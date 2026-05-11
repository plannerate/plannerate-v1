<?php

namespace App\Services\Integrations\Support;

use App\Models\TenantIntegration;
use Illuminate\Support\Arr;

class ResolvedIntegrationConfig
{
    /**
     * @param  array<string, mixed>  $apiConfig
     * @param  array<string, mixed>  $tenantConfig
     */
    public function __construct(
        public readonly TenantIntegration $integration,
        private readonly array $apiConfig,
        private readonly array $tenantConfig,
    ) {}

    public function provider(): string
    {
        return (string) $this->integration->integration_type;
    }

    /**
     * @return array<string, mixed>
     */
    public function apiConfig(): array
    {
        return $this->apiConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function tenantConfig(): array
    {
        return $this->tenantConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return array_replace_recursive($this->apiConfig, $this->tenantConfig);
    }

    /**
     * @return array<string, mixed>
     */
    public function connection(): array
    {
        $connection = $this->config()['connection'] ?? [];

        return is_array($connection) ? $connection : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function auth(): array
    {
        $auth = $this->config()['auth'] ?? [];

        return is_array($auth) ? $auth : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function response(): array
    {
        $response = $this->config()['response'] ?? [];

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function requests(): array
    {
        $requests = $this->config()['requests'] ?? [];

        return is_array($requests) ? $requests : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function request(string $resource): array
    {
        $requests = $this->requests();
        $resourceConfig = $this->resourceRequests()[$resource] ?? [];

        return array_replace_recursive(
            Arr::except($requests, [
                'paths',
                ...$this->legacyResourceRequestKeys($requests),
            ]),
            is_array($resourceConfig) ? $resourceConfig : [],
        );
    }

    public function pathIsEnabled(string $resource): bool
    {
        $request = $this->request($resource);

        if (! array_key_exists('enabled', $request)) {
            return true;
        }

        $enabled = $request['enabled'];

        if (is_bool($enabled)) {
            return $enabled;
        }

        if (is_string($enabled) || is_int($enabled)) {
            return filter_var($enabled, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
        }

        return true;
    }

    public function targetTable(string $resource): string
    {
        $request = $this->request($resource);
        $targetTable = trim((string) ($request['target_table'] ?? $resource));

        return $targetTable !== '' ? $targetTable : $resource;
    }

    /**
     * @return list<string>
     */
    public function uniqueBy(string $resource): array
    {
        $request = $this->request($resource);
        $uniqueBy = $request['unique_by'] ?? [];

        if (is_string($uniqueBy)) {
            $uniqueBy = [$uniqueBy];
        }

        if (! is_array($uniqueBy)) {
            return [];
        }

        return collect($uniqueBy)
            ->filter(fn (mixed $column): bool => is_string($column) && trim($column) !== '')
            ->map(fn (string $column): string => trim($column))
            ->values()
            ->all();
    }

    public function dateStrategy(string $resource): string
    {
        $request = $this->request($resource);
        $configured = trim((string) ($request['date_strategy'] ?? ''));

        if ($configured !== '') {
            return $configured;
        }

        return match ($this->targetTable($resource)) {
            'products' => 'products_incremental',
            'sales' => 'sales_incremental',
            default => 'none',
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function resourceRequests(): array
    {
        $requests = $this->requests();
        $paths = $requests['paths'] ?? [];

        if (is_array($paths) && $paths !== []) {
            return collect($paths)
                ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && is_array($value))
                ->map(fn (array $value): array => $value)
                ->all();
        }

        return collect($requests)
            ->only($this->legacyResourceRequestKeys($requests))
            ->filter(fn (mixed $value): bool => is_array($value))
            ->map(fn (array $value): array => $value)
            ->all();
    }

    public function path(string $resource, string $fallback = ''): string
    {
        $paths = $this->tenantConfig['paths'] ?? [];
        $path = is_array($paths) ? trim((string) ($paths[$resource] ?? '')) : '';

        if ($path !== '') {
            return $path;
        }

        $request = $this->request($resource);

        return trim((string) ($request['fallback_path'] ?? $fallback));
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldMap(string $resource, array $fallback = []): array
    {
        $configuredRows = $this->fieldMapRows($resource);
        $configured = [];

        foreach ($configuredRows as $row) {
            $target = is_string($row['target'] ?? null) ? trim($row['target']) : '';
            $source = is_string($row['source'] ?? null) ? trim($row['source']) : '';

            if ($target === '' || $source === '') {
                continue;
            }

            $configured[$target] = [
                'transforms' => collect($row['transforms'] ?? [])
                    ->filter(fn (mixed $transform): bool => is_string($transform) && trim($transform) !== '')
                    ->values()
                    ->all(),
            ];

            if ($this->isExpression($source)) {
                $configured[$target]['expression'] = $source;
            } else {
                $configured[$target]['paths'] = [$source];
            }
        }

        return $configured === [] ? $fallback : array_replace($fallback, $configured);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fieldMapRows(string $resource): array
    {
        $request = $this->request($resource);
        $rows = $request['field_map'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, fn (mixed $row): bool => is_array($row)));
    }

    public function separateByStore(): bool
    {
        $processing = $this->tenantConfig['processing'] ?? [];

        return is_array($processing) && (bool) ($processing['separate_by_store'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $requests
     * @return list<string>
     */
    private function legacyResourceRequestKeys(array $requests): array
    {
        return collect($requests)
            ->filter(fn (mixed $value): bool => is_array($value) && (
                array_key_exists('fallback_path', $value)
                || array_key_exists('field_map', $value)
                || array_key_exists('date_fields', $value)
            ))
            ->keys()
            ->filter(fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();
    }

    private function isExpression(string $source): bool
    {
        return preg_match('/\s[+\-*\/]\s|[()]/', $source) === 1;
    }
}
