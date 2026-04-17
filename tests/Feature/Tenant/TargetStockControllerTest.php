<?php

use App\Http\Controllers\Tenant\TargetStockController;
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

it('renders the target stock index page', function () {
    $response = $this->get(route('tenant.analysis.target-stock.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/target-stock/Index')
        ->has('initialData')
    );
});

it('requires authentication to access index', function () {
    auth()->logout();

    $response = $this->get(route('tenant.analysis.target-stock.index'));

    $response->assertStatus(404);
});

it('returns empty results when no category_id or eans are provided', function () {
    $response = $this->get(route('tenant.analysis.target-stock.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/target-stock/Index')
        ->where('initialData.results', [])
    );
});

it('returns inertia response when no products found for eans', function () {
    $response = $this->get(route('tenant.analysis.target-stock.index', [
        'eans' => ['0000000000000'],
        'table_type' => 'monthly_summaries',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/target-stock/Index')
        ->has('initialData')
        ->where('initialData.results', [])
    );
});

it('has getPages method for navigation registration', function () {
    $controller = app(TargetStockController::class);
    $pages = $controller->getPages();

    expect($pages)->toHaveKey('index');
    expect($pages['index']->getName())->toContain('analysis.target-stock');
});

it('accepts valid weight and cut parameters', function () {
    $response = $this->get(route('tenant.analysis.target-stock.index', [
        'eans' => ['0000000000000'],
        'table_type' => 'monthly_summaries',
        'peso_qtde' => 0.3,
        'peso_valor' => 0.3,
        'peso_margem' => 0.4,
        'corte_a' => 0.80,
        'corte_b' => 0.85,
        'period_type' => 'daily',
        'nivel_servico_a' => 0.7,
        'nivel_servico_b' => 0.8,
        'nivel_servico_c' => 0.9,
        'cobertura_dias_a' => 2,
        'cobertura_dias_b' => 5,
        'cobertura_dias_c' => 7,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/target-stock/Index')
    );
});
