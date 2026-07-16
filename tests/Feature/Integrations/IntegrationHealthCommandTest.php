<?php

use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * Isolation-safe (mesmo padrão do ImportPipelineEndToEndTest): switch_tenant_tasks=[]
 * torna $tenant->execute() passthrough; migrations reais de products/sales na
 * conexão tenant; nunca troca database.default nem DB::purge.
 */
beforeEach(function (): void {
    config(['multitenancy.switch_tenant_tasks' => []]);
    Storage::fake('local');

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    foreach ([
        '2026_04_22_200100_create_products_table.php',
        '2026_04_23_250000_create_sales_table.php',
    ] as $migration) {
        $table = str_contains($migration, 'products') ? 'products' : 'sales';
        if (! DB::connection('tenant')->getSchemaBuilder()->hasTable($table)) {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => "database/migrations/{$migration}",
                '--realpath' => false,
                '--force' => true,
                '--no-interaction' => true,
            ]);
        }
    }
});

function makeHealthIntegration(string $slug): TenantIntegration
{
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'database' => (string) config('database.connections.landlord.database').'_'.$slug,
        'status' => 'active',
    ]));

    $api = IntegrationApi::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'requests' => [
            'paths' => [
                'products' => ['target_table' => 'products', 'field_map' => []],
                'sales' => ['target_table' => 'sales', 'field_map' => []],
            ],
        ],
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

function insertHealthSale(string $tenantId, string $saleDate): void
{
    DB::connection('tenant')->table('sales')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenantId,
        'ean' => '7890000000001',
        'codigo_erp' => 'ERP-1',
        'sale_date' => $saleDate,
        'promotion' => 'N',
        'total_sale_value' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('venda recente → saudável (exit SUCCESS)', function (): void {
    $integration = makeHealthIntegration('health-fresh');
    insertHealthSale((string) $integration->tenant_id, now()->toDateString());

    $exit = Artisan::call('integration:health', ['--tenant' => (string) $integration->tenant_id]);

    expect($exit)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('Tudo saudável');
});

test('sem venda recente → atrasado (exit FAILURE)', function (): void {
    $integration = makeHealthIntegration('health-stale');
    insertHealthSale((string) $integration->tenant_id, now()->subDays(10)->toDateString());

    $exit = Artisan::call('integration:health', ['--tenant' => (string) $integration->tenant_id]);

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('atrasado');
});

test('--json emite estrutura e alerta booleano', function (): void {
    $integration = makeHealthIntegration('health-json');
    insertHealthSale((string) $integration->tenant_id, now()->toDateString());

    Artisan::call('integration:health', ['--tenant' => (string) $integration->tenant_id, '--json' => true]);
    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toBeArray()
        ->and($payload['alert'])->toBeFalse()
        ->and($payload['global']['queue_total'])->toBe(0)
        ->and($payload['integrations'])->toHaveCount(2); // products + sales
});
