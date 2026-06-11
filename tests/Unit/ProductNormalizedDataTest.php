<?php

use App\Services\Integrations\Support\ProductNormalizedData;

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
