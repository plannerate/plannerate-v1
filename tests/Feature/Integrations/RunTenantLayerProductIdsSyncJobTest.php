<?php

use App\Jobs\Integrations\Support\RunLayerProductIdSyncItemJob;
use App\Jobs\Integrations\Support\RunTenantLayerProductIdsSyncJob;
use App\Models\Tenant;
use App\Services\Integrations\Support\SyncLayerProductIdsFromLegacyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'multitenancy.tenant_database_connection_name' => null,
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant layer sync job dispatches one queue item per invalid layer', function (): void {
    $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create([
        'status' => 'active',
        'database' => 'tenant_'.Str::lower(Str::random(10)),
    ]));

    DB::table('layers')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => (string) $tenant->id,
        'product_id' => '01LEGACYPRODUCT000000000001',
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Bus::fake();

    $job = new RunTenantLayerProductIdsSyncJob(
        tenantId: (string) $tenant->id,
        tenantConnectionName: (string) config('database.default'),
        executeInTenantContext: false,
        preview: false,
    );

    $job->handle(app(SyncLayerProductIdsFromLegacyService::class));

    Bus::assertDispatched(
        RunLayerProductIdSyncItemJob::class,
        fn (RunLayerProductIdSyncItemJob $itemJob): bool => $itemJob->tenantId === (string) $tenant->id
            && $itemJob->legacyProductId === '01LEGACYPRODUCT000000000001'
    );
});
