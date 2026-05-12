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
     * @return array<string, mixed>|null
     */
    public function map(array $item, array $fieldMap, ?string $storeId = null): ?array
    {
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
                return null;
            }

            $record[$target] = $value;
        }

        if ($storeId !== null) {
            $record['store_id'] = $storeId;
        }

        return $record;
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
