<?php

use App\Jobs\Integrations\Imports\ImportIntegrationResourceJob;
use App\Jobs\Integrations\Maintenance\FinalizeTenantImportsJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Importers\IntegrationImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
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

test('imports active enabled resource and dispatches finalize when configured', function (): void {
    Bus::fake();

    IntegrationApi::query()->create([
        'name' => 'Acme ERP',
        'slug' => 'acme-erp',
        'requests' => jobIntegrationApiRequests([
            'paths' => [
                'sales' => [
                    'target_table' => 'sales',
                    'fallback_path' => '/sales',
                    'run_finalize' => true,
                ],
            ],
        ]),
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Ativo',
        'slug' => 'tenant-ativo',
        'database' => 'tenant_active',
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'acme-erp',
        'identifier' => 'principal',
        'config' => [],
        'is_active' => true,
    ]);

    $importer = Mockery::mock(IntegrationImporter::class);
    $importer
        ->shouldReceive('importResource')
        ->once()
        ->withArgs(fn (TenantIntegration $receivedIntegration, string $resource, string $targetTable): bool => $receivedIntegration->is($integration)
            && $resource === 'sales'
            && $targetTable === 'sales');

    app()->instance(IntegrationImporter::class, $importer);

    app()->call([
        new ImportIntegrationResourceJob(
            integrationId: (string) $integration->id,
            resource: 'sales',
            targetTable: 'sales',
        ),
        'handle',
    ]);

    Bus::assertDispatched(FinalizeTenantImportsJob::class, fn (FinalizeTenantImportsJob $job): bool => $job->tenantId === (string) $tenant->id);
});

test('does not import disabled resource', function (): void {
    Bus::fake();

    IntegrationApi::query()->create([
        'name' => 'Acme ERP',
        'slug' => 'acme-erp',
        'requests' => jobIntegrationApiRequests([
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/products',
                    'enabled' => false,
                ],
            ],
        ]),
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Ativo',
        'slug' => 'tenant-ativo',
        'database' => 'tenant_active',
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'acme-erp',
        'identifier' => 'principal',
        'config' => [],
        'is_active' => true,
    ]);

    $importer = Mockery::mock(IntegrationImporter::class);
    $importer->shouldNotReceive('importResource');

    app()->instance(IntegrationImporter::class, $importer);

    app()->call([
        new ImportIntegrationResourceJob(
            integrationId: (string) $integration->id,
            resource: 'products',
            targetTable: 'products',
        ),
        'handle',
    ]);

    Bus::assertNotDispatched(FinalizeTenantImportsJob::class);
});

test('does not import missing integration', function (): void {
    Bus::fake();

    $importer = Mockery::mock(IntegrationImporter::class);
    $importer->shouldNotReceive('importResource');

    app()->instance(IntegrationImporter::class, $importer);

    app()->call([
        new ImportIntegrationResourceJob(
            integrationId: '01jts31n2rpz1tyy4n6xv4qdn0',
            resource: 'products',
            targetTable: 'products',
        ),
        'handle',
    ]);

    Bus::assertNotDispatched(FinalizeTenantImportsJob::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function jobIntegrationApiRequests(array $overrides = []): array
{
    $requests = array_replace_recursive([
        'method' => 'POST',
        'payload' => 'body',
        'paths' => [
            'products' => [
                'target_table' => 'products',
                'fallback_path' => '/products',
            ],
        ],
    ], $overrides);

    if (array_key_exists('paths', $overrides)) {
        $requests['paths'] = $overrides['paths'];
    }

    return $requests;
}
