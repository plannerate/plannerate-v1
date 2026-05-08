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
     *         headers: array<string, string>,
     *         params: array<string, string>,
     *         default_body: string
     *     },
     *     processing: array<string, mixed>
     * }
     */
    public function normalize(TenantIntegration $integration): array
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];

        $authType = (string) ($auth['type'] ?? AuthenticationType::Basic->value);
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];

        return [
            'auth' => [
                'type' => $authType,
                'credentials' => $credentials,
            ],
            'connection' => [
                'base_url' => (string) ($connection['base_url'] ?? ''),
                'timeout' => (int) ($connection['timeout'] ?? 30),
                'connect_timeout' => (int) ($connection['connect_timeout'] ?? 10),
                'verify_ssl' => (bool) ($connection['verify_ssl'] ?? true),
                'ping_path' => (string) ($connection['ping_path'] ?? '/'),
                'ping_method' => strtoupper((string) ($connection['ping_method'] ?? 'GET')),
                'headers' => $this->normalizeKeyValuePairs($connection['headers'] ?? []),
                'params' => $this->normalizeKeyValuePairs($connection['params'] ?? []),
                'default_body' => (string) ($connection['default_body'] ?? ''),
            ],
            'processing' => $this->normalizeProcessing($processing),
        ];
    }

    /**
     * Normalizes headers or params stored as either:
     *   - legacy dict: {"Content-Type": "application/json"}
     *   - new array of objects: [{"key": "Content-Type", "value": "...", "enabled": true}]
     *
     * Returns only enabled entries as a key→value dict for HTTP client use.
     *
     * @return array<string, string>
     */
    private function normalizeKeyValuePairs(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                // Legacy dict format
                $normalized[$key] = (string) $value;
            } elseif (is_array($value) && isset($value['key']) && ($value['enabled'] ?? true)) {
                // New array-of-objects format — only include if enabled
                $normalized[(string) $value['key']] = (string) ($value['value'] ?? '');
            }
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
            'sales_page_size' => (int) ($processing['sales_page_size'] ?? $processing['page_size'] ?? 20000),
            'products_page_size' => (int) ($processing['products_page_size'] ?? $processing['page_size'] ?? 1000),
            'empresa' => (string) ($processing['empresa'] ?? ''),
            'partner_key' => (string) ($processing['partner_key'] ?? ''),
            'sales_tipo_consulta' => (string) ($processing['sales_tipo_consulta'] ?? 'produto'),
        ];
    }
}
