<?php

use App\Services\AutoPlanogram\AutoPlanogramService;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Placement\PlanogramWriterInterface;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Testes de regressão estrutural do AutoPlanogramService (Fase 1).
 *
 * Valida que o pipeline produz PlacedSegments com a estrutura correta para
 * 3 tamanhos de fixture: pequena, médio e grande.
 *
 * O PlanogramWriter é mockado para isolar o pipeline da camada de persistência.
 * O scorer é mockado para evitar dependência de dados de vendas reais.
 */

// ── Factories em memória ─────────────────────────────────────────────────────

/**
 * Cria um produto sintético sem persistir no banco.
 */
function fakeProduct(int $index, string $tenantId = 'tenant-test'): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->tenant_id = $tenantId;
    $product->name = "Produto {$index}";
    $product->ean = str_pad((string) ($index + 1000), 13, '0', STR_PAD_LEFT);
    $product->codigo_erp = "PROD{$index}";
    $product->width = 10;
    $product->height = 25;
    $product->depth = 30;

    return $product;
}

/**
 * Cria uma shelf sintética sem persistir no banco.
 */
function fakeShelf(string $sectionId, int $index): Shelf
{
    $shelf = new Shelf;
    $shelf->id = (string) Str::ulid();
    $shelf->section_id = $sectionId;
    $shelf->shelf_width = 100;
    $shelf->shelf_height = 30;
    $shelf->shelf_depth = 40;
    $shelf->ordering = $index;

    return $shelf;
}

/**
 * Cria uma section sintética com shelves sem persistir no banco.
 */
function fakeSection(string $gondolaId, int $numShelves, int $widthCm = 100): Section
{
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->gondola_id = $gondolaId;
    $section->width = $widthCm;
    $section->height = 200;
    $section->cremalheira_width = 0;

    $shelves = collect();
    for ($i = 0; $i < $numShelves; $i++) {
        $shelves->push(fakeShelf($section->id, $i));
    }

    // Injeta relação carregada sem query
    $section->setRelation('shelves', $shelves);

    return $section;
}

/**
 * Cria um scorer mockado com scores decrescentes.
 *
 * @param  Collection<int, Product>  $products
 */
function fakeScorer(Collection $products): ProductScorerInterface
{
    $abcCycle = ['A', 'A', 'B', 'B', 'C'];

    $scored = $products->values()->map(fn (Product $p, int $i) => new ScoredProduct(
        productId: $p->id,
        ean: (string) ($p->ean ?? ''),
        score: 100.0 - $i * 5.0,
        product: $p,
        metadata: [
            'abc_class' => $abcCycle[$i % count($abcCycle)],
            'sales_total' => 1000 - $i * 50,
            'margin' => 200 - $i * 10,
            'target_stock' => null,
            'safety_stock' => null,
        ],
    ));

    return new class($scored) implements ProductScorerInterface
    {
        public function __construct(private readonly Collection $scored) {}

        public function score(Collection $products, PlacementSettings $settings): Collection
        {
            return $this->scored;
        }
    };
}

/**
 * Cria um PlanogramInput completo a partir de sections e products sintéticos.
 *
 * @param  Collection<int, Section>  $sections
 * @param  Collection<int, Product>  $products
 */
function buildTestInput(Collection $sections, Collection $products): PlanogramInput
{
    return new PlanogramInput(
        planogramId: (string) Str::ulid(),
        gondolaId: (string) Str::ulid(),
        tenantId: 'tenant-test',
        products: $products,
        sections: $sections,
        settings: new PlacementSettings(
            strategy: 'abc',
            useExistingAnalysis: false,
            startDate: null,
            endDate: null,
            minFacings: 1,
            maxFacings: 3,
            groupBySubcategory: false,
            includeProductsWithoutSales: true,
        ),
    );
}

/**
 * Executa o pipeline com Writer mockado, retornando os PlacedSegments capturados.
 *
 * @param  Collection<int, Section>  $sections
 * @param  Collection<int, Product>  $products
 * @return Collection<int, PlacedSegment>
 */
