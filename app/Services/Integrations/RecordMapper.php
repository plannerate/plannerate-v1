<?php

namespace App\Services\Integrations;

/**
 * Maps a single API response item to a target record array using field_map config.
 */
class RecordMapper
{
    public function __construct(private readonly FieldValueResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $item  Raw item from API response
     * @param  array<int, array{target: string, source: string, transforms?: array<int, string>}>  $fieldMap
     * @param  string|null  $storeDocument  CNPJ appended when include_store_in_id is set
     * @return array<string, mixed>
     */
    public function map(array $item, array $fieldMap, ?string $storeDocument = null): array
    {
        $record = [];

        foreach ($fieldMap as $mapping) {
            $target = (string) ($mapping['target'] ?? '');
            $source = (string) ($mapping['source'] ?? '');
            $transforms = (array) ($mapping['transforms'] ?? []);

            if ($target === '' || $source === '') {
                continue;
            }

            $record[$target] = $this->resolver->resolve($item, $source, $transforms);
        }

        if ($storeDocument !== null) {
            $record['store_document'] = $storeDocument;
        }

        return $record;
    }
}
