<?php

use App\Models\IntegrationApi;
use App\Models\IntegrationImportRun;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ImportRunReconciler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Isolation-safe (padrão do E2E): switch_tenant_tasks=[] → $tenant->execute()
 * passthrough; migra runs (landlord) + sales (tenant). Nunca toca no banco real.
 */
beforeEach(function (): void {
    config(['multitenancy.switch_tenant_tasks' => []]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    if (! DB::connection('tenant')->getSchemaBuilder()->hasTable('sales')) {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/2026_04_23_250000_create_sales_table.php',
            '--realpath' => false,
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }
});

function makeRunReconcilerIntegration(): TenantIntegration
{
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'RECONCILER',
        'slug' => 'reconciler-'.Str::lower(Str::random(6)),
        'database' => (string) config('database.connections.landlord.database').'_rec',
        'status' => 'active',
    ]));

    $api = IntegrationApi::query()->create([
        'name' => 'REC API',
        'slug' => 'rec-api-'.Str::lower(Str::random(6)),
        'requests' => ['paths' => ['sales' => ['target_table' => 'sales', 'field_map' => []]]],
        'response' => ['items_path' => 'dados'],
        'is_active' => true,
    ]);

    return TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => [],
        'is_active' => true,
    ]);
}

function insertRunReconcilerSale(string $tenantId, ?string $storeId, string $saleDate): void
{
    DB::connection('tenant')->table('sales')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenantId,
        'store_id' => $storeId,
        'ean' => '789',
        'codigo_erp' => 'E1',
        'sale_date' => $saleDate,
        'promotion' => 'N',
        'total_sale_value' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('daily: marca complete quando todos os dias esperados têm dado', function (): void {
    $integration = makeRunReconcilerIntegration();
    $tenantId = (string) $integration->tenant_id;
    $days = [now()->toDateString(), now()->subDay()->toDateString(), now()->subDays(2)->toDateString()];

    IntegrationImportRun::startRun([
        'tenant_id' => $tenantId, 'integration_id' => (string) $integration->id, 'path_key' => 'sales',
        'store_id' => null, 'mode' => 'daily', 'reference_date' => now()->toDateString(),
        'expected_units' => 3, 'expected_dates' => $days,
    ]);

    foreach ($days as $d) {
        insertRunReconcilerSale($tenantId, null, $d);
    }

    $summary = ImportRunReconciler::reconcileForDate(now()->toDateString());
    $run = IntegrationImportRun::query()->where('tenant_id', $tenantId)->first();

    expect($summary)->toMatchArray(['reconciled' => 1, 'complete' => 1, 'partial' => 0])
        ->and($run->status)->toBe('complete')
        ->and($run->covered_units)->toBe(3)
        ->and($run->reconciled_at)->not->toBeNull();
});

test('daily: marca partial quando falta dado de um dia esperado', function (): void {
    $integration = makeRunReconcilerIntegration();
    $tenantId = (string) $integration->tenant_id;
    $days = [now()->toDateString(), now()->subDay()->toDateString(), now()->subDays(2)->toDateString()];

    IntegrationImportRun::startRun([
        'tenant_id' => $tenantId, 'integration_id' => (string) $integration->id, 'path_key' => 'sales',
        'store_id' => null, 'mode' => 'daily', 'reference_date' => now()->toDateString(),
        'expected_units' => 3, 'expected_dates' => $days,
    ]);

    // Só 2 dos 3 dias têm venda
    insertRunReconcilerSale($tenantId, null, $days[0]);
    insertRunReconcilerSale($tenantId, null, $days[1]);

    ImportRunReconciler::reconcileForDate(now()->toDateString());
    $run = IntegrationImportRun::query()->where('tenant_id', $tenantId)->first();

    expect($run->status)->toBe('partial')
        ->and($run->covered_units)->toBe(2);
});

test('page: complete se persistiu algo, partial se não', function (): void {
    $integration = makeRunReconcilerIntegration();
    $tenantId = (string) $integration->tenant_id;

    $withData = IntegrationImportRun::startRun([
        'tenant_id' => $tenantId, 'integration_id' => (string) $integration->id, 'path_key' => 'products',
        'store_id' => null, 'mode' => 'page', 'reference_date' => now()->toDateString(),
        'expected_units' => 5, 'expected_dates' => null,
    ]);
    IntegrationImportRun::recordPersisted($withData->id, 4200);

    IntegrationImportRun::startRun([
        'tenant_id' => $tenantId, 'integration_id' => (string) $integration->id, 'path_key' => 'products',
        'store_id' => (string) Str::ulid(), 'mode' => 'page', 'reference_date' => now()->toDateString(),
        'expected_units' => 3, 'expected_dates' => null,
    ]); // persisted = 0

    ImportRunReconciler::reconcileForDate(now()->toDateString());

    $good = IntegrationImportRun::query()->where('id', $withData->id)->first();
    $empty = IntegrationImportRun::query()->where('store_id', '!=', null)->first();

    expect($good->status)->toBe('complete')
        ->and($good->covered_units)->toBe(5)
        ->and($empty->status)->toBe('partial')
        ->and($empty->covered_units)->toBe(0);
});
