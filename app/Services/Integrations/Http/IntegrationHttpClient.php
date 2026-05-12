<?php

namespace App\Services\Integrations\Http;

use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IntegrationHttpClient
{
    public function __construct(
        private readonly ?IntegrationTokenResolver $tokenResolver = null,
        private readonly ?ResolvedIntegrationConfigResolver $configResolver = null,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    public function request(
        ResolvedIntegrationConfig|TenantIntegration $integration,
        string $method,
        string $endpoint,
        array $query = [],
        array $body = [],
        ?string $bearerToken = null,
    ): Response {
        $config = $this->resolveConfig($integration);
        $integration = $config->integration;
        $url = $this->url($config, $endpoint);
        $request = $this->pendingRequest($config, $bearerToken);
        $method = strtolower($method);
        $query = $this->mergeQuery($config, $query);
        $body = $this->mergeBody($config, $body);

        $response = match ($method) {
            'get' => $request->get($url, $query),
            'post' => $request->post($this->appendQuery($url, $query), $body),
            'put' => $request->put($this->appendQuery($url, $query), $body),
            'patch' => $request->patch($this->appendQuery($url, $query), $body),
            'delete' => $request->delete($this->appendQuery($url, $query)),
            default => throw new RuntimeException(sprintf('Método HTTP [%s] não suportado para integrações.', $method)),
        };

        if ($response->failed()) {
            Log::warning('Integração HTTP request failed.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'provider' => (string) $integration->integration_type,
                'method' => strtoupper($method),
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
            ]);
        }

        return $response->throw();
    }

    private function pendingRequest(ResolvedIntegrationConfig $config, ?string $bearerToken = null): PendingRequest
    {
        $auth = $config->auth();
        $connection = $config->connection();
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];

        $request = Http::timeout(60)
            ->connectTimeout(15)
            ->acceptJson()
            ->withHeaders($this->enabledKeyValueRows($connection['headers'] ?? []));

        $resolvedBearerToken = ($this->tokenResolver ?? new IntegrationTokenResolver)->resolve($config, $bearerToken);
        if (is_string($resolvedBearerToken) && $resolvedBearerToken !== '') {
            return $request->withToken($resolvedBearerToken);
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
     * @return array<string, mixed>
     */
    private function mergeQuery(ResolvedIntegrationConfig $config, array $query): array
    {
        $connection = $config->connection();

        return [
            ...$this->enabledKeyValueRows($connection['params'] ?? []),
            ...$query,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeBody(ResolvedIntegrationConfig $config, array $body): array
    {
        $connection = $config->connection();

        return [
            ...$this->enabledKeyValueRows($connection['body'] ?? []),
            ...$body,
        ];
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

    private function resolveConfig(ResolvedIntegrationConfig|TenantIntegration $config): ResolvedIntegrationConfig
    {
        if ($config instanceof ResolvedIntegrationConfig) {
            return $config;
        }

        return ($this->configResolver ?? app(ResolvedIntegrationConfigResolver::class))->resolve($config);
    }
}
