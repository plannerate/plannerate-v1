<?php

namespace App\Services\Integrations;

use Carbon\Carbon;

/**
 * Resolves a single field value from an API response item
 * by applying a source path and a sequence of transforms.
 */
class FieldValueResolver
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $transforms
     */
    public function resolve(array $item, string $source, array $transforms): mixed
    {
        $value = $this->extractSource($item, $source);

        foreach ($transforms as $transform) {
            $value = $this->applyTransform($value, $transform);
        }

        return $value;
    }

    // ─── Source extraction ───────────────────────────────────────────────────

    private function extractSource(array $item, string $source): mixed
    {
        // Arithmetic expression: "field_a - field_b * field_c / field_d"
        if (preg_match('/\S\s*[+\-*\/]\s*\S/', $source)) {
            return $this->evalExpression($item, $source);
        }

        // Array filter: "path.array[key=value].subfield"
        if (preg_match('/\[(\w+)=(.+?)\]/', $source, $m, PREG_OFFSET_CAPTURE)) {
            return $this->extractWithFilter($item, $source, (int) $m[0][1], $m[1][0], $m[2][0]);
        }

        // Wildcard: "path.*.subfield"
        if (str_contains($source, '*')) {
            return $this->extractWildcard($item, $source);
        }

        return data_get($item, $source);
    }

    /** @param  array<string, mixed>  $item */
    private function evalExpression(array $item, string $expr): float
    {
        $tokens = preg_split('/\s*([+\-*\/])\s*/', $expr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return 0.0;
        }

        $values = [];
        $ops = [];

        foreach ($tokens as $index => $token) {
            if ($index % 2 === 0) {
                $resolved = data_get($item, trim($token), 0);
                $values[] = $this->toFloat($resolved);

                continue;
            }

            $ops[] = $token;
        }

        if ($values === []) {
            return 0.0;
        }

        // First pass: * and /
        $reducedValues = [$values[0]];
        $reducedOps = [];

        foreach ($ops as $index => $operator) {
            $right = $values[$index + 1] ?? 0.0;

            if ($operator === '*') {
                $reducedValues[count($reducedValues) - 1] *= $right;

                continue;
            }

            if ($operator === '/') {
                $reducedValues[count($reducedValues) - 1] = $right !== 0.0
                    ? $reducedValues[count($reducedValues) - 1] / $right
                    : 0.0;

                continue;
            }

            $reducedOps[] = $operator;
            $reducedValues[] = $right;
        }

        // Second pass: + and -
        $result = $reducedValues[0] ?? 0.0;

        foreach ($reducedOps as $index => $operator) {
            $right = $reducedValues[$index + 1] ?? 0.0;

            if ($operator === '+') {
                $result += $right;

                continue;
            }

            $result -= $right;
        }

        return $result;
    }

    private function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));

            return is_numeric($normalized) ? (float) $normalized : 0.0;
        }

        return 0.0;
    }

    /**
     * Handles "path.array[filterKey=filterVal].subfield"
     *
     * @param  array<string, mixed>  $item
     * @return array<int, mixed>|mixed
     */
    private function extractWithFilter(array $item, string $source, int $bracketPos, string $filterKey, string $filterVal): mixed
    {
        $arrayPath = rtrim(substr($source, 0, $bracketPos), '.');
        $afterBracket = ltrim((string) preg_replace('/\[.+?\]/', '', substr($source, $bracketPos)), '.');

        $collection = data_get($item, $arrayPath);

        if (! is_array($collection)) {
            return null;
        }

        $filtered = array_values(array_filter(
            $collection,
            fn (mixed $row): bool => is_array($row) && (string) ($row[$filterKey] ?? '') === $filterVal,
        ));

        if ($afterBracket === '') {
            return $filtered;
        }

        return array_values(array_map(fn (array $row): mixed => data_get($row, $afterBracket), $filtered));
    }

    /**
     * Handles "path.*.subfield" — collects all values from nested array.
     *
     * @param  array<string, mixed>  $item
     * @return array<int, mixed>
     */
    private function extractWildcard(array $item, string $source): array
    {
        [$before, $after] = explode('.*', $source, 2);
        $collection = data_get($item, rtrim($before, '.'));

        if (! is_array($collection)) {
            return [];
        }

        $subPath = ltrim($after, '.');

        return array_values(array_map(
            fn (mixed $row): mixed => $subPath !== '' && is_array($row) ? data_get($row, $subPath) : $row,
            $collection,
        ));
    }

    // ─── Transforms ──────────────────────────────────────────────────────────

    private function applyTransform(mixed $value, string $transform): mixed
    {
        return match ($transform) {
            'string' => $value !== null ? (string) $value : null,
            'decimal' => $value !== null ? (float) $value : null,
            'integer' => $value !== null ? (int) $value : null,
            'alnum' => $value !== null ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $value) : null,
            'date' => $this->toDate($value),
            'first' => is_array($value) ? ($value[0] ?? null) : $value,
            'filter_filled' => is_array($value) ? array_values(array_filter($value, fn (mixed $v): bool => $v !== null && $v !== '')) : $value,
            'max_date' => $this->maxDate($value),
            'ean' => $this->normalizeEan($value),
            'not_null' => $value,
            'round2' => $value !== null ? round((float) $value, 2) : null,
            default => $value,
        };
    }

    private function toDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function maxDate(mixed $values): ?string
    {
        if (! is_array($values) || $values === []) {
            return null;
        }

        $dates = array_values(array_filter($values, fn (mixed $v): bool => $v !== null && $v !== ''));

        if ($dates === []) {
            return null;
        }

        usort($dates, fn (mixed $a, mixed $b): int => strcmp((string) $a, (string) $b));

        return $this->toDate(end($dates));
    }

    private function normalizeEan(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $ean = preg_replace('/\D/', '', (string) $value) ?? '';

        return $ean !== '' ? $ean : null;
    }
}
