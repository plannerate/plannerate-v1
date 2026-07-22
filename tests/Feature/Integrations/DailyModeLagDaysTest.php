<?php

use App\Jobs\Integrations\DiscoverIntegrationPagesJob;
use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/*
 * `lag_days` desloca a janela do modo diário para trás.
 *
 * Existe porque alguns ERPs só materializam o movimento do dia depois do
 * fechamento: a RP Info responde HTTP 200 com "Não localizada tabela de
 * movimento ... para a data:<hoje>" quando o import roda às 06:00. Buscar a
 * partir de ontem evita a retentativa inútil sem perder dado — a janela de
 * recheck re-busca os dias recentes de qualquer forma.
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
function makeLagDaysIntegration(string $slug, array $pathOverrides = []): TenantIntegration
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
                'sales' => [
                    'target_table' => 'sales',
                    'fallback_path' => '/vendas',
                    'last_date_column' => 'sale_date',
                    'initial_days' => 2,
                    'date_fields' => ['start' => 'de', 'end' => 'ate'],
                    'field_map' => [],
                    ...$pathOverrides,
                ],
            ],
        ],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    return TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.lag.test']],
        'is_active' => true,
    ]);
}

/** @return array<int, string> Datas despachadas, da mais recente para a mais antiga. */
function datasDespachadas(): array
{
    $datas = [];

    Bus::assertDispatched(FetchIntegrationPageJob::class, function (FetchIntegrationPageJob $j) use (&$datas): bool {
        $datas[] = (string) $j->dateStart;

        return true;
    });

    rsort($datas);

    return $datas;
}

test('sem lag_days a janela começa hoje — comportamento das integrações existentes', function (): void {
    Http::fake();
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeLagDaysIntegration('lag-zero');

    (new DiscoverIntegrationPagesJob((string) $integration->id, 'sales', forceFull: true))->handle();

    expect(datasDespachadas())->toBe([
        now()->toDateString(),
        now()->subDay()->toDateString(),
        now()->subDays(2)->toDateString(),
    ]);
});

test('lag_days 1 pula o dia corrente e mantém o mesmo tamanho de janela', function (): void {
    Http::fake();
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeLagDaysIntegration('lag-um', ['lag_days' => 1]);

    (new DiscoverIntegrationPagesJob((string) $integration->id, 'sales', forceFull: true))->handle();

    $datas = datasDespachadas();

    expect($datas)->toBe([
        now()->subDay()->toDateString(),
        now()->subDays(2)->toDateString(),
        now()->subDays(3)->toDateString(),
    ])
        // O ponto do lag: o dia de hoje não é pedido à API.
        ->and($datas)->not->toContain(now()->toDateString())
        ->and($datas)->toHaveCount(3);
});

test('lag_days maior desloca a janela proporcionalmente', function (): void {
    Http::fake();
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeLagDaysIntegration('lag-tres', ['lag_days' => 3]);

    (new DiscoverIntegrationPagesJob((string) $integration->id, 'sales', forceFull: true))->handle();

    expect(datasDespachadas())->toBe([
        now()->subDays(3)->toDateString(),
        now()->subDays(4)->toDateString(),
        now()->subDays(5)->toDateString(),
    ]);
});

test('lag_days negativo é tratado como zero', function (): void {
    Http::fake();
    Bus::fake([FetchIntegrationPageJob::class]);

    $integration = makeLagDaysIntegration('lag-negativo', ['lag_days' => -5]);

    (new DiscoverIntegrationPagesJob((string) $integration->id, 'sales', forceFull: true))->handle();

    expect(datasDespachadas()[0])->toBe(now()->toDateString());
});

test('o blueprint rpinfo pede vendas a partir de ontem', function (): void {
    $api = IntegrationApi::query()->where('slug', 'rpinfo')->firstOrFail();

    expect(data_get($api->requests, 'paths.sales.lag_days'))->toBe(1)
        // Produtos não têm janela de data — o lag não se aplica.
        ->and(data_get($api->requests, 'paths.products.lag_days'))->toBeNull();
});
