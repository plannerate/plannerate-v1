<?php

namespace App\Services\Integrations\Support;

use App\Models\EanReference;
use Illuminate\Support\Carbon;

class FieldNormalizerRegistry
{
    public function apply(string $normalizer, mixed $value): mixed
    {
        return match ($normalizer) {
            'string' => $this->normalizeString($value),
            'int' => $this->normalizeInt($value),
            'integer' => $this->normalizeInt($value),
            'float' => $this->normalizeFloat($value),
            'decimal' => $this->normalizeFloat($value),
            'ean' => $this->normalizeEan($value),
            'alnum' => $this->normalizeAlphaNumeric($value),
            'date' => $this->normalizeDate($value),
            'first' => $this->firstFilled($value),
            'filter_filled' => $this->filterFilled($value),
            'max' => $this->maxValue($value),
            'max_date' => $this->maxDate($value),
            'round2' => $this->roundValue($value, 2),
            default => $value,
        };
    }

    private function normalizeString(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $this->firstFilled($value);
        }

        if ($value === null) {
            return null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = $this->firstFilled($value);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeFloat(mixed $value): ?float
    {
        if (is_array($value)) {
            $value = $this->firstFilled($value);
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = str_replace(',', '.', trim($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function normalizeEan(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $this->firstFilled($value);
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = EanReference::normalizeEan((string) $value);
        if ($normalized === '' || strlen($normalized) > 13) {
            return null;
        }

        return $normalized;
    }

    private function normalizeAlphaNumeric(mixed $value): ?string
    {
        $string = $this->normalizeString($value);
        if ($string === null) {
            return null;
        }

        $clean = preg_replace('/[^A-Za-z0-9]/', '', $string) ?? '';

        return $clean === '' ? null : $clean;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $this->firstFilled($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<mixed>
     */
    private function filterFilled(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->flatten()
            ->filter(fn (mixed $item): bool => $this->hasValue($item))
            ->values()
            ->all();
    }

    private function firstFilled(mixed $value): mixed
    {
        foreach ($this->filterFilled($value) as $item) {
            return $item;
        }

        return null;
    }

    private function maxValue(mixed $value): mixed
    {
        $values = $this->filterFilled($value);

        return $values === [] ? null : max($values);
    }

    private function maxDate(mixed $value): ?string
    {
        $dates = collect($this->filterFilled($value))
            ->map(fn (mixed $item): ?string => $this->normalizeDate($item))
            ->filter()
            ->values()
            ->all();

        return $dates === [] ? null : max($dates);
    }

    private function roundValue(mixed $value, int $precision): ?float
    {
        $float = $this->normalizeFloat($value);

        return $float === null ? null : round($float, $precision);
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
            return $this->filterFilled($value) !== [];
        }

        return true;
    }
}
