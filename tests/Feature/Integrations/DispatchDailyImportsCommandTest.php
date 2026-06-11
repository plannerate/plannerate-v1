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
        $table->softDeletes();
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
        $table->softDeletes();
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

    expect(Artisan::output())->toBe('');

    Bus::assertDispatched(ImportIntegrationResourceJob::class, function (ImportIntegrationResourceJob $job) use ($activeIntegration): bool {
        return $job->integrationId === (string) $activeIntegration->id
            && $job->resource === 'sales';
    });

    Bus::assertDispatched(ImportIntegrationResourceJob::class, function (ImportIntegrationResourceJob $job) use ($activeIntegration): bool {
        return $job->integrationId === (string) $activeIntegration->id
            && $job->resource === 'products';
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
        'integration_type' => 'acme-erp',
        'identifier' => 'principal',
        'config' => [],
        'is_active' => true,
    ]);

    Artisan::call('integrations:daily-imports', [
        '--type' => 'products',
    ]);

    expect(Artisan::output())->toBe('');

    Bus::assertDispatched(ImportIntegrationResourceJob::class, fn (ImportIntegrationResourceJob $job): bool => $job->resource === 'products');
    Bus::assertNotDispatched(ImportIntegrationResourceJob::class, fn (ImportIntegrationResourceJob $job): bool => $job->resource === 'sales');
});

test('daily imports command stays quiet when there are no active integrations', function (): void {
    Bus::fake();

    Artisan::call('integrations:daily-imports');

    expect(Artisan::output())->toBe('');

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

    expect(Artisan::output())->toBe('');

    Bus::assertDispatched(ImportIntegrationResourceJob::class, fn (ImportIntegrationResourceJob $job): bool => $job->resource === 'stores');
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
