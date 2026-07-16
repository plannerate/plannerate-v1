<?php

use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Jobs\Integrations\ProcessPageResponseJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

function makeFetchRetryIntegration(string $slug): TenantIntegration
{
    $tenant = Tenant::withoutEvents(function () use ($slug): Tenant {
        return Tenant::query()->create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'database' => (string) config('database.connections.landlord.database').'_'.$slug,
            'status' => 'active',
        ]);
    });

    $api = IntegrationApi::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'requests' => [
            'method' => 'GET',
            'paths' => [
                'products' => [
                    'fallback_path' => '/products',
                    'target_table' => 'products',
                    'unique_by' => ['ean'],
                    'field_map' => [],
                ],
            ],
        ],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    return TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.example.test']],
        'is_active' => true,
    ]);
}

test('HTTP 500 lança exceção para o retry com backoff assumir', function (): void {
    Http::fake(['erp.example.test/*' => Http::response(['error' => 'boom'], 500)]);
    Bus::fake([ProcessPageResponseJob::class]);

    $integration = makeFetchRetryIntegration('fetch-retry-500');

    $job = new FetchIntegrationPageJob((string) $integration->id, 'products', 1);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);

    Bus::assertNotDispatched(ProcessPageResponseJob::class);
});

test('HTTP 429 também é re-tentável', function (): void {
    Http::fake(['erp.example.test/*' => Http::response(null, 429)]);
    Bus::fake([ProcessPageResponseJob::class]);

    $integration = makeFetchRetryIntegration('fetch-retry-429');

    $job = new FetchIntegrationPageJob((string) $integration->id, 'products', 1);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
});

test('HTTP 401 é re-tentável — token em cache pode ter expirado', function (): void {
    Http::fake(['erp.example.test/*' => Http::response(null, 401)]);
    Bus::fake([ProcessPageResponseJob::class]);

    $integration = makeFetchRetryIntegration('fetch-retry-401');

    $job = new FetchIntegrationPageJob((string) $integration->id, 'products', 1);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
});

test('HTTP 404 falha em definitivo, sem lançar exceção de retry', function (): void {
    Http::fake(['erp.example.test/*' => Http::response(null, 404)]);
    Bus::fake([ProcessPageResponseJob::class]);

    $integration = makeFetchRetryIntegration('fetch-retry-404');

    $job = new FetchIntegrationPageJob((string) $integration->id, 'products', 1);

    // fail() marca o job como falho em definitivo; handle() retorna sem exceção
    $job->handle();

    Bus::assertNotDispatched(ProcessPageResponseJob::class);
});

test('job enfileirado antes do deploy (runId não-inicializado) não estoura ao despachar o process', function (): void {
    Http::fake(['erp.example.test/*' => Http::response(['data' => [['produto' => 'ERP-1', 'ean' => '7890000000001']]])]);
    Storage::fake('local');
    Bus::fake([ProcessPageResponseJob::class]);

    $integration = makeFetchRetryIntegration('fetch-old-serialized');

    // Simula desserialização de um job antigo (enfileirado antes do deploy que
    // adicionou $runId): newInstanceWithoutConstructor deixa a typed property
    // $runId NÃO-inicializada — acessá-la direto estouraria.
    $ref = new ReflectionClass(FetchIntegrationPageJob::class);
    $job = $ref->newInstanceWithoutConstructor();

    foreach ([
        'integrationId' => (string) $integration->id,
        'pathKey' => 'products',
        'page' => 1,
        'dateStart' => null,
        'dateEnd' => null,
        'storeId' => null,
        'storeDocument' => null,
        'autoPage' => false,
        'knownLastPage' => null,
    ] as $prop => $value) {
        $ref->getProperty($prop)->setValue($job, $value);
    }
    // $runId deixado NÃO-inicializado de propósito.

    $job->handle(); // não deve estourar "must not be accessed before initialization"

    Bus::assertDispatched(ProcessPageResponseJob::class, fn (ProcessPageResponseJob $j): bool => $j->runId === null);
});
