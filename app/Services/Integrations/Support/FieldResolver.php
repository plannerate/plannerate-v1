<?php

namespace App\Services\Integrations\Support;

class FieldResolver
{
    public function __construct(
        private readonly FieldNormalizerRegistry $normalizers,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     paths?: list<string>|callable(array<string, mixed>): mixed,
     *     transforms?: list<string>
     * }|list<string>|callable(array<string, mixed>): mixed  $definition
     */
    public function resolve(array $item, array|callable $definition): mixed
    {
        if (is_callable($definition)) {
            return $definition($item);
        }

        $paths = $definition['paths'] ?? $definition;
        $transforms = $definition['transforms'] ?? [];

        if (is_callable($paths)) {
            $value = $paths($item);

            return $this->applyTransforms($value, $transforms);
        }

        foreach ($paths as $path) {
            $value = data_get($item, $path);
            if ($this->hasValue($value)) {
                return $this->applyTransforms($value, $transforms);
            }
        }

        return $this->applyTransforms(null, $transforms);
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

    /**
     * @param  list<string>  $transforms
     */
    private function applyTransforms(mixed $value, array $transforms): mixed
    {
        foreach ($transforms as $transform) {
            $value = $this->normalizers->apply($transform, $value);
        }

        return $value;
    }
}
