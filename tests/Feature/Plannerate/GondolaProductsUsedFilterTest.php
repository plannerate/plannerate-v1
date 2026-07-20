<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cenários combinados num único test() porque a conexão `tenant` (sqlite :memory:)
 * não sobrevive ao primeiro teste do arquivo neste ambiente — mesmo motivo
 * documentado em CleanupCommandTest e afins.
 */
test('produto so conta como usado enquanto toda a cadeia ate a section estiver ativa', function (): void {
    $tenantConnection = (string) config('multitenancy.tenant_database_connection_name', 'tenant');
    $tenantId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();
    $gondolaId = (string) Str::ulid();
    $sectionId = (string) Str::ulid();
    $shelfId = (string) Str::ulid();
    $segmentId = (string) Str::ulid();
    $layerId = (string) Str::ulid();
    $productId = (string) Str::ulid();
    $ean = '7891910020065';
    $now = now();

    DB::connection($tenantConnection)->table('planograms')->insert([
        'id' => $planogramId,
        'tenant_id' => $tenantId,
        'name' => 'Planograma Filtro Usados',
        'slug' => 'planograma-filtro-usados',
        'type' => 'planograma',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('gondolas')->insert([
        'id' => $gondolaId,
        'tenant_id' => $tenantId,
        'planogram_id' => $planogramId,
        'name' => 'Gôndola Filtro Usados',
        'slug' => 'gondola-filtro-usados',
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('sections')->insert([
        'id' => $sectionId,
        'tenant_id' => $tenantId,
        'gondola_id' => $gondolaId,
        'name' => 'Seção Filtro',
        'code' => 'SEC-FILTRO',
        'slug' => 'sec-filtro',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('shelves')->insert([
        'id' => $shelfId,
        'tenant_id' => $tenantId,
        'section_id' => $sectionId,
        'code' => 'SHELF-FILTRO',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('products')->insert([
        'id' => $productId,
        'tenant_id' => $tenantId,
        'name' => 'Açúcar Demerara Filtro',
        'slug' => 'acucar-demerara-filtro',
        'ean' => $ean,
        'width' => 12.5,
        'height' => 26.0,
        'depth' => 5.5,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('segments')->insert([
        'id' => $segmentId,
        'tenant_id' => $tenantId,
        'shelf_id' => $shelfId,
        'ordering' => 0,
        'position' => 0,
        'quantity' => 1,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection($tenantConnection)->table('layers')->insert([
        'id' => $layerId,
        'tenant_id' => $tenantId,
        'segment_id' => $segmentId,
        'product_id' => $productId,
        'quantity' => 1,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $fetch = function () use ($gondolaId, $ean): array {
        $request = Request::create('/', 'GET', [
            'search' => $ean,
            'show_used' => false,
            'with_dimensions' => true,
        ]);

        $response = app(GondolaController::class)->products($request, 'planogram-irrelevante', $gondolaId);

        return json_decode($response->getContent(), true);
    };

    $restoreAll = function () use ($tenantConnection, $segmentId, $shelfId, $sectionId): void {
        DB::connection($tenantConnection)->table('segments')->where('id', $segmentId)->update(['deleted_at' => null]);
        DB::connection($tenantConnection)->table('shelves')->where('id', $shelfId)->update(['deleted_at' => null]);
        DB::connection($tenantConnection)->table('sections')->where('id', $sectionId)->update(['deleted_at' => null]);
    };

    // Cadeia inteira ativa: produto está na gôndola, some da lista e conta como usado.
    $payload = $fetch();
    expect($payload['used_count'])->toBe(1)
        ->and($payload['products'])->toBeEmpty();

    // Remover um produto no editor soft-deleta apenas o segment; a layer filha
    // permanece com deleted_at NULL (ver SegmentService::update). O produto deve
    // voltar para a lista de disponíveis mesmo assim.
    DB::connection($tenantConnection)->table('segments')->where('id', $segmentId)->update(['deleted_at' => now()]);

    $payload = $fetch();
    expect($payload['used_count'])->toBe(0)
        ->and($payload['products'])->toHaveCount(1)
        ->and($payload['products'][0]['id'])->toBe($productId);

    // Mesmo comportamento quando o soft delete acontece na shelf ancestral.
    $restoreAll();
    DB::connection($tenantConnection)->table('shelves')->where('id', $shelfId)->update(['deleted_at' => now()]);

    $payload = $fetch();
    expect($payload['used_count'])->toBe(0)
        ->and($payload['products'])->toHaveCount(1);

    // E quando acontece na section ancestral.
    $restoreAll();
    DB::connection($tenantConnection)->table('sections')->where('id', $sectionId)->update(['deleted_at' => now()]);

    $payload = $fetch();
    expect($payload['used_count'])->toBe(0)
        ->and($payload['products'])->toHaveCount(1);

    // Restaurando tudo, o produto volta a ser considerado usado.
    $restoreAll();

    $payload = $fetch();
    expect($payload['used_count'])->toBe(1)
        ->and($payload['products'])->toBeEmpty();
});
