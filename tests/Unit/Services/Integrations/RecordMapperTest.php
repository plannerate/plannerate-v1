<?php

use App\Services\Integrations\FieldValueResolver;
use App\Services\Integrations\RecordMapper;

it('returns null when a not_null mapped field resolves to null', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        [
            'descricao' => 'Produto sem gtin',
            'gtins' => ['completo' => []],
        ],
        [
            [
                'target' => 'name',
                'source' => 'descricao',
                'transforms' => ['string'],
            ],
            [
                'target' => 'ean',
                'source' => 'gtins.completo[principal=S].gtin',
                'transforms' => ['first', 'ean', 'not_null'],
            ],
        ],
        'store-1',
    );

    expect($record)->toBeNull();
});

it('keeps mapping when not_null field is present and valid', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        [
            'descricao' => 'Produto com gtin',
            'gtins' => [
                'completo' => [
                    ['principal' => 'S', 'gtin' => '7891234567890'],
                ],
            ],
        ],
        [
            [
                'target' => 'name',
                'source' => 'descricao',
                'transforms' => ['string'],
            ],
            [
                'target' => 'ean',
                'source' => 'gtins.completo[principal=S].gtin',
                'transforms' => ['first', 'ean', 'not_null'],
            ],
        ],
        'store-1',
    );

    expect($record)->toBe([
        'name' => 'Produto com gtin',
        'ean' => '7891234567890',
        'store_id' => 'store-1',
    ]);
});
