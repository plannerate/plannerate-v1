<?php

use App\Console\Commands\Integrations\RunIntegrationImportCommand;
use App\Jobs\Integrations\DiscoverIntegrationPagesJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

function makeBackfillTenant(string $slug): Tenant
{
    return Tenant::withoutEvents(function () use ($slug): Tenant {
        return Tenant::query()->create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'database' => (string) config('database.connections.landlord.database').'_'.$slug,
            'status' => 'active',
        ]);
    });
}

function makeBackfillApi(string $slug): IntegrationApi
{
    return IntegrationApi::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'requests' => [
            'method' => 'POST',
            'paths' => [
                'products' => [
                    'fallback_path' => '/products',
                    'field_map' => [],
                ],
                'sales' => [
                    'fallback_path' => '/sales',
                    'field_map' => [],
                ],
            ],
        ],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);
}

test('dispatches a full discovery job per active integration and path', function (): void {
    Bus::fake();

    $api = makeBackfillApi('backfill-api-a');
    $tenant = makeBackfillTenant('backfill-tenant-a');

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => [],
        'is_active' => true,
    ]);

    Artisan::call('integration:backfill');

    Bus::assertDispatched(DiscoverIntegrationPagesJob::class, fn (DiscoverIntegrationPagesJob $job): bool => $job->integrationId === $integration->id
        && $job->pathKey === 'products'
        && $job->forceFull === true);

    Bus::assertDispatched(DiscoverIntegrationPagesJob::class, fn (DiscoverIntegrationPagesJob $job): bool => $job->integrationId === $integration->id
        && $job->pathKey === 'sales'
        && $job->forceFull === true);
});

test('ignores inactive integrations', function (): void {
    Bus::fake();

    $api = makeBackfillApi('backfill-api-b');
    $tenant = makeBackfillTenant('backfill-tenant-b');

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => [],
        'is_active' => false,
    ]);

    Artisan::call('integration:backfill');

    Bus::assertNotDispatched(DiscoverIntegrationPagesJob::class);
});

test('--path filters which path gets a full discovery job', function (): void {
    Bus::fake();

    $api = makeBackfillApi('backfill-api-c');
    $tenant = makeBackfillTenant('backfill-tenant-c');

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => [],
        'is_active' => true,
    ]);

    Artisan::call('integration:backfill', ['--path' => 'products']);

    Bus::assertDispatched(DiscoverIntegrationPagesJob::class, fn (DiscoverIntegrationPagesJob $job): bool => $job->integrationId === $integration->id && $job->pathKey === 'products');

    Bus::assertNotDispatched(DiscoverIntegrationPagesJob::class, fn (DiscoverIntegrationPagesJob $job): bool => $job->pathKey === 'sales');
});

test('--integration filters which integration gets backfilled', function (): void {
    Bus::fake();

    $api = makeBackfillApi('backfill-api-d');
    $tenantA = makeBackfillTenant('backfill-tenant-d1');
    $tenantB = makeBackfillTenant('backfill-tenant-d2');

    $integrationA = TenantIntegration::query()->create([
        'tenant_id' => $tenantA->id,
        'integration_type' => $api->id,
        'config' => [],
        'is_active' => true,
    ]);

    $integrationB = TenantIntegration::query()->create([
        'tenant_id' => $tenantB->id,
        'integration_type' => $api->id,
        'config' => [],
        'is_active' => true,
    ]);

    Artisan::call('integration:backfill', ['--integration' => $integrationA->id]);

    Bus::assertDispatched(DiscoverIntegrationPagesJob::class, fn (DiscoverIntegrationPagesJob $job): bool => $job->integrationId === $integrationA->id);

    Bus::assertNotDispatched(DiscoverIntegrationPagesJob::class, fn (DiscoverIntegrationPagesJob $job): bool => $job->integrationId === $integrationB->id);
});

test('aborts when another discovery dispatch holds the lock', function (): void {
    Bus::fake();

    $api = makeBackfillApi('backfill-api-lock');
    $tenant = makeBackfillTenant('backfill-tenant-lock');

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => [],
        'is_active' => true,
    ]);

    $lock = Cache::lock(RunIntegrationImportCommand::DISPATCH_LOCK_KEY, 60);
    expect($lock->get())->toBeTrue();

    try {
        $exitCode = Artisan::call('integration:backfill');
    } finally {
        $lock->release();
    }

    expect($exitCode)->toBe(Command::FAILURE);
    Bus::assertNotDispatched(DiscoverIntegrationPagesJob::class);
});
