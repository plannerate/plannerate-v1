<?php

namespace App\Services\Integrations\Support;

class FieldResolver
{
    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>|callable(array<string, mixed>): mixed  $paths
     */
    public function resolve(array $item, array|callable $paths): mixed
    {
        if (is_callable($paths)) {
            return $paths($item);
        }

        foreach ($paths as $path) {
            $value = data_get($item, $path);
            if ($this->hasValue($value)) {
                return $value;
            }
        }

        return null;
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }
}
