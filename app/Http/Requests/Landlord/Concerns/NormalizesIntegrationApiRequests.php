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
        $paths = is_array($requests['paths'] ?? null) ? $requests['paths'] : [];

        foreach ($this->legacyPathKeys($requests) as $key) {
            if (! is_array($requests[$key] ?? null)) {
                continue;
            }

            $paths[$key] ??= $requests[$key];
            unset($requests[$key]);
        }

        $requests['paths'] = $paths;

        return $requests;
    }

    /**
     * @param  array<string, mixed>  $requests
     * @return list<string>
     */
    private function legacyPathKeys(array $requests): array
    {
        $reserved = [
            'method',
            'payload',
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
