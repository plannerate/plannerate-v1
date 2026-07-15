<?php

use App\Services\Integrations\TenantNaturalKeyReconciler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * A conexão tenant é um sqlite :memory: próprio, recriado a cada teste — as migrations
 * rodadas pelo RefreshDatabase não sobrevivem. Recriamos a tabela products por teste.
 */
beforeEach(function (): void {
    if (DB::connection('tenant')->getSchemaBuilder()->hasTable('products')) {
        return;
    }

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations/2026_04_22_200100_create_products_table.php',
        '--realpath' => false,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function insertReconcilerProduct(string $tenantId, string $id, string $ean, array $overrides = []): void
{
    DB::connection('tenant')->table('products')->insert([
        'id' => $id,
        'tenant_id' => $tenantId,
        'name' => 'Produto '.$ean,
        'slug' => 'produto-'.$ean,
        'ean' => $ean,
        'codigo_erp' => 'ERP-'.$ean,
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

/**
 * @return array<string, mixed>
 */
function reconcilerRecord(string $tenantId, string $id, string $ean, string $codigoErp): array
{
    return [
        'id' => $id,
        'tenant_id' => $tenantId,
        'ean' => $ean,
        'codigo_erp' => $codigoErp,
        'name' => 'Produto '.$codigoErp,
    ];
}

test('reusa o id do produto existente quando o EAN já pertence a outro id', function (): void {
    $tenantId = (string) str()->ulid();
    $existingId = (string) str()->ulid();

    insertReconcilerProduct($tenantId, $existingId, '7891035017001');

    $records = [reconcilerRecord($tenantId, (string) str()->ulid(), '7891035017001', 'ERP-999')];

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    expect($reconciled)->toHaveCount(1)
        ->and($reconciled[0]['id'])->toBe($existingId)
        ->and($reconciled[0]['codigo_erp'])->toBe('ERP-999');
});

test('mantém o id determinístico quando o EAN ainda não existe no tenant', function (): void {
    $tenantId = (string) str()->ulid();
    $newId = (string) str()->ulid();

    $records = [reconcilerRecord($tenantId, $newId, '7891035017002', 'ERP-1')];

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    expect($reconciled)->toHaveCount(1)
        ->and($reconciled[0]['id'])->toBe($newId);
});

test('não reaproveita o id de um produto de outro tenant', function (): void {
    $tenantId = (string) str()->ulid();
    $otherTenantId = (string) str()->ulid();
    $newId = (string) str()->ulid();

    insertReconcilerProduct($otherTenantId, (string) str()->ulid(), '7891035017003');

    $records = [reconcilerRecord($tenantId, $newId, '7891035017003', 'ERP-1')];

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    expect($reconciled[0]['id'])->toBe($newId);
});

test('reusa e restaura o produto soft-deleted quando o EAN volta no feed', function (): void {
    $tenantId = (string) str()->ulid();
    $deletedId = (string) str()->ulid();
    $newId = (string) str()->ulid();

    insertReconcilerProduct($tenantId, $deletedId, '7891035017004', ['deleted_at' => now()]);

    $records = [reconcilerRecord($tenantId, $newId, '7891035017004', 'ERP-1')];

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    // Reaproveita a linha apagada (id realinhado) em vez de bifurcar num id novo.
    expect($reconciled[0]['id'])->toBe($deletedId);

    // E a linha foi restaurada (deleted_at limpo), pronta para o upsert atualizá-la.
    expect(DB::connection('tenant')->table('products')->where('id', $deletedId)->value('deleted_at'))
        ->toBeNull();
});

test('restaura a linha apagada mesmo quando o id determinístico já é o dela', function (): void {
    $tenantId = (string) str()->ulid();
    $sameId = (string) str()->ulid();

    insertReconcilerProduct($tenantId, $sameId, '7891035017040', ['deleted_at' => now()]);

    // Mesmo codigo_erp/id determinístico: não há remap, mas ainda precisa restaurar.
    $records = [reconcilerRecord($tenantId, $sameId, '7891035017040', 'ERP-7891035017040')];

    TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    expect(DB::connection('tenant')->table('products')->where('id', $sameId)->value('deleted_at'))
        ->toBeNull();
});

test('prioriza a linha ativa e não restaura a apagada quando ambas existem para o mesmo EAN', function (): void {
    $tenantId = (string) str()->ulid();
    $activeId = (string) str()->ulid();
    $deletedId = (string) str()->ulid();
    $newId = (string) str()->ulid();

    // Em produção o índice de EAN é parcial (WHERE deleted_at IS NULL), o que permite uma
    // linha ativa e uma apagada com o mesmo EAN. A migration de teste cria unique cheio;
    // convertemos para parcial só aqui para reproduzir esse estado.
    DB::connection('tenant')->statement('DROP INDEX IF EXISTS products_tenant_id_ean_unique');
    DB::connection('tenant')->statement('CREATE UNIQUE INDEX products_tenant_id_ean_unique ON products (tenant_id, ean) WHERE deleted_at IS NULL');

    insertReconcilerProduct($tenantId, $activeId, '7891035017041', ['slug' => 'produto-ativo-41']);
    insertReconcilerProduct($tenantId, $deletedId, '7891035017041', ['slug' => 'produto-apagado-41', 'deleted_at' => now()]);

    $records = [reconcilerRecord($tenantId, $newId, '7891035017041', 'ERP-1')];

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    // Realinhado para o ativo — restaurar o apagado colidiria com o índice único parcial.
    expect($reconciled[0]['id'])->toBe($activeId);
    expect(DB::connection('tenant')->table('products')->where('id', $deletedId)->value('deleted_at'))
        ->not->toBeNull();
});

test('mantém apenas o último registro quando o lote traz dois codigo_erp com o mesmo EAN', function (): void {
    $tenantId = (string) str()->ulid();

    $records = [
        reconcilerRecord($tenantId, (string) str()->ulid(), '7891035017005', 'ERP-A'),
        reconcilerRecord($tenantId, (string) str()->ulid(), '7891035017005', 'ERP-B'),
    ];

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    expect($reconciled)->toHaveCount(1)
        ->and($reconciled[0]['codigo_erp'])->toBe('ERP-B');
});

test('preserva registros sem EAN e não consulta a tabela quando nenhum registro tem chave natural', function (): void {
    $tenantId = (string) str()->ulid();
    $firstId = (string) str()->ulid();
    $secondId = (string) str()->ulid();

    $records = [
        [...reconcilerRecord($tenantId, $firstId, '', 'ERP-A'), 'ean' => null],
        [...reconcilerRecord($tenantId, $secondId, '', 'ERP-B'), 'ean' => ''],
    ];

    DB::connection('tenant')->enableQueryLog();

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    expect(DB::connection('tenant')->getQueryLog())->toBeEmpty()
        ->and($reconciled)->toHaveCount(2)
        ->and(array_column($reconciled, 'id'))->toBe([$firstId, $secondId]);

    DB::connection('tenant')->disableQueryLog();
});

test('faz uma única consulta por lote, independente do número de registros', function (): void {
    $tenantId = (string) str()->ulid();
    $existingId = (string) str()->ulid();

    insertReconcilerProduct($tenantId, $existingId, '7891035010001');

    $records = [];

    foreach (range(1, 200) as $i) {
        $ean = sprintf('789103501%04d', $i);
        $records[] = reconcilerRecord($tenantId, (string) str()->ulid(), $ean, "ERP-{$i}");
    }

    DB::connection('tenant')->enableQueryLog();

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'products', $records);

    expect(DB::connection('tenant')->getQueryLog())->toHaveCount(1)
        ->and($reconciled)->toHaveCount(200)
        ->and($reconciled[0]['id'])->toBe($existingId);

    DB::connection('tenant')->disableQueryLog();
});

test('tabelas sem chave natural configurada passam intactas', function (): void {
    $tenantId = (string) str()->ulid();
    $id = (string) str()->ulid();

    $records = [['id' => $id, 'tenant_id' => $tenantId, 'codigo_erp' => 'ERP-1']];

    DB::connection('tenant')->enableQueryLog();

    $reconciled = TenantNaturalKeyReconciler::reconcile(DB::connection('tenant'), 'sales', $records);

    expect(DB::connection('tenant')->getQueryLog())->toBeEmpty()
        ->and($reconciled)->toBe($records);

    DB::connection('tenant')->disableQueryLog();
});
