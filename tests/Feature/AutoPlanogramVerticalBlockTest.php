<?php

use App\Services\AutoPlanogram\AutoPlanogramService;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Placement\PlanogramWriterInterface;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use App\Services\AutoPlanogram\Validation\PlanogramValidator;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── Helpers locais ────────────────────────────────────────────────────────────

function vbFeatureProduct(int $index, float $width = 10.0, float $height = 20.0): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = "Feature VB {$index}";
    $product->ean = str_pad((string) ($index + 9000), 13, '0', STR_PAD_LEFT);
    $product->width = $width;
    $product->height = $height;

    return $product;
}

function vbFeatureShelf(string $sectionId, int $position, int $widthCm = 100): Shelf
{
    $shelf = new Shelf;
    $shelf->id = (string) Str::ulid();
    $shelf->section_id = $sectionId;
    $shelf->shelf_width = $widthCm;
    $shelf->shelf_height = 4;
    $shelf->shelf_depth = 40;
    $shelf->ordering = 0;
    $shelf->shelf_position = $position;

    return $shelf;
}

function vbFeatureSection(string $gondolaId, int $numShelves = 3, int $widthCm = 100): Section
{
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->gondola_id = $gondolaId;
    $section->width = $widthCm;
    $section->height = 200;
    $section->cremalheira_width = 0;

    $shelves = collect();
    for ($i = 0; $i < $numShelves; $i++) {
        $shelves->push(vbFeatureShelf($section->id, $i * 40, $widthCm));
    }
    $section->setRelation('shelves', $shelves);

    return $section;
}

/**
 * Scorer com scores decrescentes — produto 0 = score 100, produto N = score 100-N*10.
 *
 * @param  Collection<int, Product>  $products
 */
function vbFeatureScorer(Collection $products): ProductScorerInterface
{
    $abcCycle = ['A', 'A', 'B', 'B', 'C'];

    $scored = $products->values()->map(fn (Product $p, int $i) => new ScoredProduct(
        productId: $p->id,
        ean: (string) ($p->ean ?? ''),
        score: 100.0 - $i * 5.0,
        product: $p,
        metadata: [
            'abc_class' => $abcCycle[$i % count($abcCycle)],
            'sales_total' => 500 - $i * 10,
            'margin' => 0,
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

function runVbPipeline(Collection $sections, Collection $products, float $threshold = 0.20): PlanogramOutput
{
    $mockWriter = new class implements PlanogramWriterInterface
    {
        public function write(string $gondolaId, Collection $sections, Collection $placedSegments): void {}
    };

    app()->bind(ProductScorerInterface::class, fn () => vbFeatureScorer($products));
    app()->instance(PlanogramWriterInterface::class, $mockWriter);
    app()->instance(PlanogramValidator::class, new PlanogramValidator);

    return app(AutoPlanogramService::class)->generate(new PlanogramInput(
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
            maxFacings: 1,
            groupBySubcategory: false,
            includeProductsWithoutSales: true,
            verticalBlockThreshold: $threshold,
            verticalBlockMinShelves: 2,
        ),
    ));
}

// ── Testes ────────────────────────────────────────────────────────────────────

test('top 20% de 10 produtos gera blocos verticais', function (): void {
    // 10 products, top 20% = 2 candidates
    $gondolaId = (string) Str::ulid();
    $sections = collect([vbFeatureSection($gondolaId, numShelves: 3, widthCm: 200)]);
    $products = collect(range(0, 9))->map(fn ($i) => vbFeatureProduct($i));

    $output = runVbPipeline($sections, $products, threshold: 0.20);

    $verticalSegments = $output->placedSegments->filter(fn (PlacedSegment $s) => $s->isVerticalBlock);

    // 2 candidatos × 3 prateleiras = no mínimo 4 segmentos verticais (mínimo 2 prateleiras por candidato)
    expect($verticalSegments->count())->toBeGreaterThanOrEqual(4);
});

test('produtos verticais têm mesmo position X em shelves diferentes da mesma section', function (): void {
    $gondolaId = (string) Str::ulid();
    $sections = collect([vbFeatureSection($gondolaId, numShelves: 3, widthCm: 200)]);
    $products = collect(range(0, 4))->map(fn ($i) => vbFeatureProduct($i));

    $output = runVbPipeline($sections, $products, threshold: 0.20);

    $verticalSegments = $output->placedSegments->filter(fn (PlacedSegment $s) => $s->isVerticalBlock);

    // Para cada produto vertical, todos os segmentos na mesma section devem ter o mesmo position
    $productPositions = $verticalSegments->groupBy(fn (PlacedSegment $s) => $s->layers->first()?->productId);

    foreach ($productPositions as $productId => $segs) {
        $uniquePositions = $segs->pluck('position')->unique();
        expect($uniquePositions)->toHaveCount(1,
            "Produto {$productId} tem posições X diferentes em shelves distintas"
        );
    }
});

test('threshold 0 desativa blocos verticais', function (): void {
    $gondolaId = (string) Str::ulid();
    $sections = collect([vbFeatureSection($gondolaId, numShelves: 3, widthCm: 200)]);
    $products = collect(range(0, 9))->map(fn ($i) => vbFeatureProduct($i));

    $output = runVbPipeline($sections, $products, threshold: 0.0);

    $verticalSegments = $output->placedSegments->filter(fn (PlacedSegment $s) => $s->isVerticalBlock);

    expect($verticalSegments)->toHaveCount(0);
});

test('segmentos verticais e normais não se sobrepõem em position X', function (): void {
    $gondolaId = (string) Str::ulid();
    $sections = collect([vbFeatureSection($gondolaId, numShelves: 3, widthCm: 200)]);
    $products = collect(range(0, 9))->map(fn ($i) => vbFeatureProduct($i));

    $output = runVbPipeline($sections, $products, threshold: 0.20);

    // Agrupar por shelf_id
    $byShelf = $output->placedSegments->groupBy('shelfId');

    foreach ($byShelf as $shelfId => $shelfSegments) {
        $sorted = $shelfSegments->sortBy('position')->values();

        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = $sorted[$i - 1];
            $curr = $sorted[$i];

            // O início do segmento atual deve ser >= fim do segmento anterior
            $prevEnd = $prev->position + $prev->width;
            expect($curr->position)->toBeGreaterThanOrEqual($prevEnd,
                "Sobreposição na shelf {$shelfId}: segmento em pos {$curr->position} sobrepõe anterior em {$prev->position}+{$prev->width}"
            );
        }
    }
});