function runPipelineWithMockedWriter(Collection $sections, Collection $products): Collection
{
    $capturedSegments = collect();

    $mockWriter = new class($capturedSegments) implements PlanogramWriterInterface
    {
        public function __construct(private Collection &$captured) {}

        public function write(string $gondolaId, Collection $sections, Collection $placedSegments): void
        {
            foreach ($placedSegments as $seg) {
                $this->captured->push($seg);
            }
        }
    };

    app()->bind(ProductScorerInterface::class, fn () => fakeScorer($products));
    app()->instance(PlanogramWriterInterface::class, $mockWriter);

    app(AutoPlanogramService::class)->generate(buildTestInput($sections, $products));

    return $capturedSegments;
}

// ── Fixtures ─────────────────────────────────────────────────────────────────

dataset('fixtures', [
    'pequena (1 section, 2 shelves, 3 produtos)' => [
        fn () => collect([fakeSection('g1', 2, 100)]),
        fn () => collect(array_map(fn ($i) => fakeProduct($i), range(0, 2))),
    ],
    'média (2 sections, 5 shelves, 6 produtos)' => [
        fn () => collect([fakeSection('g2', 3, 100), fakeSection('g2', 2, 100)]),
        fn () => collect(array_map(fn ($i) => fakeProduct($i), range(0, 5))),
    ],
    'grande (3 sections, 10 shelves, 12 produtos)' => [
        fn () => collect([fakeSection('g3', 4, 150), fakeSection('g3', 3, 150), fakeSection('g3', 3, 150)]),
        fn () => collect(array_map(fn ($i) => fakeProduct($i), range(0, 11))),
    ],
]);

// ── Testes ───────────────────────────────────────────────────────────────────

test(
    'pipeline produz segmentos não-vazios',
    function (Collection $sections, Collection $products): void {
        $segments = runPipelineWithMockedWriter($sections, $products);

        expect($segments)->not->toBeEmpty();
    }
)->with('fixtures')->group('regression');

test(
    'cada segmento tem exatamente uma layer',
    function (Collection $sections, Collection $products): void {
        $segments = runPipelineWithMockedWriter($sections, $products);

        $segments->each(fn (PlacedSegment $s) => expect($s->layers)->toHaveCount(1));
    }
)->with('fixtures')->group('regression');

test(
    'ordering dos segmentos é sequencial por shelf (começa em 0)',
    function (Collection $sections, Collection $products): void {
        $segments = runPipelineWithMockedWriter($sections, $products);

        $shelfIds = $segments->pluck('shelfId')->unique();

        $shelfIds->each(function (string $shelfId) use ($segments): void {
            $orderings = $segments
                ->where('shelfId', $shelfId)
                ->sortBy('ordering')
                ->pluck('ordering')
                ->values();

            expect($orderings->first())->toBe(0);

            for ($i = 1; $i < $orderings->count(); $i++) {
                expect($orderings[$i])->toBe($orderings[$i - 1] + 1);
            }
        });
    }
)->with('fixtures')->group('regression');

test(
    'nenhum segmento excede a largura da shelf',
    function (Collection $sections, Collection $products): void {
        $shelfWidthMap = $sections
            ->flatMap(fn (Section $s) => $s->shelves->map(fn (Shelf $sh) => [$sh->id => $sh->shelf_width]))
            ->collapse();

        $segments = runPipelineWithMockedWriter($sections, $products);

        $groupedByShelf = $segments->groupBy('shelfId');

        $groupedByShelf->each(function (Collection $shelfSegments, string $shelfId) use ($shelfWidthMap): void {
            $totalWidth = $shelfSegments->sum('width');
            $availableWidth = $shelfWidthMap[$shelfId] ?? PHP_INT_MAX;

            expect($totalWidth)->toBeLessThanOrEqual($availableWidth);
        });
    }
)->with('fixtures')->group('regression');

test(
    'pipeline é determinístico — duas execuções produzem a mesma estrutura',
    function (Collection $sections, Collection $products): void {
        $first = runPipelineWithMockedWriter($sections, $products);
        $second = runPipelineWithMockedWriter($sections, $products);

        expect($second->count())->toBe($first->count());

        $first->each(function (PlacedSegment $seg, int $idx) use ($second): void {
            $other = $second[$idx];
            expect($seg->shelfId)->toBe($other->shelfId)
                ->and($seg->ordering)->toBe($other->ordering)
                ->and($seg->width)->toBe($other->width);
        });
    }
)->with('fixtures')->group('regression');
