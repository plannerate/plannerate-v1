<?php

use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationDailySyncJob;
use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationInitialSyncJob;
use App\Jobs\Integrations\Maintenance\RunTenantIntegrationNightlyMaintenanceJob;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

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
