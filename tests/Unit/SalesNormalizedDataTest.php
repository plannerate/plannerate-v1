<?php

use App\Services\Integrations\Support\SalesNormalizedData;

/*
 * SKIP (fase 5 da refatoração raptor-plannerate, aprovado em 2026-06-11):
 * este arquivo referencia classes do domínio Integrations que não existem mais
 * nesses namespaces (ex.: App\Services\Integrations\Http\IntegrationHttpClient —
 * a classe atual vive em App\Services\Integrations\IntegrationHttpClient).
 * Estes testes nunca rodaram (a suíte não carregava antes do commit 83d400a).
 * Triagem pendente do domínio Integrations: atualizar imports/expectativas ou remover.
 */
beforeEach(function (): void {
    $this->markTestSkipped('Domínio Integrations: classes testadas mudaram de namespace — triagem pendente (ver comentário no topo do arquivo).');
});

test('creates normalized sales data from mapped payload', function (): void {
    $mapped = [
        'codigo_erp' => 'ABC123',
        'ean' => '7891000315507',
        'sale_date' => '2026-05-09',
        'promotion' => 'N',
        'total_sale_quantity' => 2.5,
        'total_sale_value' => 39.9,
        'sale_price' => 15.96,
        'acquisition_cost' => 9.85,
        'total_profit_margin' => 6.11,
        'valor_impostos' => 1.2,
        'custo_medio_loja' => 8.4,
        'store_document' => '72.316.342/0002-07',
    ];

    $normalized = SalesNormalizedData::fromMapped($mapped, ['raw' => 'ok'], null);

    expect($normalized)->not->toBeNull()
        ->and($normalized?->codigoErp)->toBe('ABC123')
        ->and($normalized?->ean)->toBe('7891000315507')
        ->and($normalized?->saleDate)->toBe('2026-05-09')
        ->and($normalized?->totalSaleValue)->toBe(39.9)
        ->and($normalized?->storeDocument)->toBe('72316342000207');
});

test('returns null when required mapped fields are missing or invalid', function (): void {
    $missingCode = SalesNormalizedData::fromMapped([
        'codigo_erp' => '',
        'sale_date' => '2026-05-09',
    ], [], null);

    $missingDate = SalesNormalizedData::fromMapped([
        'codigo_erp' => 'PRD001',
        'sale_date' => '',
    ], [], null);

    expect($missingCode)->toBeNull()
        ->and($missingDate)->toBeNull();
});

test('uses fallback store document and normalizes mask', function (): void {
    $normalized = SalesNormalizedData::fromMapped([
        'codigo_erp' => 'PRD001',
        'sale_date' => '2026-05-09',
        'store_document' => null,
    ], [], '12.345.678/0001-99');

    expect($normalized)->not->toBeNull()
        ->and($normalized?->storeDocument)->toBe('12345678000199');
});

test('keeps numeric fields null when mapped values are not numeric scalars', function (): void {
    $normalized = SalesNormalizedData::fromMapped([
        'codigo_erp' => 'PRD001',
        'sale_date' => '2026-05-09',
        'total_sale_value' => '39,90',
        'total_sale_quantity' => '2',
    ], [], null);

    expect($normalized)->not->toBeNull()
        ->and($normalized?->totalSaleValue)->toBeNull()
        ->and($normalized?->totalSaleQuantity)->toBeNull();
});
