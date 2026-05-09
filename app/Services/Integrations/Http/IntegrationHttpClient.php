<?php

namespace App\Services\Integrations\Http;

use App\Models\TenantIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IntegrationHttpClient
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    public function request(
        TenantIntegration $integration,
        string $method,
        string $endpoint,
        array $query = [],
        array $body = [],
        ?string $bearerToken = null,
    ): Response {
        $url = $this->url($integration, $endpoint);
        $request = $this->pendingRequest($integration, $bearerToken);
        $method = strtolower($method);

        $response = match ($method) {
            'get' => $request->get($url, $this->mergeQuery($integration, $query)),
            'post' => $request->post($this->appendQuery($integration, $url, $query), $body),
            default => throw new RuntimeException(sprintf('Método HTTP [%s] não suportado para integrações.', $method)),
        };

        return $response->throw();
    }

    private function pendingRequest(TenantIntegration $integration, ?string $bearerToken = null): PendingRequest
    {
        $config = $this->config($integration);
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];

        $request = Http::timeout(60)
            ->connectTimeout(15)
            ->acceptJson()
            ->withHeaders($this->enabledKeyValueRows($connection['headers'] ?? []));

        if (is_string($bearerToken) && $bearerToken !== '') {
            return $request->withToken($bearerToken);
        }

        return match ((string) ($auth['type'] ?? 'none')) {
            'basic' => $request->withBasicAuth(
                (string) ($credentials['username'] ?? ''),
                (string) ($credentials['password'] ?? ''),
            ),
            'bearer' => $request->withToken((string) ($credentials['token'] ?? '')),
            default => $request,
        };
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
     * @return array<string, mixed>
     */
    private function mergeQuery(TenantIntegration $integration, array $query): array
    {
        $config = $this->config($integration);
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];

        return [
            ...$this->enabledKeyValueRows($connection['params'] ?? []),
            ...$query,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function appendQuery(TenantIntegration $integration, string $url, array $query): string
    {
        $query = $this->mergeQuery($integration, $query);

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
}
