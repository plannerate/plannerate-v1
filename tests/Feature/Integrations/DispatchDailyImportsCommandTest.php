<?php

use App\Jobs\Integrations\Imports\ImportIntegrationResourceJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('database.connections.landlord', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('landlord');

    Schema::connection('landlord')->dropIfExists('tenant_integrations');
    Schema::connection('landlord')->dropIfExists('integration_apis');
    Schema::connection('landlord')->dropIfExists('tenants');

    Schema::connection('landlord')->create('tenants', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('database')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
    });

    Schema::connection('landlord')->create('integration_apis', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->json('requests')->nullable();
        $table->json('response')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::connection('landlord')->create('tenant_integrations', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id');
        $table->string('integration_type');
        $table->string('identifier')->nullable();
        $table->json('config')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_sync')->nullable();
        $table->timestamps();
    });
});

test('daily imports command dispatches enabled paths for active integrations', function (): void {
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
        ->toContain('ImportIntegrationResourceJob')
        ->not->toContain('Tenant Inativo')
        ->not->toContain('desativada');

    Bus::assertDispatched(ImportIntegrationResourceJob::class, function (ImportIntegrationResourceJob $job) use ($activeIntegration): bool {
        return $job->integrationId === (string) $activeIntegration->id
            && $job->resource === 'sales'
            && $job->targetTable === 'sales';
    });

    Bus::assertDispatched(ImportIntegrationResourceJob::class, function (ImportIntegrationResourceJob $job) use ($activeIntegration): bool {
        return $job->integrationId === (string) $activeIntegration->id
            && $job->resource === 'products'
            && $job->targetTable === 'products';
    });
});

test('daily imports command filters dispatches by path type when provided', function (): void {
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

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Ativo',
        'slug' => 'tenant-ativo',
        'database' => 'tenant_active',
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'identifier' => 'principal',
        'config' => [],
        'is_active' => true,
    ]);

    Artisan::call('integrations:daily-imports', [
        '--type' => 'products',
    ]);

    $output = Artisan::output();

    expect($output)
        ->toContain('[Passo 03] Paths despacháveis encontrados: 1.')
        ->toContain('products')
        ->not->toContain('sales');

    Bus::assertDispatched(ImportIntegrationResourceJob::class, fn (ImportIntegrationResourceJob $job): bool => $job->resource === 'products');
    Bus::assertNotDispatched(ImportIntegrationResourceJob::class, fn (ImportIntegrationResourceJob $job): bool => $job->resource === 'sales');
});

test('daily imports command warns when there are no active integrations', function (): void {
    Bus::fake();

    Artisan::call('integrations:daily-imports');

    expect(Artisan::output())->toContain('Nenhuma integração ativa encontrada para a busca diária.');

    Bus::assertNothingDispatched();
});

test('daily imports command dispatches generic configured paths and skips disabled paths', function (): void {
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
                    'enabled' => false,
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
        ->toContain('[Passo 03] Paths despacháveis encontrados: 1.')
        ->toContain('stores')
        ->not->toContain('products');

    Bus::assertDispatched(ImportIntegrationResourceJob::class, fn (ImportIntegrationResourceJob $job): bool => $job->resource === 'stores'
        && $job->targetTable === 'stores');
    Bus::assertNotDispatched(ImportIntegrationResourceJob::class, fn (ImportIntegrationResourceJob $job): bool => $job->resource === 'products');
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
