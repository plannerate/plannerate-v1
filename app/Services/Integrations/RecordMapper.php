<?php

namespace App\Services\Integrations;

/**
 * Maps a single API response item to a target record array using field_map config.
 */
class RecordMapper
{
    public function __construct(private readonly FieldValueResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, array{target: string, source: string, transforms?: array<int, string>}>  $fieldMap
     * @param  string|null  $storeId  ID da loja no tenant DB
     * @param  array<int, array{type: string, sources: array<int, string>, allowed_values?: array<int, string>}>  $validations
     * @return array<string, mixed>|null
     */
    public function map(array $item, array $fieldMap, ?string $storeId = null, array $validations = []): ?array
    {
        return $this->mapWithRejectionReason($item, $fieldMap, $storeId, $validations)[0];
    }

    /**
     * Returns the mapped record and, when null, the field that caused rejection.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, array{target: string, source: string, transforms?: array<int, string>, allowed_values?: array<int, string>}>  $fieldMap
     * @param  string|null  $storeId  ID da loja no tenant DB
     * @param  array<int, array{type: string, sources: array<int, string>, allowed_values?: array<int, string>}>  $validations
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    public function mapWithRejectionReason(array $item, array $fieldMap, ?string $storeId = null, array $validations = []): array
    {
        foreach ($validations as $validation) {
            if (! $this->passesGroupValidation($item, $validation)) {
                return [null, null];
            }
        }

        $record = [];

        foreach ($fieldMap as $mapping) {
            $target = (string) ($mapping['target'] ?? '');
            $source = (string) ($mapping['source'] ?? '');
            $transforms = (array) ($mapping['transforms'] ?? []);

            if ($target === '' || $source === '') {
                continue;
            }

            $value = $this->resolver->resolve($item, $source, $transforms);

            if ($this->isRequiredAndMissing($transforms, $value)) {
                return [null, $target];
            }

            if (array_key_exists('allowed_values', $mapping) && $this->isValueNotAllowed($mapping['allowed_values'], $value)) {
                return [null, $target];
            }

            $record[$target] = $value;
        }

        if ($storeId !== null) {
            $record['store_id'] = $storeId;
        }

        return [$record, null];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{type: string, sources: array<int, string>, allowed_values?: array<int, string>}  $validation
     */
    private function passesGroupValidation(array $item, array $validation): bool
    {
        $type = (string) ($validation['type'] ?? '');
        $sources = array_values(array_filter(
            (array) ($validation['sources'] ?? []),
            static fn (mixed $s): bool => is_string($s) && $s !== '',
        ));

        $allowedValues = array_values(array_filter(
            (array) ($validation['allowed_values'] ?? ['S']),
            static fn (mixed $v): bool => is_string($v) && trim($v) !== '',
        ));

        if ($sources === []) {
            return true;
        }

        return match ($type) {
            'any_of' => $this->anyOfPasses($item, $sources, $allowedValues),
            'all_of' => $this->allOfPasses($item, $sources, $allowedValues),
            default => true,
        };
    }

    /**
     * Returns true if every source field value is in $allowedValues.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $allowedValues
     */
    private function allOfPasses(array $item, array $sources, array $allowedValues): bool
    {
        foreach ($sources as $source) {
            $value = (string) data_get($item, $source, '');

            if (! in_array($value, $allowedValues, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if at least one source field value is in $allowedValues.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $allowedValues
     */
    private function anyOfPasses(array $item, array $sources, array $allowedValues): bool
    {
        foreach ($sources as $source) {
            $value = (string) data_get($item, $source, '');

            if (in_array($value, $allowedValues, true)) {
                return true;
            }
        }

        return false;
    }

    private function isValueNotAllowed(mixed $allowedValuesConfig, mixed $value): bool
    {
        $allowedValues = array_values(array_filter(
            (array) $allowedValuesConfig,
            static fn (mixed $v): bool => is_string($v) && trim($v) !== '',
        ));

        if ($allowedValues === []) {
            return $value === 'N';
        }

        return ! in_array($value, $allowedValues, true);
    }

    /**
     * @param  array<int, string>  $transforms
     */
    private function isRequiredAndMissing(array $transforms, mixed $value): bool
    {
        if (! in_array('not_null', $transforms, true)) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
