<?php

use App\Http\Controllers\Tenant\BcgMatrixController;
use App\Models\User;
use App\Services\Analysis\BcgMatrixService;
use Callcocam\LaravelRaptor\Http\Middleware\TenantMiddleware;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->withoutMiddleware(TenantMiddleware::class);
    $this->user = User::factory()->create();
    actingAs($this->user);

    mock(BcgMatrixService::class)
        ->shouldReceive('analyzeAll')->andReturn(collect())
        ->shouldReceive('analyzeByCategory')->andReturn(collect())
        ->shouldReceive('analyzeByEans')->andReturn(collect());
});

it('renders the bcg matrix index page', function () {
    $response = $this->get(route('tenant.analysis.bcg.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/bcg/Index')
        ->has('initialData')
    );
});

it('requires authentication to access index', function () {
    auth()->logout();

    $response = $this->get(route('tenant.analysis.bcg.index'));

    $response->assertStatus(404);
});

it('returns empty results when no category_id or eans are provided', function () {
    $response = $this->get(route('tenant.analysis.bcg.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/bcg/Index')
        ->where('initialData.results', [])
    );
});

it('returns analysis results with empty array when no matching products', function () {
    $response = $this->get(route('tenant.analysis.bcg.index', [
        'eans' => ['0000000000000'],
        'table_type' => 'monthly_summaries',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/bcg/Index')
        ->has('initialData')
        ->where('initialData.results', [])
        ->has('initialData.summary')
        ->where('initialData.summary.total', 0)
    );
});

it('has getPages method for navigation registration', function () {
    $controller = app(BcgMatrixController::class);
    $pages = $controller->getPages();

    expect($pages)->toHaveKey('index');
    expect($pages['index']->getName())->toContain('analysis.bcg');
});

it('returns summary with all quadrant counts', function () {
    $response = $this->get(route('tenant.analysis.bcg.index', [
        'eans' => ['0000000000000'],
        'table_type' => 'monthly_summaries',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/analysis/bcg/Index')
        ->where('initialData.summary.star', 0)
        ->where('initialData.summary.cash_cow', 0)
        ->where('initialData.summary.question_mark', 0)
        ->where('initialData.summary.dog', 0)
    );
});
