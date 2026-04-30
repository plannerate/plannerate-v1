<?php

namespace App\Services\Integrations\Sysmo\Concerns;

trait PicksSysmoMappedValues
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function pickString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function pickFloat(array $item, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if (is_numeric($value)) {
                return (float) $value;
            }

            if (is_string($value) && is_numeric(str_replace(',', '.', $value))) {
                return (float) str_replace(',', '.', $value);
            }
        }

        return null;
    }
}
