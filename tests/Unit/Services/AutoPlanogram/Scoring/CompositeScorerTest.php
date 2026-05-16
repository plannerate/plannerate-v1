<?php

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Scoring\CompositeScorer;
use App\Services\AutoPlanogram\Scoring\SalesMetricsRepository;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── Helpers ──────────────────────────────────────────────────────────────────

function scorerProduct(string $id): Product
{
    $p = new Product;
    $p->id = $id;
    $p->ean = '0000000000000';
    $p->codigo_erp = 'ERP-'.$id;
    $p->setRelation('category', null);

    return $p;
}

function stubSalesRepo(array $metrics): SalesMetricsRepository
{
    return new class($metrics) extends SalesMetricsRepository
    {
        public function __construct(private readonly array $map) {}

        public function fetchMetrics(string $tenantId, Collection $productIds, int $windowMonths, ?string $storeId = null): array
        {
            return $this->map;
        }
    };
}

function scorerSettings(?ScoringWeightsValue $weights = null, ?string $tenantId = null): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'mix',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        tenantId: $tenantId ?? (string) Str::ulid(),
        weights: $weights ?? ScoringWeightsValue::default(),
    );
}

/** Scorer com strategic IDs injetados, sem precisar de DB. */
function scorerWithStrategic(array $metrics, array $strategicIds = []): CompositeScorer
{
    $repo = stubSalesRepo($metrics);

    return new class($repo, $strategicIds) extends CompositeScorer
    {
        public function __construct(
            SalesMetricsRepository $sales,
            private readonly array $strategicIds,
        ) {
            parent::__construct($sales);
        }

        protected function loadStrategicIds(string $tenantId): array
        {
            return $this->strategicIds;
        }
    };
}

// ── Testes ───────────────────────────────────────────────────────────────────

test('Cenário 1: 3 produtos da mesma categoria com vendas distintas geram ranking esperado', function () {
    $idA = (string) Str::ulid();
    $idB = (string) Str::ulid();
    $idC = (string) Str::ulid();

    $metrics = [
        $idA => ['quantity' => 100, 'margem' => 500.0, 'doh' => null],
        $idB => ['quantity' => 50,  'margem' => 200.0, 'doh' => null],
        $idC => ['quantity' => 10,  'margem' => 50.0,  'doh' => null],
    ];

    $result = (new CompositeScorer(stubSalesRepo($metrics)))->score(
        collect([scorerProduct($idA), scorerProduct($idB), scorerProduct($idC)]),
        scorerSettings(),
    );

    expect($result)->toHaveCount(3)
        ->and($result->first())->toBeInstanceOf(ScoredProduct::class)
        ->and($result->first()->productId)->toBe($idA)
        ->and($result->last()->productId)->toBe($idC);
});

test('Cenário 2: categoria com 1 SKU retorna 0.5 em giro_norm e margem_norm (min=max)', function () {
    $id = (string) Str::ulid();

    $result = (new CompositeScorer(stubSalesRepo([
        $id => ['quantity' => 99, 'margem' => 999.0, 'doh' => null],
    ])))->score(
        collect([scorerProduct($id)]),
        scorerSettings(),
    );

    expect($result)->toHaveCount(1)
        ->and($result->first()->metadata['giro_norm'])->toBe(0.5)
        ->and($result->first()->metadata['margem_norm'])->toBe(0.5);
});

test('Cenário 3: DOH null é tratado como neutro (doh_norm = 0.5)', function () {
    $id = (string) Str::ulid();

    $result = (new CompositeScorer(stubSalesRepo([
        $id => ['quantity' => 10, 'margem' => 10.0, 'doh' => null],
    ])))->score(
        collect([scorerProduct($id)]),
        scorerSettings(),
    );

    expect($result->first()->metadata['doh_norm'])->toBe(0.5);
});

test('Cenário 4: produto estratégico vence empate com mesmo giro e margem', function () {
    $idNormal = (string) Str::ulid();
    $idStrategic = (string) Str::ulid();

    $metrics = [
        $idNormal => ['quantity' => 50, 'margem' => 50.0, 'doh' => null],
        $idStrategic => ['quantity' => 50, 'margem' => 50.0, 'doh' => null],
    ];

    $result = scorerWithStrategic($metrics, [$idStrategic])->score(
        collect([scorerProduct($idNormal), scorerProduct($idStrategic)]),
        scorerSettings(),
    );

    expect($result->first()->productId)->toBe($idStrategic);
});

test('Cenário 5: pesos zerados resultam em score 0 para todos', function () {
    $id = (string) Str::ulid();

    $result = (new CompositeScorer(stubSalesRepo([
        $id => ['quantity' => 100, 'margem' => 500.0, 'doh' => 10.0],
    ])))->score(
        collect([scorerProduct($id)]),
        scorerSettings(new ScoringWeightsValue(0.0, 0.0, 0.0, 0.0, 4)),
    );

    expect($result->first()->score)->toBe(0.0);
});
