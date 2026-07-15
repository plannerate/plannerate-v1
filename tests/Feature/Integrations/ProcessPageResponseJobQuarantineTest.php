<?php

use App\Jobs\Integrations\ProcessPageResponseJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('move o arquivo para quarentena quando a integração não existe mais', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('imports/01TESTMISSINGINTEGRATION.json', json_encode([['id' => 'x']]));

    $job = new ProcessPageResponseJob(
        integrationId: '01JZZZZZZZZZZZZZZZZZZZZZZZ',
        pathKey: 'products',
        storeId: null,
        filePath: 'imports/01TESTMISSINGINTEGRATION.json',
    );

    $job->handle();

    Storage::disk('local')->assertMissing('imports/01TESTMISSINGINTEGRATION.json');
    Storage::disk('local')->assertExists('imports/failed/01TESTMISSINGINTEGRATION.json');
});

test('failed() preserva o arquivo em quarentena em vez de apagar', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('imports/01TESTFAILEDJOB.json', json_encode([['id' => 'x']]));

    $job = new ProcessPageResponseJob(
        integrationId: '01JZZZZZZZZZZZZZZZZZZZZZZZ',
        pathKey: 'products',
        storeId: null,
        filePath: 'imports/01TESTFAILEDJOB.json',
    );

    $job->failed(new RuntimeException('boom'));

    Storage::disk('local')->assertMissing('imports/01TESTFAILEDJOB.json');
    Storage::disk('local')->assertExists('imports/failed/01TESTFAILEDJOB.json');
});

test('JSON inválido vai para quarentena', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('imports/01TESTINVALIDJSON.json', 'isto não é json');

    $tenant = Tenant::withoutEvents(function (): Tenant {
        return Tenant::query()->create([
            'name' => 'QUARANTINE',
            'slug' => 'quarantine-tenant',
            'database' => (string) config('database.connections.landlord.database').'_quarantine',
            'status' => 'active',
        ]);
    });

    $api = IntegrationApi::query()->create([
        'name' => 'QUARANTINE API',
        'slug' => 'quarantine-api',
        'requests' => [
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'field_map' => [],
                ],
            ],
        ],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    $integration = TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => [],
        'is_active' => true,
    ]);

    $job = new ProcessPageResponseJob(
        integrationId: (string) $integration->id,
        pathKey: 'products',
        storeId: null,
        filePath: 'imports/01TESTINVALIDJSON.json',
    );

    $job->handle();

    Storage::disk('local')->assertMissing('imports/01TESTINVALIDJSON.json');
    Storage::disk('local')->assertExists('imports/failed/01TESTINVALIDJSON.json');
});
