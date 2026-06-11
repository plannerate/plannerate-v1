<?php

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\Importers\GenericIntegrationImporter;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\IntegrationResponseReader;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('multitenancy.tenant_database_connection_name', 'tenant');

    DB::purge('tenant');

    Schema::connection('tenant')->create('products', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('tenant_id');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('sales', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('tenant_id');
        $table->string('store_id')->nullable();
        $table->date('sale_date');
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function (): void {
    Carbon::setTestNow();
    Schema::connection('tenant')->dropIfExists('sales');
    Schema::connection('tenant')->dropIfExists('products');
    DB::purge('tenant');
});

test('non-sales with no data fetches all pages without date filter', function (): void {
    Carbon::setTestNow('2026-05-10 12:00:00');
    Bus::fake();
    Http::fake(['https://api.example.test/products*' => Http::response(['data' => []])]);

    $integration = resourceIntegrationForGenericImporter('products', [
        'requests' => [
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/products',
                    'page_field' => 'pagina',
                    'date_fields' => ['changed_since' => 'data_alteracao'],
                ],
            ],
        ],
    ]);

    genericResourceImporter()->importResource(resolvedConfigForTest($integration), 'products', 'products', genericImporterStore());

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/products?pagina=1');
    Http::assertSentCount(1);
});

test('non-sales with existing data uses changed_since = yesterday', function (): void {
    Carbon::setTestNow('2026-05-10 12:00:00');
    Bus::fake();
    Http::fake(['https://api.example.test/products*' => Http::response(['data' => []])]);

    $integration = resourceIntegrationForGenericImporter('products', [
        'requests' => [
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/products',
                    'date_fields' => ['changed_since' => 'data_alteracao'],
                ],
            ],
        ],
    ]);

    DB::connection('tenant')->table('products')->insert([
        'id' => 'P1',
        'tenant_id' => (string) $integration->tenant_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    genericResourceImporter()->importResource(resolvedConfigForTest($integration), 'products', 'products', genericImporterStore());

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/products?data_alteracao=2026-05-09');
});

test('sales with existing data fetches yesterday and today', function (): void {
    Carbon::setTestNow('2026-05-10 12:00:00');
    Bus::fake();
    Http::fake(['https://api.example.test/sales*' => Http::response(['data' => []])]);

    $integration = resourceIntegrationForGenericImporter('sales', [
        'requests' => [
            'paths' => [
                'sales' => [
                    'target_table' => 'sales',
                    'fallback_path' => '/sales',
                    'date_fields' => ['start' => 'data_inicial', 'end' => 'data_final'],
                ],
            ],
        ],
    ]);

    DB::connection('tenant')->table('sales')->insert([
        'id' => 'S1',
        'tenant_id' => (string) $integration->tenant_id,
        'store_id' => 'STORE-A',
        'sale_date' => '2026-05-09',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $store = new Store;
    $store->id = 'STORE-A';

    genericResourceImporter()->importResource(resolvedConfigForTest($integration), 'sales', 'sales', $store);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/sales?data_inicial=2026-05-09&data_final=2026-05-10');
});

test('sales with no data fetches one request per day for sales_initial_days', function (): void {
    Carbon::setTestNow('2026-05-10 12:00:00');
    Bus::fake();
    Http::fake(['https://api.example.test/sales*' => Http::response(['data' => []])]);

    $integration = resourceIntegrationForGenericImporter('sales', [
        'processing' => ['sales_initial_days' => 3],
        'requests' => [
            'paths' => [
                'sales' => [
                    'target_table' => 'sales',
                    'fallback_path' => '/sales',
                    'date_fields' => ['start' => 'data_inicial', 'end' => 'data_final'],
                ],
            ],
        ],
    ]);

    $store = new Store;
    $store->id = 'STORE-A';

    genericResourceImporter()->importResource(resolvedConfigForTest($integration), 'sales', 'sales', $store);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/sales?data_inicial=2026-05-08&data_final=2026-05-08');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/sales?data_inicial=2026-05-09&data_final=2026-05-09');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/sales?data_inicial=2026-05-10&data_final=2026-05-10');
});

function genericResourceImporter(): GenericIntegrationImporter
{
    return new GenericIntegrationImporter(
        new IntegrationHttpClient,
        new ImportBatchPayloadStore,
        new IntegrationResponseReader,
    );
}

function resolvedConfigForTest(TenantIntegration $integration): ResolvedIntegrationConfig
{
    return app(ResolvedIntegrationConfigResolver::class)->resolve($integration);
}

function genericImporterStore(): Store
{
    $store = new Store;
    $store->id = 'STORE-A';

    return $store;
}

/**
 * @param  array<string, mixed>  $config
 */
function resourceIntegrationForGenericImporter(string $resource, array $config): TenantIntegration
{
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->forceFill(['id' => '01jts31n2rpz1tyy4n6xv4qdn0']);
    $tenant
        ->shouldReceive('execute')
        ->byDefault()
        ->andReturnUsing(fn (callable $callback): mixed => $callback());

    $integration = new TenantIntegration([
        'id' => '01k-generic-importer-test',
        'tenant_id' => $tenant->id,
        'integration_type' => 'custom-api',
        'config' => array_replace_recursive([
            'connection' => ['base_url' => 'https://api.example.test'],
            'requests' => [
                'method' => 'GET',
                'payload' => 'query',
                'paths' => [
                    $resource => [
                        'target_table' => $resource,
                        'fallback_path' => '/'.$resource,
                    ],
                ],
            ],
            'response' => ['items_path' => 'data'],
        ], $config),
    ]);
    $integration->setRelation('tenant', $tenant);

    return $integration;
}
