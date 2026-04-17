<?php

use App\Http\Controllers\Tenant\AbcAnalysisController;
use App\Models\User;
use App\Services\Plannerate\AbcAnalysisService;
use Callcocam\LaravelRaptor\Http\Middleware\TenantMiddleware;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->withoutMiddleware(TenantMiddleware::class);
    $this->user = User::factory()->create();
    actingAs($this->user);

    mock(AbcAnalysisService::class)
        ->shouldReceive('setWeights')->andReturnSelf()
        ->shouldReceive('setCuts')->andReturnSelf()
        ->shouldReceive('analyzeAll')->andReturn(collect())
        ->shouldReceive('analyzeByCategory')->andReturn(collect())
        ->shouldReceive('analyzeByEans')->andReturn(collect());
});

it('renders the abc analysis index page', function () {
    $response = $this->get(route('tenant.analysis.abc.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/abc/Index')
        ->has('initialData')
        ->has('initialData.filters')
        ->has('initialData.weights')
        ->has('initialData.cuts')
    );
});

it('requires authentication to access index', function () {
    auth()->logout();

    $response = $this->get(route('tenant.analysis.abc.index'));

    $response->assertStatus(404);
});

it('returns empty results on the index page when nothing is found', function () {
    $response = $this->get(route('tenant.analysis.abc.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/abc/Index')
        ->where('initialData.results', [])
    );
});

it('has getPages method for navigation registration', function () {
    $controller = app(AbcAnalysisController::class);
    $pages = $controller->getPages();

    expect($pages)->toHaveKey('index');
    expect($pages['index']->getName())->toContain('analysis.abc');
});

it('returns empty results when no matching products are found via eans', function () {
    $response = $this->get(route('tenant.analysis.abc.index', [
        'eans' => ['0000000000000'],
        'table_type' => 'monthly_summaries',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/abc/Index')
        ->has('initialData')
        ->where('initialData.results', [])
    );
});

it('returns formatted results when products are found', function () {
    $fakeResults = collect([[
        'product_id' => 'prod-1',
        'product_name' => 'Produto Teste',
        'ean' => '7891000315507',
        'image_url' => null,
        'category_id' => 'cat-1',
        'category_name' => 'Categoria Teste',
        'qtde' => 100.0,
        'valor' => 1500.0,
        'margem' => 300.0,
        'media_ponderada' => 50.0,
        'percentual_individual' => 10.0,
        'percentual_acumulado' => 10.0,
        'classificacao' => 'A',
        'ranking' => 1,
        'class_rank' => 'A1',
        'retirar_do_mix' => false,
        'status' => 'active',
    ]]);

    mock(AbcAnalysisService::class)
        ->shouldReceive('setWeights')->andReturnSelf()
        ->shouldReceive('setCuts')->andReturnSelf()
        ->shouldReceive('analyzeAll')->andReturn(collect())
        ->shouldReceive('analyzeByEans')->andReturn($fakeResults);

    $response = $this->get(route('tenant.analysis.abc.index', [
        'eans' => ['7891000315507'],
        'table_type' => 'monthly_summaries',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/abc/Index')
        ->has('initialData.results', 1)
        ->where('initialData.results.0.classificacao', 'A')
    );
});
