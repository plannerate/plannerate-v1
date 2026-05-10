<?php

namespace App\Services\Integrations\Http;

use App\Models\TenantIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IntegrationTokenResolver
{
    public function resolve(TenantIntegration $integration, ?string $bearerToken = null): ?string
    {
        if (is_string($bearerToken) && $bearerToken !== '') {
            return $bearerToken;
        }

        $config = $this->config($integration);
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $type = (string) ($auth['type'] ?? 'none');
        $tokenMode = (string) ($auth['token_mode'] ?? 'manual');

        if ($type === 'bearer' && $tokenMode !== 'fetch') {
            $token = (string) ($credentials['token'] ?? '');

            return $token !== '' ? $token : null;
        }

        if ($type === 'bearer_fetch' || ($type === 'bearer' && $tokenMode === 'fetch') || $this->shouldUseLegacyGescooperToken($integration, $auth)) {
            return $this->cachedFetchedToken($integration);
        }

        return null;
    }

    private function cachedFetchedToken(TenantIntegration $integration): string
    {
        $cacheKey = $this->cacheKey($integration);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->fetchToken($integration);
        Cache::put($cacheKey, $token, $this->tokenExpiration($token));

        return $token;
    }

    private function fetchToken(TenantIntegration $integration): string
    {
        $config = $this->config($integration);
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $tokenRequest = is_array($auth['token_request'] ?? null) ? $auth['token_request'] : [];
        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new RuntimeException('Credenciais não configuradas para buscar token da integração.');
        }

        $method = strtolower((string) ($tokenRequest['method'] ?? 'POST'));
        $url = $this->url($integration, (string) ($tokenRequest['path'] ?? $this->legacyGescooperAuthPath($integration)));
        $headers = $this->enabledKeyValueRows($tokenRequest['headers'] ?? []);
        $params = $this->enabledKeyValueRows($tokenRequest['params'] ?? []);
        $body = $this->tokenBody($integration, $tokenRequest, $username, $password);

        $request = Http::timeout(60)
            ->connectTimeout(15)
            ->acceptJson()
            ->withHeaders($headers);

        $response = match ($method) {
            'get' => $request->get($url, $params),
            'post' => $request->post($this->appendQuery($url, $params), $body),
            'put' => $request->put($this->appendQuery($url, $params), $body),
            'patch' => $request->patch($this->appendQuery($url, $params), $body),
            default => throw new RuntimeException(sprintf('Método HTTP [%s] não suportado para buscar token de integração.', $method)),
        };

        $payload = $response->throw()->json();
        $token = $this->extractToken(is_array($payload) ? $payload : [], $tokenRequest);

        if ($token === '') {
            throw new RuntimeException('Token não encontrado na resposta de autenticação da integração.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $tokenRequest
     * @return array<string, mixed>
     */
    private function tokenBody(TenantIntegration $integration, array $tokenRequest, string $username, string $password): array
    {
        $body = $this->enabledKeyValueRows($tokenRequest['body'] ?? []);
        $usernameField = (string) ($tokenRequest['username_field'] ?? $this->legacyGescooperUsernameField($integration));
        $passwordField = (string) ($tokenRequest['password_field'] ?? $this->legacyGescooperPasswordField($integration));

        if ($usernameField !== '' && ! array_key_exists($usernameField, $body)) {
            $body[$usernameField] = $username;
        }

        if ($passwordField !== '' && ! array_key_exists($passwordField, $body)) {
            $body[$passwordField] = $password;
        }

        if ((string) $integration->integration_type === 'gescooper' && ! array_key_exists('dispositivoUID', $body)) {
            $config = $this->config($integration);
            $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
            $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
            $body['dispositivoUID'] = (string) ($credentials['dispositivo_uid'] ?? 'plannerate');
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $tokenRequest
     */
    private function extractToken(array $payload, array $tokenRequest): string
    {
        $paths = [
            (string) ($tokenRequest['response_path'] ?? ''),
            'token',
            'access_token',
            'jwt',
            'data.token',
            'data.access_token',
        ];

        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }

            $candidate = data_get($payload, $path);
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function tokenExpiration(string $token): CarbonImmutable
    {
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $decoded = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '', true);
            $exp = is_array($decoded) ? ($decoded['exp'] ?? null) : null;
            if (is_numeric($exp)) {
                $expiration = CarbonImmutable::createFromTimestamp((int) $exp)->subMinutes(1);

                return $expiration->isFuture()
                    ? $expiration
                    : CarbonImmutable::now()->addMinutes(30);
            }
        }

        return CarbonImmutable::now()->addMinutes(55);
    }

    private function cacheKey(TenantIntegration $integration): string
    {
        $config = $this->config($integration);
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];

        return sprintf(
            'integrations:token:%s:%s',
            (string) ($integration->id ?: spl_object_id($integration)),
            sha1(json_encode($auth, JSON_THROW_ON_ERROR)),
        );
    }

    private function url(TenantIntegration $integration, string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        $config = $this->config($integration);
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $baseUrl = rtrim((string) ($connection['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException(sprintf(
                'Base URL não configurada para integração [%s].',
                (string) $integration->id,
            ));
        }

        return $baseUrl.'/'.ltrim($endpoint, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function appendQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function config(TenantIntegration $integration): array
    {
        return is_array($integration->config) ? $integration->config : [];
    }

    /**
     * @return array<string, string>
     */
    private function enabledKeyValueRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $values = [];
        foreach ($rows as $key => $row) {
            if (is_string($key) && (is_string($row) || is_numeric($row))) {
                $values[$key] = (string) $row;

                continue;
            }

            if (! is_array($row) || ! $this->rowIsEnabled($row)) {
                continue;
            }

            $rowKey = trim((string) ($row['key'] ?? ''));
            if ($rowKey === '') {
                continue;
            }

            $values[$rowKey] = (string) ($row['value'] ?? '');
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsEnabled(array $row): bool
    {
        if (! array_key_exists('enabled', $row)) {
            return true;
        }

        $enabled = $row['enabled'];
        if (is_bool($enabled)) {
            return $enabled;
        }

        if (is_string($enabled) || is_int($enabled)) {
            return filter_var($enabled, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private function shouldUseLegacyGescooperToken(TenantIntegration $integration, array $auth): bool
    {
        if ((string) $integration->integration_type !== 'gescooper') {
            return false;
        }

        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];

        return (string) ($credentials['username'] ?? '') !== ''
            && (string) ($credentials['password'] ?? '') !== '';
    }

    private function legacyGescooperAuthPath(TenantIntegration $integration): string
    {
        if ((string) $integration->integration_type !== 'gescooper') {
            return '/token';
        }

        $config = $this->config($integration);
        $paths = is_array($config['paths'] ?? null) ? $config['paths'] : [];

        return (string) ($paths['auth'] ?? '/v1/Token');
    }

    private function legacyGescooperUsernameField(TenantIntegration $integration): string
    {
        return (string) $integration->integration_type === 'gescooper' ? 'usuario' : 'username';
    }

    private function legacyGescooperPasswordField(TenantIntegration $integration): string
    {
        return (string) $integration->integration_type === 'gescooper' ? 'senha' : 'password';
    }
}
