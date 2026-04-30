<?php

use App\Jobs\Integrations\Support\RunTenantLayerProductIdsSyncJob;
use App\Models\Tenant;
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

test('command dispatches layer sync job for active tenants', function (): void {
    $legacyPath = storage_path('framework/testing/legacy-layer-sync-command.sqlite');
    if (! is_dir(dirname($legacyPath))) {
        mkdir(dirname($legacyPath), 0777, true);
    }
    if (! file_exists($legacyPath)) {
        touch($legacyPath);
    }

    config([
        'database.connections.mysql_legacy' => [
            'driver' => 'sqlite',
            'database' => $legacyPath,
            'prefix' => '',
        ],
    ]);

    DB::purge('mysql_legacy');

    $activeTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create([
        'status' => 'active',
        'database' => 'tenant_'.Str::lower(Str::random(10)),
    ]));
    Tenant::withoutEvents(fn () => Tenant::factory()->create(['status' => 'inactive']));

    Bus::fake();

    $this->artisan('sync:layers-product-ids-from-legacy')
        ->assertExitCode(0);

    Bus::assertDispatched(
        RunTenantLayerProductIdsSyncJob::class,
        fn (RunTenantLayerProductIdsSyncJob $job): bool => $job->tenantId === (string) $activeTenant->id
            && $job->preview === false
    );
});
