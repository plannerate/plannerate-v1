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
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $value = $this->valueByPath($item, $path);
            if ($this->hasValue($value)) {
                return $this->applyTransforms($value, $transforms);
            }
        }

        $resolved = $this->applyTransforms(null, $transforms);
        $nullValue = is_array($definition) && is_string($definition['null_value'] ?? null) && $definition['null_value'] !== ''
            ? $definition['null_value']
            : null;

        if (($resolved === null || $resolved === '') && $nullValue !== null) {
            return $nullValue;
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $transforms
     */
    public function resolveExpression(array $mapped, string $expression, array $transforms = [], array $raw = []): mixed
    {
        $value = $this->evaluateExpression($mapped, $raw, $expression);

        return $this->applyTransforms($value, $transforms);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    private function evaluateExpression(array $mapped, array $raw, string $expression): ?float
    {
        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*|\d+(?:[\.,]\d+)?|[+\-*\/()]/', $expression, $matches);
        $tokens = $matches[0];

        if ($tokens === [] || trim(implode('', $tokens)) !== preg_replace('/\s+/', '', $expression)) {
            return null;
        }

        $referencedFields = [];
        foreach ($tokens as $token) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token) === 1) {
                $referencedFields[] = $token;
            }
        }

        if ($referencedFields !== [] && ! $this->hasValue($this->expressionValue($mapped, $raw, $referencedFields[0]))) {
            return null;
        }

        return $this->evaluateRpn($this->toRpn($tokens), $mapped, $raw);
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function toRpn(array $tokens): array
    {
        $output = [];
        $operators = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

        foreach ($tokens as $token) {
            if (is_numeric(str_replace(',', '.', $token)) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token) === 1) {
                $output[] = $token;

                continue;
            }

            if ($token === '(') {
                $operators[] = $token;

                continue;
            }

            if ($token === ')') {
                while ($operators !== [] && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }

                array_pop($operators);

                continue;
            }

            while ($operators !== [] && end($operators) !== '(' && ($precedence[end($operators)] ?? 0) >= ($precedence[$token] ?? 0)) {
                $output[] = array_pop($operators);
            }

            $operators[] = $token;
        }

        while ($operators !== []) {
            $operator = array_pop($operators);

            if ($operator !== '(') {
                $output[] = $operator;
            }
        }

        return $output;
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    private function evaluateRpn(array $tokens, array $mapped, array $raw): ?float
    {
        $stack = [];

        foreach ($tokens as $token) {
            if (is_numeric(str_replace(',', '.', $token))) {
                $stack[] = (float) str_replace(',', '.', $token);

                continue;
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token) === 1) {
                $stack[] = $this->numericValue($this->expressionValue($mapped, $raw, $token));

                continue;
            }

            if (count($stack) < 2) {
                return null;
            }

            $right = array_pop($stack);
            $left = array_pop($stack);

            $stack[] = match ($token) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right == 0.0 ? null : $left / $right,
                default => null,
            };

            if (end($stack) === null) {
                return null;
            }
        }

        return count($stack) === 1 ? $stack[0] : null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    private function expressionValue(array $mapped, array $raw, string $token): mixed
    {
        if (array_key_exists($token, $mapped)) {
            return $mapped[$token];
        }

        return data_get($raw, $token);
    }

    private function numericValue(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));

            return is_numeric($normalized) ? (float) $normalized : 0.0;
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function valueByPath(array $item, string $path): mixed
    {
        if (! str_contains($path, '[')) {
            return data_get($item, $path);
        }

        $values = [$item];
        foreach (explode('.', $path) as $segment) {
            $values = $this->resolveSegment($values, $segment);

            if ($values === []) {
                return null;
            }
        }

        return count($values) === 1 ? $values[0] : $values;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<mixed>
     */
    private function resolveSegment(array $values, string $segment): array
    {
        if ($segment === '*') {
            return collect($values)
                ->filter(fn (mixed $value): bool => is_array($value))
                ->flatMap(fn (array $value): array => array_values($value))
                ->values()
                ->all();
        }

        if (preg_match('/^([^\[]+)\[([^=\]]+)=([^\]]*)\]$/', $segment, $matches) === 1) {
            return $this->resolveFilteredSegment($values, $matches[1], $matches[2], $matches[3]);
        }

        return collect($values)
            ->map(fn (mixed $value): mixed => is_array($value) ? data_get($value, $segment) : null)
            ->filter(fn (mixed $value): bool => $value !== null)
            ->values()
            ->all();
    }

    /**
     * @param  list<mixed>  $values
     * @return list<mixed>
     */
    private function resolveFilteredSegment(array $values, string $key, string $filterKey, string $filterValue): array
    {
        return collect($values)
            ->map(fn (mixed $value): mixed => is_array($value) ? data_get($value, $key) : null)
            ->filter(fn (mixed $value): bool => is_array($value))
            ->flatMap(function (array $items) use ($filterKey, $filterValue): array {
                return collect($items)
                    ->filter(fn (mixed $item): bool => is_array($item) && (string) data_get($item, $filterKey) === $filterValue)
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return collect($value)
                ->flatten()
                ->contains(fn (mixed $item): bool => $this->hasValue($item));
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
