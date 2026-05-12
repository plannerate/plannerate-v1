<?php

namespace App\Services\Integrations\Http;

use App\Services\Integrations\Support\ResolvedIntegrationConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IntegrationTokenResolver
{
    public function resolve(ResolvedIntegrationConfig $config, ?string $bearerToken = null): ?string
    {
        if (is_string($bearerToken) && $bearerToken !== '') {
            return $bearerToken;
        }

        $auth = $config->auth();
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $type = (string) ($auth['type'] ?? 'none');
        $tokenMode = (string) ($auth['token_mode'] ?? 'manual');

        if ($type === 'bearer' && $tokenMode !== 'fetch') {
            $token = (string) ($credentials['token'] ?? '');

            return $token !== '' ? $token : null;
        }

        if ($type === 'bearer_fetch' || ($type === 'bearer' && $tokenMode === 'fetch')) {
            return $this->cachedFetchedToken($config);
        }

        return null;
    }

    private function cachedFetchedToken(ResolvedIntegrationConfig $config): string
    {
        $cacheKey = $this->cacheKey($config);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->fetchToken($config);
        Cache::put($cacheKey, $token, $this->tokenExpiration($token));

        return $token;
    }

    private function fetchToken(ResolvedIntegrationConfig $config): string
    {
        $integration = $config->integration;
        $auth = $config->auth();
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $tokenRequest = is_array($auth['token_request'] ?? null) ? $auth['token_request'] : [];
        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new RuntimeException('Credenciais não configuradas para buscar token da integração.');
        }

        $method = strtolower((string) ($tokenRequest['method'] ?? 'POST'));
        $url = $this->url($config, (string) ($tokenRequest['path'] ?? '/token'));
        $headers = $this->enabledKeyValueRows($tokenRequest['headers'] ?? []);
        $params = $this->enabledKeyValueRows($tokenRequest['params'] ?? []);
        $body = $this->tokenBody($tokenRequest, $username, $password);

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

        if ($response->failed()) {
            Log::warning('Integração token request failed.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'provider' => (string) $integration->integration_type,
                'method' => strtoupper($method),
                'endpoint' => (string) ($tokenRequest['path'] ?? '/token'),
                'url' => $url,
                'status' => $response->status(),
            ]);
        }

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
    private function tokenBody(array $tokenRequest, string $username, string $password): array
    {
        $body = $this->enabledKeyValueRows($tokenRequest['body'] ?? []);
        $usernameField = (string) ($tokenRequest['username_field'] ?? 'username');
        $passwordField = (string) ($tokenRequest['password_field'] ?? 'password');

        if ($usernameField !== '' && ! array_key_exists($usernameField, $body)) {
            $body[$usernameField] = $username;
        }

        if ($passwordField !== '' && ! array_key_exists($passwordField, $body)) {
            $body[$passwordField] = $password;
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

    private function cacheKey(ResolvedIntegrationConfig $config): string
    {
        $integration = $config->integration;

        return sprintf(
            'integrations:token:%s:%s',
            (string) ($integration->id ?: spl_object_id($integration)),
            sha1(json_encode($config->auth(), JSON_THROW_ON_ERROR)),
        );
    }

    private function url(ResolvedIntegrationConfig $config, string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        $connection = $config->connection();
        $baseUrl = rtrim((string) ($connection['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException(sprintf(
                'Base URL não configurada para integração [%s].',
                (string) $config->integration->id,
            ));
        }

        return $this->joinUrl($baseUrl, $endpoint);
    }

    private function joinUrl(string $baseUrl, string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');
        $basePath = trim((string) parse_url($baseUrl, PHP_URL_PATH), '/');
        $lastBaseSegment = collect(explode('/', $basePath))->filter()->last();
        $endpointSegments = collect(explode('/', $endpoint))->filter()->values();

        if (
            is_string($lastBaseSegment)
            && is_string($endpointSegments->first())
            && strcasecmp($lastBaseSegment, (string) $endpointSegments->first()) === 0
        ) {
            $endpoint = $endpointSegments->slice(1)->implode('/');
        }

        return rtrim($baseUrl, '/').($endpoint !== '' ? '/'.$endpoint : '');
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
}
