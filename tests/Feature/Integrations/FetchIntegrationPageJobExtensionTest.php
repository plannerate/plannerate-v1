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

function makeFetchExtensionIntegration(string $slug, array $pathOverrides = []): TenantIntegration
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
                    'field_map' => [],
                    ...$pathOverrides,
                ],
            ],
        ],
        'response' => [
            'items_path' => 'data',
            'pagination' => ['last_page_path' => 'meta.last_page'],
        ],
        'is_active' => true,
    ]);

    return TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.extension.test']],
        'is_active' => true,
    ]);
}

test('a última página planejada despacha as páginas que surgiram após a sondagem', function (): void {
    Http::fake([
        'erp.extension.test/*' => Http::response(['data' => [], 'meta' => ['last_page' => 4]]),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeFetchExtensionIntegration('fetch-ext-grow');

    $job = new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 2,
        knownLastPage: 2,
    );

    $job->handle();

    Bus::assertDispatched(FetchIntegrationPageJob::class, fn (FetchIntegrationPageJob $j): bool => $j->page === 3 && $j->knownLastPage === 4);
    Bus::assertDispatched(FetchIntegrationPageJob::class, fn (FetchIntegrationPageJob $j): bool => $j->page === 4 && $j->knownLastPage === 4);
    Bus::assertNotDispatched(FetchIntegrationPageJob::class, fn (FetchIntegrationPageJob $j): bool => $j->page === 5);
});

test('sem páginas novas, nada é despachado', function (): void {
    Http::fake([
        'erp.extension.test/*' => Http::response(['data' => [], 'meta' => ['last_page' => 2]]),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeFetchExtensionIntegration('fetch-ext-stable');

    $job = new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 2,
        knownLastPage: 2,
    );

    $job->handle();

    Bus::assertNotDispatched(FetchIntegrationPageJob::class);
});

test('a extensão respeita o max_page do path', function (): void {
    Http::fake([
        'erp.extension.test/*' => Http::response(['data' => [], 'meta' => ['last_page' => 10]]),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeFetchExtensionIntegration('fetch-ext-cap', ['max_page' => 3]);

    $job = new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 2,
        knownLastPage: 2,
    );

    $job->handle();

    Bus::assertDispatched(FetchIntegrationPageJob::class, fn (FetchIntegrationPageJob $j): bool => $j->page === 3);
    Bus::assertNotDispatched(FetchIntegrationPageJob::class, fn (FetchIntegrationPageJob $j): bool => $j->page === 4);
});

test('páginas intermediárias não estendem a paginação', function (): void {
    Http::fake([
        'erp.extension.test/*' => Http::response(['data' => [], 'meta' => ['last_page' => 4]]),
    ]);
    Bus::fake([FetchIntegrationPageJob::class, ProcessPageResponseJob::class]);

    $integration = makeFetchExtensionIntegration('fetch-ext-mid');

    $job = new FetchIntegrationPageJob(
        (string) $integration->id, 'products', 1,
        knownLastPage: 2,
    );

    $job->handle();

    Bus::assertNotDispatched(FetchIntegrationPageJob::class);
});
