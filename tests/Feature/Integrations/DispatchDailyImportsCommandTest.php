<?php

use App\Jobs\Integrations\Imports\ImportProductsJob;
use App\Jobs\Integrations\Imports\ImportSalesJob;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

test('daily imports command dispatches sales and products jobs for active integrations', function (): void {
    Bus::fake();

    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
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
        ->toContain('Integrações ativas encontradas para importação diária: 1')
        ->toContain('Tenant Ativo')
        ->toContain('principal')
        ->toContain('sales')
        ->toContain('products')
        ->toContain('provider_adapter')
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

test('daily imports command can dispatch only sales', function (): void {
    Bus::fake();

    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Vendas',
        'slug' => 'tenant-vendas',
        'database' => 'tenant_sales',
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'acme-erp',
        'identifier' => 'vendas',
        'config' => [],
        'is_active' => true,
    ]);

    Artisan::call('integrations:daily-imports', ['--type' => 'sales']);

    Bus::assertDispatched(ImportSalesJob::class);
    Bus::assertNotDispatched(ImportProductsJob::class);
});
