<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\CompositeScorer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\SalesMetricsRepository;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * O scorer real consulta product_strategic_flags (loadStrategicIds) na conexão
 * tenant — tabela criada vazia (nenhum produto estratégico).
 */
beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('product_strategic_flags', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->char('product_id', 26);
        $table->boolean('is_strategic')->default(false);
        $table->string('reason')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

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

    // Pesos default (giro 0.4, margem 0.3): o log-transform do giro comprime a
    // vantagem do produto de alto giro (log(11)/log(101) ≈ 0.52), enquanto a
    // margem normalizada dá 1.0 cheio ao low => low vence com os defaults.
    $defaultWeights = ScoringWeightsValue::default();
    $resultDefault = (new CompositeScorer(featureSalesRepo($metrics)))
        ->score($products, featureSettings($defaultWeights));

    expect($resultDefault->first()->productId)->toBe($idLow);

    // Pesos giro-pesados (giro 0.9, margem 0.1): high vence — ranking mudou
    // em relação ao default, que é o contrato deste teste.
    $customWeights = new ScoringWeightsValue(
        giro: 0.90,
        margem: 0.10,
        estrategico: 0.0,
        doh: 0.0,
        salesWindowMonths: 4,
    );
    $resultCustom = (new CompositeScorer(featureSalesRepo($metrics)))
        ->score($products, featureSettings($customWeights));

    expect($resultCustom->first()->productId)->toBe($idHigh);
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
