<?php

namespace App\Http\Requests\Landlord\Concerns;

trait NormalizesIntegrationApiRequests
{
    /**
     * @param  array<string, mixed>  $requests
     * @return array<string, mixed>
     */
    private function normalizeIntegrationApiRequests(array $requests): array
    {
        if (($requests['payload'] ?? null) === 'body') {
            unset($requests['payload']);
        }

        if (($requests['page_size_payload'] ?? null) === 'body') {
            unset($requests['page_size_payload']);
        }

        $paths = is_array($requests['paths'] ?? null) ? $requests['paths'] : [];

        foreach ($this->legacyPathKeys($requests) as $key) {
            if (! is_array($requests[$key] ?? null)) {
                continue;
            }

            $paths[$key] ??= $requests[$key];
            unset($requests[$key]);
        }

        $requests['paths'] = $this->normalizePivotTables($paths);

        return $requests;
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return array<string, mixed>
     */
    private function normalizePivotTables(array $paths): array
    {
        foreach ($paths as $pathKey => $pathConfig) {
            if (! is_array($pathConfig)) {
                continue;
            }

            $pivotTables = $pathConfig['pivot_tables'] ?? null;

            if (! is_array($pivotTables)) {
                continue;
            }

            $normalizedPivots = [];

            foreach ($pivotTables as $pivotConfig) {
                if (! is_array($pivotConfig)) {
                    continue;
                }

                $table = (string) ($pivotConfig['table'] ?? '');
                $foreignKey = (string) ($pivotConfig['foreign_key'] ?? '');
                $relatedKey = (string) ($pivotConfig['related_key'] ?? '');

                $uniqueBy = collect(is_array($pivotConfig['unique_by'] ?? null) ? $pivotConfig['unique_by'] : [])
                    ->filter(fn (mixed $column): bool => is_string($column) && $column !== '')
                    ->values()
                    ->all();

                if ($uniqueBy === []) {
                    $uniqueBy = collect([$foreignKey, $relatedKey])
                        ->filter(fn (string $column): bool => $column !== '')
                        ->values()
                        ->all();
                }

                if ($table === 'product_store' && ! in_array('tenant_id', $uniqueBy, true)) {
                    $uniqueBy = ['tenant_id', ...$uniqueBy];
                }

                if ($uniqueBy !== []) {
                    $pivotConfig['unique_by'] = array_values(array_unique($uniqueBy));
                }

                $normalizedPivots[] = $pivotConfig;
            }

            $pathConfig['pivot_tables'] = $normalizedPivots;
            $paths[$pathKey] = $pathConfig;
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $requests
     * @return list<string>
     */
    private function legacyPathKeys(array $requests): array
    {
        $reserved = [
            'method',
            'paths',
            'page_field',
            'page_value_type',
            'page_size_field',
            'page_size_payload',
            'default_page_size',
            'min_page_size',
            'max_page_size',
            'store_document_field',
            'fixed_query',
            'fixed_body',
            'date_strategy',
            'enabled',
            'unique_by',
            'initial_days',
            'run_finalize',
        ];

        return collect($requests)
            ->reject(fn (mixed $value, string|int $key): bool => in_array((string) $key, $reserved, true))
            ->filter(fn (mixed $value): bool => is_array($value) && (
                array_key_exists('target_table', $value)
                || array_key_exists('fallback_path', $value)
                || array_key_exists('field_map', $value)
                || array_key_exists('date_fields', $value)
            ))
            ->keys()
            ->filter(fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();
    }
}
