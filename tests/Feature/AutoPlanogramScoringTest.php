<?php

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\Scoring\CompositeScorer;
use App\Services\AutoPlanogram\Scoring\SalesMetricsRepository;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── Helpers ──────────────────────────────────────────────────────────────────

function featureProduct(string $id): Product
{
    $p = new Product;
    $p->id = $id;
    $p->ean = '0000000000000';
    $p->codigo_erp = 'ERP-'.$id;
    $p->setRelation('category', null);

    return $p;
}

function featureSettings(?ScoringWeightsValue $weights = null, ?string $tenantId = null): PlacementSettings
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

function featureSalesRepo(array $metricsMap): SalesMetricsRepository
{
    return new class($metricsMap) extends SalesMetricsRepository
    {
        public function __construct(private readonly array $map) {}

        public function fetchMetrics(string $tenantId, Collection $productIds, int $windowMonths, ?string $storeId = null): array
        {
            return $this->map;
        }
    };
}

// ── Testes ───────────────────────────────────────────────────────────────────

test('tenant com pesos custom gera ranking diferente do default', function () {
    $idHigh = (string) Str::ulid();
    $idLow = (string) Str::ulid();

    // high tem giro alto, margem baixa
    // low tem giro baixo, margem alta
    $metrics = [
        $idHigh => ['quantity' => 100, 'margem' => 10.0,  'doh' => null],
        $idLow => ['quantity' => 10,  'margem' => 1000.0, 'doh' => null],
    ];

    $products = collect([featureProduct($idHigh), featureProduct($idLow)]);

    // Pesos default: giro 0.4 > margem 0.3 => high vence
    $defaultWeights = ScoringWeightsValue::default();
    $resultDefault = (new CompositeScorer(featureSalesRepo($metrics)))
        ->score($products, featureSettings($defaultWeights));

    expect($resultDefault->first()->productId)->toBe($idHigh);

    // Pesos invertidos: margem=0.9, giro=0.1 => low vence
    $customWeights = new ScoringWeightsValue(
        giro: 0.10,
        margem: 0.90,
        estrategico: 0.0,
        doh: 0.0,
        salesWindowMonths: 4,
    );
    $resultCustom = (new CompositeScorer(featureSalesRepo($metrics)))
        ->score($products, featureSettings($customWeights));

    expect($resultCustom->first()->productId)->toBe($idLow);
});

test('SalesMetricsRepository é consultado com a janela de meses correta', function () {
    $id = (string) Str::ulid();
    $capture = new stdClass;
    $capture->window = null;

    $repo = new class($id, $capture) extends SalesMetricsRepository
    {
        public function __construct(
            private readonly string $productId,
            private readonly stdClass $capture,
        ) {}

        public function fetchMetrics(string $tenantId, Collection $productIds, int $windowMonths, ?string $storeId = null): array
        {
            $this->capture->window = $windowMonths;

            return [
                $this->productId => ['quantity' => 10, 'margem' => 10.0, 'doh' => null],
            ];
        }
    };

    $customWindow = 6;
    $weights = new ScoringWeightsValue(0.40, 0.30, 0.20, 0.10, $customWindow);

    (new CompositeScorer($repo))->score(
        collect([featureProduct($id)]),
        featureSettings($weights),
    );

    expect($capture->window)->toBe($customWindow);
});
