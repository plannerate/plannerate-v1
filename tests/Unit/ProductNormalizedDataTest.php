<?php

use App\Services\Integrations\Support\ProductNormalizedData;

test('creates normalized product data from mapped payload', function (): void {
    $mapped = [
        'codigo_erp' => 'ABC123',
        'ean' => '7891000315507',
        'name' => 'Produto X',
        'current_stock' => 12.5,
        'last_purchase_date' => '2026-05-10',
    ];

    $normalized = ProductNormalizedData::fromMapped($mapped, ['raw' => 'ok']);

    expect($normalized)->not->toBeNull()
        ->and($normalized?->codigoErp)->toBe('ABC123')
        ->and($normalized?->ean)->toBe('7891000315507')
        ->and($normalized?->name)->toBe('Produto X')
        ->and($normalized?->currentStock)->toBe(12.5);
});

test('returns null when required product keys are missing', function (): void {
    $missingCode = ProductNormalizedData::fromMapped([
        'codigo_erp' => '',
        'ean' => '7891000315507',
    ], []);

    $missingEan = ProductNormalizedData::fromMapped([
        'codigo_erp' => 'PRD001',
        'ean' => '',
    ], []);

    expect($missingCode)->toBeNull()
        ->and($missingEan)->toBeNull();
});

test('returns null when provider-required string fields are missing', function (): void {
    $normalized = ProductNormalizedData::fromMapped([
        'codigo_erp' => 'PRD001',
        'ean' => '7891000315507',
        'name' => '   ',
    ], [], ['name']);

    expect($normalized)->toBeNull();
});
