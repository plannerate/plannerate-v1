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
            'float' => $this->normalizeFloat($value),
            'ean' => $this->normalizeEan($value),
            'alnum' => $this->normalizeAlphaNumeric($value),
            'date' => $this->normalizeDate($value),
            default => $value,
        };
    }

    private function normalizeString(mixed $value): ?string
    {
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
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
