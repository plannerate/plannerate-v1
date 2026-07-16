<?php

use App\Jobs\Integrations\DiscoverIntegrationPagesJob;
use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Models\IntegrationApi;
use App\Models\IntegrationImportRun;
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

function makeDiscoverFailureIntegration(string $slug): TenantIntegration
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
                ],
            ],
        ],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    return TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.discover.test']],
        'is_active' => true,
    ]);
}

test('sondagem falhando em todas as lojas lança exceção para o retry assumir', function (): void {
    Http::fake(['erp.discover.test/*' => Http::response(null, 500)]);
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeDiscoverFailureIntegration('discover-fail-all');

    $job = new DiscoverIntegrationPagesJob((string) $integration->id, 'products');

    // Nada foi despachado → re-tentar o discover inteiro é seguro
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);

    Bus::assertNotDispatched(FetchIntegrationPageJob::class);
});

test('sondagem bem-sucedida despacha os fetch jobs com o last_page conhecido', function (): void {
    Http::fake([
        'erp.discover.test/*' => Http::response(['data' => [['x' => 1]]]),
    ]);
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeDiscoverFailureIntegration('discover-ok');

    $job = new DiscoverIntegrationPagesJob((string) $integration->id, 'products');
    $job->handle();

    // Sem pagination na resposta → 1 página, com knownLastPage preenchido
    Bus::assertDispatched(FetchIntegrationPageJob::class, fn (FetchIntegrationPageJob $j): bool => $j->page === 1 && $j->knownLastPage === 1 && $j->runId !== null);

    // O discover registra o run com o plano (page mode: expected = páginas).
    $run = IntegrationImportRun::query()->where('integration_id', (string) $integration->id)->first();
    expect($run)->not->toBeNull()
        ->and($run->path_key)->toBe('products')
        ->and($run->mode)->toBe('page')
        ->and($run->expected_units)->toBe(1)
        ->and($run->status)->toBe('running');
});
