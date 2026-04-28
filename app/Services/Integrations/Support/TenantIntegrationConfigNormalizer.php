<?php

namespace App\Services\Integrations\Support;

use App\Models\TenantIntegration;
use App\Services\Integrations\Auth\AuthenticationType;

class TenantIntegrationConfigNormalizer
{
    /**
     * @return array{
     *     auth: array{type: string, credentials: array<string, mixed>},
     *     connection: array{
     *         base_url: string,
     *         timeout: int,
     *         connect_timeout: int,
     *         verify_ssl: bool,
     *         ping_path: string,
     *         ping_method: string,
     *         headers: array<string, string>
     *     },
     *     processing: array<string, mixed>
     * }
     */
    public function normalize(TenantIntegration $integration): array
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : $config;
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $legacyHeaders = is_array($integration->authentication_headers) ? $integration->authentication_headers : [];

        $authType = (string) ($auth['type'] ?? AuthenticationType::Basic->value);
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];

        if ($credentials === [] && $legacyHeaders !== []) {
            $credentials = [
                'username' => (string) ($legacyHeaders['auth_username'] ?? ''),
                'password' => (string) ($legacyHeaders['auth_password'] ?? ''),
            ];
        }

        return [
            'auth' => [
                'type' => $authType,
                'credentials' => $credentials,
            ],
            'connection' => [
                'base_url' => (string) ($connection['base_url'] ?? $integration->api_url ?? ''),
                'timeout' => (int) ($connection['timeout'] ?? 30),
                'connect_timeout' => (int) ($connection['connect_timeout'] ?? 10),
                'verify_ssl' => (bool) ($connection['verify_ssl'] ?? true),
                'ping_path' => (string) ($connection['ping_path'] ?? '/'),
                'ping_method' => strtoupper((string) ($connection['ping_method'] ?? 'GET')),
                'headers' => $this->normalizeHeaders($connection['headers'] ?? []),
            ],
            'processing' => $this->normalizeProcessing($processing),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeHeaders(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProcessing(mixed $processing): array
    {
        if (! is_array($processing)) {
            $processing = [];
        }

        return [
            'sales_initial_days' => (int) ($processing['sales_initial_days'] ?? $processing['initial_days'] ?? 120),
            'products_initial_days' => (int) ($processing['products_initial_days'] ?? $processing['initial_days'] ?? 120),
            'sales_retention_days' => (int) ($processing['sales_retention_days'] ?? 120),
            'daily_lookback_days' => (int) ($processing['daily_lookback_days'] ?? 7),
            'page_size' => (int) ($processing['page_size'] ?? 1000),
            'empresa' => is_string($processing['empresa'] ?? null) ? $processing['empresa'] : '',
            'partner_key' => is_string($processing['partner_key'] ?? null) ? $processing['partner_key'] : '',
        ];
    }
}
