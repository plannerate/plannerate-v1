<?php

use App\Services\Integrations\FieldValueResolver;
use App\Services\Integrations\RecordMapper;

// ─── any_of validations ──────────────────────────────────────────────────────

it('rejects record when none of the any_of sources has an allowed value', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['cadastro_ativo' => 'N', 'pertence_ao_mix' => 'N', 'ativo_erp' => 'N', 'descricao' => 'Produto'],
        [['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']]],
        null,
        [['type' => 'any_of', 'sources' => ['cadastro_ativo', 'pertence_ao_mix', 'ativo_erp'], 'allowed_values' => ['S']]],
    );

    expect($record)->toBeNull();
});

it('keeps record when at least one any_of source has an allowed value', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['cadastro_ativo' => 'N', 'pertence_ao_mix' => 'S', 'ativo_erp' => 'N', 'descricao' => 'Produto'],
        [['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']]],
        null,
        [['type' => 'any_of', 'sources' => ['cadastro_ativo', 'pertence_ao_mix', 'ativo_erp'], 'allowed_values' => ['S']]],
    );

    expect($record)->toBe(['name' => 'Produto']);
});

it('defaults allowed_values to ["S"] when omitted from any_of validation', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['cadastro_ativo' => 'S', 'descricao' => 'Produto'],
        [['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']]],
        null,
        [['type' => 'any_of', 'sources' => ['cadastro_ativo']]],
    );

    expect($record)->toBe(['name' => 'Produto']);
});

it('skips group validation when sources list is empty', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['descricao' => 'Produto'],
        [['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']]],
        null,
        [['type' => 'any_of', 'sources' => [], 'allowed_values' => ['S']]],
    );

    expect($record)->toBe(['name' => 'Produto']);
});

// ─── not_null ────────────────────────────────────────────────────────────────

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

it('rejects record when field value is "N" and allowed_values is empty', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['cadastro_ativo' => 'N', 'descricao' => 'Produto inativo'],
        [
            ['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']],
            ['target' => 'sales_status', 'source' => 'cadastro_ativo', 'transforms' => ['string'], 'allowed_values' => []],
        ],
    );

    expect($record)->toBeNull();
});

it('keeps record when field value is not "N" and allowed_values is empty', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['cadastro_ativo' => 'S', 'descricao' => 'Produto ativo'],
        [
            ['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']],
            ['target' => 'sales_status', 'source' => 'cadastro_ativo', 'transforms' => ['string'], 'allowed_values' => []],
        ],
    );

    expect($record)->toBe(['name' => 'Produto ativo', 'sales_status' => 'S']);
});

it('rejects record when field value is not in allowed_values list', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['cadastro_ativo' => 'N', 'descricao' => 'Produto'],
        [
            ['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']],
            ['target' => 'sales_status', 'source' => 'cadastro_ativo', 'transforms' => ['string'], 'allowed_values' => ['S', 'A']],
        ],
    );

    expect($record)->toBeNull();
});

it('keeps record when field value is in allowed_values list', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['cadastro_ativo' => 'S', 'descricao' => 'Produto'],
        [
            ['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']],
            ['target' => 'sales_status', 'source' => 'cadastro_ativo', 'transforms' => ['string'], 'allowed_values' => ['S', 'A']],
        ],
    );

    expect($record)->toBe(['name' => 'Produto', 'sales_status' => 'S']);
});

it('does not validate when allowed_values key is absent', function (): void {
    $mapper = new RecordMapper(new FieldValueResolver);

    $record = $mapper->map(
        ['cadastro_ativo' => 'N', 'descricao' => 'Produto'],
        [
            ['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']],
            ['target' => 'sales_status', 'source' => 'cadastro_ativo', 'transforms' => ['string']],
        ],
    );

    expect($record)->toBe(['name' => 'Produto', 'sales_status' => 'N']);
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
