<?php

use App\Jobs\Integrations\Imports\ImportProductsJob;
use App\Jobs\Integrations\Imports\ImportSalesJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('daily imports command dispatches sales and products jobs for active integrations', function (): void {
    Bus::fake();

    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);

    IntegrationApi::query()->create([
        'name' => 'Acme ERP',
        'slug' => 'acme-erp',
        'requests' => integrationApiRequests(),
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    $activeTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Ativo',
        'slug' => 'tenant-ativo',
        'database' => 'tenant_active',
        'status' => 'active',
    ]));

    $inactiveTenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Inativo',
        'slug' => 'tenant-inativo',
        'database' => 'tenant_inactive',
        'status' => 'active',
    ]));

    $activeIntegration = TenantIntegration::query()->create([
        'tenant_id' => $activeTenant->id,
        'integration_type' => 'acme-erp',
        'identifier' => 'principal',
        'config' => [],
        'is_active' => true,
    ]);

    TenantIntegration::query()->create([
        'tenant_id' => $inactiveTenant->id,
        'integration_type' => 'acme-erp',
        'identifier' => 'desativada',
        'config' => [],
        'is_active' => false,
    ]);

    Artisan::call('integrations:daily-imports');

    $output = Artisan::output();

    expect($output)
        ->toContain('[Passo 01] Iniciando importações diárias usando requests.paths.')
        ->toContain('[Passo 03] Paths despacháveis encontrados: 2.')
        ->toContain('Integrações ativas encontradas para importação diária: 1')
        ->toContain('Tenant Ativo')
        ->toContain('principal')
        ->toContain('sales')
        ->toContain('products')
        ->toContain('ImportSalesJob')
        ->toContain('ImportProductsJob')
        ->not->toContain('Tenant Inativo')
        ->not->toContain('desativada');

    Bus::assertDispatched(ImportSalesJob::class, function (ImportSalesJob $job) use ($activeIntegration): bool {
        return $job->integrationId === (string) $activeIntegration->id;
    });

    Bus::assertDispatched(ImportProductsJob::class, function (ImportProductsJob $job) use ($activeIntegration): bool {
        return $job->integrationId === (string) $activeIntegration->id;
    });
});

test('daily imports command warns when there are no active integrations', function (): void {
    Bus::fake();

    Artisan::call('integrations:daily-imports');

    expect(Artisan::output())->toContain('Nenhuma integração ativa encontrada para a busca diária.');

    Bus::assertNothingDispatched();
});

test('daily imports command dispatches only configured paths with registered jobs', function (): void {
    Bus::fake();

    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);

    IntegrationApi::query()->create([
        'name' => 'Products Only ERP',
        'slug' => 'products-only-erp',
        'requests' => integrationApiRequests([
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/products',
                ],
                'stores' => [
                    'target_table' => 'stores',
                    'fallback_path' => '/stores',
                ],
            ],
        ]),
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Produtos',
        'slug' => 'tenant-produtos',
        'database' => 'tenant_products',
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'products-only-erp',
        'identifier' => 'produtos',
        'config' => [],
        'is_active' => true,
    ]);

    Artisan::call('integrations:daily-imports');

    $output = Artisan::output();

    expect($output)
        ->toContain('Path [stores]')
        ->toContain('[Passo 03] Paths despacháveis encontrados: 1.')
        ->toContain('products');

    Bus::assertDispatched(ImportProductsJob::class);
    Bus::assertNotDispatched(ImportSalesJob::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function integrationApiRequests(array $overrides = []): array
{
    $requests = array_replace_recursive([
        'method' => 'POST',
        'payload' => 'body',
        'paths' => [
            'products' => [
                'target_table' => 'products',
                'fallback_path' => '/products',
            ],
            'sales' => [
                'target_table' => 'sales',
                'fallback_path' => '/sales',
            ],
        ],
    ], $overrides);

    if (array_key_exists('paths', $overrides)) {
        $requests['paths'] = $overrides['paths'];
    }

    return $requests;
}
