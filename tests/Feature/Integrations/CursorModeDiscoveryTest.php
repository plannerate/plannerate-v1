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

/*
 * Descoberta no modo cursor: não há total de páginas para sondar, então o
 * discover apenas semeia a cadeia. E o modo diário, quando o path é cursor,
 * precisa semear cada dia com o cursor inicial — senão o {cursor} da URL sai
 * vazio e a chamada quebra.
 */

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

/** @param array<string, mixed> $pathOverrides */
function makeCursorDiscoveryIntegration(string $slug, array $pathOverrides = []): TenantIntegration
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
            'pagination_mode' => 'cursor',
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/produtos/{cursor}',
                    'cursor_item_path' => 'Codigo',
                    'cursor_initial' => 0,
                    'field_map' => [],
                    ...$pathOverrides,
                ],
            ],
        ],
        'response' => ['items_path' => 'response.produtos'],
        'is_active' => true,
    ]);

    return TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.cursordisc.test']],
        'is_active' => true,
    ]);
}

test('semeia uma única cadeia com o cursor inicial, sem sondar a API', function (): void {
    Http::fake();
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeCursorDiscoveryIntegration('cursor-disc-seed');

    (new DiscoverIntegrationPagesJob((string) $integration->id, 'products'))->handle();

    Bus::assertDispatchedTimes(FetchIntegrationPageJob::class, 1);
    Bus::assertDispatched(
        FetchIntegrationPageJob::class,
        fn (FetchIntegrationPageJob $j): bool => $j->cursor === '0' && $j->page === 1 && $j->runId !== null,
    );

    // Sem sondagem HTTP: a API não informa total de páginas.
    Http::assertNothingSent();
});

test('o run do modo cursor mede o início da cadeia, não o número de páginas', function (): void {
    Http::fake();
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeCursorDiscoveryIntegration('cursor-disc-run');

    (new DiscoverIntegrationPagesJob((string) $integration->id, 'products'))->handle();

    $run = IntegrationImportRun::query()->where('integration_id', (string) $integration->id)->first();

    expect($run)->not->toBeNull()
        ->and($run->mode)->toBe('cursor')
        ->and($run->expected_units)->toBe(1)
        ->and($run->status)->toBe('running');
});

test('o modo diário tem precedência e semeia cada dia com o cursor inicial', function (): void {
    Http::fake();
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeCursorDiscoveryIntegration('cursor-disc-daily', [
        'target_table' => 'sales',
        'initial_days' => 2,
        'last_date_column' => 'sale_date',
    ]);

    // forceFull evita a consulta de lacunas no banco do tenant (inexistente em teste);
    // o que importa aqui é o cursor da semente, não o filtro incremental.
    (new DiscoverIntegrationPagesJob((string) $integration->id, 'products', forceFull: true))->handle();

    // initial_days = 2 → hoje, ontem e anteontem.
    Bus::assertDispatchedTimes(FetchIntegrationPageJob::class, 3);
    Bus::assertDispatched(
        FetchIntegrationPageJob::class,
        fn (FetchIntegrationPageJob $j): bool => $j->cursor === '0' && $j->dateStart === now()->toDateString(),
    );

    $run = IntegrationImportRun::query()->where('integration_id', (string) $integration->id)->first();
    expect($run->mode)->toBe('daily')->and($run->expected_units)->toBe(3);
});

test('blueprint sem pagination_mode continua no modo página — nenhuma regressão', function (): void {
    Http::fake(['erp.cursordisc.test/*' => Http::response(['response' => ['produtos' => [['Codigo' => 1]]]])]);
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeCursorDiscoveryIntegration('cursor-disc-legacy');
    $api = $integration->api;
    $requests = $api->requests;
    unset($requests['pagination_mode']);
    $api->requests = $requests;
    $api->save();

    (new DiscoverIntegrationPagesJob((string) $integration->id, 'products', forceFull: true))->handle();

    Bus::assertDispatched(
        FetchIntegrationPageJob::class,
        fn (FetchIntegrationPageJob $j): bool => $j->cursor === null && $j->knownLastPage === 1,
    );

    expect(IntegrationImportRun::query()->where('integration_id', (string) $integration->id)->first()->mode)
        ->toBe('page');
});
