<?php

use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationDailySyncJob;
use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationInitialSyncJob;
use App\Jobs\Integrations\Maintenance\RunTenantIntegrationNightlyMaintenanceJob;
use App\Jobs\Integrations\Products\SyncTenantProductsDayJob;
use App\Jobs\Integrations\Sales\SyncTenantSalesDayJob;
use App\Models\IntegrationSyncDay;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Services\Integrations\Orchestration\DispatchDailySyncService;
use App\Services\Integrations\Orchestration\DispatchInitialSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());
});

test('integrations dispatch daily command enqueues jobs for active integrations', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Daily',
        'slug' => 'tenant-daily-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['daily_lookback_days' => 7]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-daily')->assertSuccessful();

    Bus::assertDispatched(DispatchTenantIntegrationDailySyncJob::class, 1);
});

test('integrations dispatch initial command enqueues jobs for active integrations', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial',
        'slug' => 'tenant-initial-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 30, 'products_initial_days' => 15]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-initial')->assertSuccessful();

    Bus::assertDispatched(DispatchTenantIntegrationInitialSyncJob::class, 1);
});

test('initial sync sales jobs are queued with tenant context', function () {
    config([
        'queue.default' => 'database',
        'queue.connections.database.connection' => 'landlord',
        'multitenancy.tenant_database_connection_name' => 'tenant',
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial Context',
        'slug' => 'tenant-initial-context-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 1, 'products_initial_days' => 1]],
        'is_active' => true,
    ]);

    app(DispatchInitialSyncService::class)->dispatch($integration);

    $payload = DB::connection('landlord')
        ->table('jobs')
        ->where('payload', 'like', '%SyncTenantSalesDayJob%')
        ->value('payload');

    $decodedPayload = json_decode((string) $payload, true);

    expect($payload)->not->toBeNull()
        ->and($decodedPayload)->toHaveKey('illuminate:log:context')
        ->and(unserialize($decodedPayload['illuminate:log:context']['data']['tenantId']))->toBe($tenant->id);
});

test('daily sync sales jobs are queued with tenant context', function () {
    config([
        'queue.default' => 'database',
        'queue.connections.database.connection' => 'landlord',
        'multitenancy.tenant_database_connection_name' => 'tenant',
    ]);

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Daily Context',
        'slug' => 'tenant-daily-context-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['daily_lookback_days' => 2]],
        'is_active' => true,
    ]);

    app(DispatchDailySyncService::class)->dispatch($integration);

    $payload = DB::connection('landlord')
        ->table('jobs')
        ->where('payload', 'like', '%SyncTenantSalesDayJob%')
        ->value('payload');

    $decodedPayload = json_decode((string) $payload, true);

    expect($payload)->not->toBeNull()
        ->and($decodedPayload)->toHaveKey('illuminate:log:context')
        ->and(unserialize($decodedPayload['illuminate:log:context']['data']['tenantId']))->toBe($tenant->id);
});

test('initial sync skips sales dates already marked as success', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Initial Skip',
        'slug' => 'tenant-initial-skip-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 1, 'products_initial_days' => 1]],
        'is_active' => true,
    ]);

    $referenceDate = Carbon::yesterday()->toDateString();

    IntegrationSyncDay::query()->create([
        'tenant_integration_id' => $integration->id,
        'resource' => 'sales',
        'reference_date' => $referenceDate,
        'status' => 'success',
    ]);

    IntegrationSyncDay::query()->create([
        'tenant_integration_id' => $integration->id,
        'resource' => 'products',
        'reference_date' => $referenceDate,
        'status' => 'success',
    ]);

    app(DispatchInitialSyncService::class)->dispatch($integration);

    Bus::assertNotDispatched(SyncTenantSalesDayJob::class);
    Bus::assertNotDispatched(SyncTenantProductsDayJob::class);
});

test('initial sync dispatches products as single full sync bootstrap', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Products Bootstrap',
        'slug' => 'tenant-products-bootstrap-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_initial_days' => 3, 'products_initial_days' => 30]],
        'is_active' => true,
    ]);

    app(DispatchInitialSyncService::class)->dispatch($integration);

    Bus::assertDispatched(
        SyncTenantProductsDayJob::class,
        fn (SyncTenantProductsDayJob $job): bool => $job->integrationId === (string) $integration->id
            && $job->fullSync === true
    );
});

test('queue and cache infrastructure use landlord connection explicitly', function () {
    expect(config('multitenancy.tenant_database_connection_name'))->toBe('tenant')
        ->and(config('cache.stores.database.connection'))->toBe('landlord')
        ->and(config('cache.stores.database.lock_connection'))->toBe('landlord')
        ->and(config('queue.connections.database.connection'))->toBe('landlord')
        ->and(config('queue.batching.database'))->toBe('landlord')
        ->and(config('queue.failed.database'))->toBe('landlord');
});

test('makeCurrent switches only dedicated tenant connection', function () {
    $defaultConnectionName = (string) config('database.default');
    $tenantConnectionName = (string) config('multitenancy.tenant_database_connection_name');

    $originalDefaultDatabase = config("database.connections.{$defaultConnectionName}.database");
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Connection Switch',
        'slug' => 'tenant-connection-switch-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_connection_switch_db',
        'status' => 'active',
    ]));

    $tenant->makeCurrent();

    expect(config("database.connections.{$tenantConnectionName}.database"))->toBe('tenant_connection_switch_db')
        ->and(config("database.connections.{$defaultConnectionName}.database"))->toBe($originalDefaultDatabase);

    Tenant::forgetCurrent();

    expect(config("database.connections.{$tenantConnectionName}.database"))->toBeNull();
});

test('integrations nightly maintenance command enqueues maintenance job', function () {
    Bus::fake();

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Maintenance',
        'slug' => 'tenant-maintenance-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
    ]));

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'config' => ['processing' => ['sales_retention_days' => 120]],
        'is_active' => true,
    ]);

    $this->artisan('integrations:dispatch-nightly-maintenance')->assertSuccessful();

    Bus::assertDispatched(RunTenantIntegrationNightlyMaintenanceJob::class, 1);
});
