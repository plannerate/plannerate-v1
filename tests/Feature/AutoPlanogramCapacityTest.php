<?php

use App\Enums\PlacementFailureReason;
use App\Services\AutoPlanogram\AutoPlanogramService;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\FacingCalculatorService;
use App\Services\AutoPlanogram\Placement\PlanogramWriterInterface;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use App\Services\AutoPlanogram\Validation\PlanogramValidator;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── Helpers locais ────────────────────────────────────────────────────────────

function capacityProduct(int $index, float $width = 10.0): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = "Produto Capacity {$index}";
    $product->ean = str_pad((string) ($index + 5000), 13, '0', STR_PAD_LEFT);
    $product->width = $width;
    $product->height = 20;
    $product->depth = 30;

    return $product;
}

function capacityShelf(string $sectionId, int $index, int $widthCm = 100): Shelf
{
    $shelf = new Shelf;
    $shelf->id = (string) Str::ulid();
    $shelf->section_id = $sectionId;
    $shelf->shelf_width = $widthCm;
    $shelf->shelf_height = 4;
    $shelf->shelf_depth = 40;
    $shelf->ordering = $index;
    $shelf->shelf_position = 30 + ($index * 34);

    return $shelf;
}

function capacitySection(string $gondolaId, int $numShelves, int $widthCm = 100): Section
{
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->gondola_id = $gondolaId;
    $section->width = $widthCm;
    $section->height = 200;
    $section->cremalheira_width = 0;

    $shelves = collect();
    for ($i = 0; $i < $numShelves; $i++) {
        $shelves->push(capacityShelf($section->id, $i, $widthCm));
    }
    $section->setRelation('shelves', $shelves);

    return $section;
}

/**
 * Scorer que atribui scores descendentes: produto 0 tem score 100, produto N tem score 100 - N*10.
 * Isso garante que produtos de menor índice são os melhores e devem ser posicionados primeiro.
 *
 * @param  Collection<int, Product>  $products
 */
function capacityScorer(Collection $products): ProductScorerInterface
{
    $scored = $products->values()->map(fn (Product $p, int $i) => new ScoredProduct(
        productId: $p->id,
        ean: (string) ($p->ean ?? ''),
        score: 100.0 - $i * 10.0,
        product: $p,
        metadata: [
            'abc_class' => $i < 3 ? 'A' : ($i < 6 ? 'B' : 'C'),
            'sales_total' => 1000 - $i * 100,
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

function runCapacityPipeline(Collection $sections, Collection $products): PlanogramOutput
{
    $mockWriter = new class implements PlanogramWriterInterface
    {
        public function write(string $gondolaId, Collection $sections, Collection $placedSegments): void {}
    };

    app()->bind(ProductScorerInterface::class, fn () => capacityScorer($products));
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
        ),
    ));
}

// ── Testes ────────────────────────────────────────────────────────────────────

test('mix dentro da capacidade: nenhum produto rejeitado por espaço', function (): void {
    // 5 products × 10cm width × 1 facing = 50cm demand; gondola has 1 section × 1 shelf × 100cm
    $gondolaId = (string) Str::ulid();
    $sections = collect([capacitySection($gondolaId, numShelves: 1, widthCm: 100)]);
    $products = collect(range(0, 4))->map(fn ($i) => capacityProduct($i, width: 10.0));

    $output = runCapacityPipeline($sections, $products);

    $rejectedBySpace = $output->rejectedProducts
        ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::NoHorizontalSpace)
        ->count();

    expect($rejectedBySpace)->toBe(0)
        ->and($output->totalAllocated())->toBe(5);
});

test('mix excede capacidade: produtos de menor score são rejeitados', function (): void {
    // 10 products × 10cm width × 1 facing = 100cm demand; gondola has only 50cm
    $gondolaId = (string) Str::ulid();
    $sections = collect([capacitySection($gondolaId, numShelves: 1, widthCm: 50)]);
    $products = collect(range(0, 9))->map(fn ($i) => capacityProduct($i, width: 10.0));

    $output = runCapacityPipeline($sections, $products);

    $rejectedBySpace = $output->rejectedProducts
        ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::NoHorizontalSpace);

    expect($rejectedBySpace->count())->toBeGreaterThan(0)
        ->and($output->totalAllocated())->toBeGreaterThan(0)
        ->and($output->totalAllocated() + $rejectedBySpace->count())->toBeLessThanOrEqual($products->count());
});

test('produtos posicionados têm score maior que os rejeitados por espaço', function (): void {
    // 8 products × 10cm; gondola has only 40cm (fits ~4)
    $gondolaId = (string) Str::ulid();
    $sections = collect([capacitySection($gondolaId, numShelves: 1, widthCm: 40)]);
    $products = collect(range(0, 7))->map(fn ($i) => capacityProduct($i, width: 10.0));

    $output = runCapacityPipeline($sections, $products);

    $placedIds = $output->placedSegments->pluck('productId')->unique()->values();
    $rejectedIds = $output->rejectedProducts
        ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::NoHorizontalSpace)
        ->map(fn ($r) => $r['product']->id)
        ->values();

    // All placed products should come from the top-scored products (lower indices = higher score)
    // Products 0..N are scored 100,90,80,... so placed ones should have lower indices than rejected ones
    $maxPlacedIndex = $placedIds->map(function ($id) use ($products) {
        return $products->search(fn ($p) => $p->id === $id);
    })->max();

    $minRejectedIndex = $rejectedIds->map(function ($id) use ($products) {
        return $products->search(fn ($p) => $p->id === $id);
    })->min();

    expect($rejectedIds->count())->toBeGreaterThan(0)
        ->and($maxPlacedIndex)->toBeLessThan($minRejectedIndex);
});

test('scaleFacings exists in FacingCalculatorService but is not called by generate()', function (): void {
    $service = app(FacingCalculatorService::class);

    expect(method_exists($service, 'scaleFacings'))->toBeTrue();

    $reflection = new ReflectionClass(AutoPlanogramService::class);
    $generateSource = $reflection->getMethod('generate')->getFileName();
    $startLine = $reflection->getMethod('generate')->getStartLine();
    $endLine = $reflection->getMethod('generate')->getEndLine();

    $lines = file($generateSource);
    $methodBody = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

    expect($methodBody)->not->toContain('scaleFacings');
});
