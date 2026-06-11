<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\CompositeScorer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\SalesMetricsRepository;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * O scorer real consulta product_strategic_flags (loadStrategicIds) e
 * scoring_weights (resolveWeights) na conexão tenant. As tabelas são criadas
 * vazias: flags vazias = nenhum estratégico; weights vazios = default().
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

    Schema::connection('tenant')->create('scoring_weights', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->float('w_giro')->default(0.4);
        $table->float('w_margem')->default(0.3);
        $table->float('w_doh')->default(0.2);
        $table->float('w_estrategia')->default(0.1);
        $table->timestamps();
        $table->softDeletes();
    });
});

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

test('Cenário 2: categoria com 1 SKU — giro log-transform dá 1.0 ao máximo e minMax degenera em 0.0', function () {
    $id = (string) Str::ulid();

    $result = (new CompositeScorer(stubSalesRepo([
        $id => ['quantity' => 99, 'margem' => 999.0, 'doh' => null],
    ])))->score(
        collect([scorerProduct($id)]),
        scorerSettings(),
    );

    // Normalização atual: giro usa log(q+1)/log(qMax+1) → produto máximo = 1.0;
    // margem usa minMax, que devolve 0.0 quando min == max (guarda de divisão por zero).
    expect($result)->toHaveCount(1)
        ->and($result->first()->metadata['giro_norm'])->toBe(1.0)
        ->and($result->first()->metadata['margem_norm'])->toBe(0.0);
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

// ── scoreOrNeutral ────────────────────────────────────────────────────────────

test('scoreOrNeutral com dados de venda retorna scores normais (não neutro)', function () {
    $idA = (string) Str::ulid();
    $idB = (string) Str::ulid();

    $metrics = [
        $idA => ['quantity' => 100, 'margem' => 500.0, 'doh' => null],
        $idB => ['quantity' => 10,  'margem' => 50.0,  'doh' => null],
    ];

    $result = (new CompositeScorer(stubSalesRepo($metrics)))->scoreOrNeutral(
        collect([scorerProduct($idA), scorerProduct($idB)]),
        scorerSettings(),
    );

    expect($result)->toHaveCount(2)
        ->and($result->first()->score)->toBeGreaterThan(0.0)
        ->and($result->first()->metadata['score_type'])->toBe('composite');
});

test('scoreOrNeutral sem dados de venda retorna score 0.5 para todos', function () {
    $idA = (string) Str::ulid();
    $idB = (string) Str::ulid();

    $metrics = [
        $idA => ['quantity' => 0, 'margem' => 0.0, 'doh' => null],
        $idB => ['quantity' => 0, 'margem' => 0.0, 'doh' => null],
    ];

    $result = (new CompositeScorer(stubSalesRepo($metrics)))->scoreOrNeutral(
        collect([scorerProduct($idA), scorerProduct($idB)]),
        scorerSettings(),
    );

    expect($result)->toHaveCount(2);
    $result->each(fn ($sp) => expect($sp->score)->toBe(0.5));
});

test('scoreOrNeutral sem dados define metadata score_type como neutral', function () {
    $id = (string) Str::ulid();

    $metrics = [$id => ['quantity' => 0, 'margem' => 0.0, 'doh' => null]];

    $result = (new CompositeScorer(stubSalesRepo($metrics)))->scoreOrNeutral(
        collect([scorerProduct($id)]),
        scorerSettings(),
    );

    expect($result->first()->metadata['score_type'])->toBe('neutral');
});

test('score sem dados retorna apenas a contribuição neutra de DOH para todos', function () {
    $idA = (string) Str::ulid();
    $idB = (string) Str::ulid();

    $metrics = [
        $idA => ['quantity' => 0, 'margem' => 0.0, 'doh' => null],
        $idB => ['quantity' => 0, 'margem' => 0.0, 'doh' => null],
    ];

    $result = (new CompositeScorer(stubSalesRepo($metrics)))->score(
        collect([scorerProduct($idA), scorerProduct($idB)]),
        scorerSettings(),
    );

    // Sem venda: giro/margem/estratégico zeram; resta (1 - doh_norm 0.5) * peso doh 0.10 = 0.05
    // — piso igual para todos (ranking estável). O caminho neutro 0.5 é papel do scoreOrNeutral.
    expect($result)->toHaveCount(2);
    $result->each(fn ($sp) => expect($sp->score)->toBe(0.05));
});
