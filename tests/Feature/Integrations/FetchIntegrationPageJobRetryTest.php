<?php

use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Jobs\Integrations\ProcessPageResponseJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

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

test('HTTP 404 falha em definitivo, sem lançar exceção de retry', function (): void {
    Http::fake(['erp.example.test/*' => Http::response(null, 404)]);
    Bus::fake([ProcessPageResponseJob::class]);

    $integration = makeFetchRetryIntegration('fetch-retry-404');

    $job = new FetchIntegrationPageJob((string) $integration->id, 'products', 1);

    // fail() marca o job como falho em definitivo; handle() retorna sem exceção
    $job->handle();

    Bus::assertNotDispatched(ProcessPageResponseJob::class);
});
