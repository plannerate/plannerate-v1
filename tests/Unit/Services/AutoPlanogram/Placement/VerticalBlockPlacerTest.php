<?php

use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Placement\VerticalBlockPlacer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Str;

// ── Helpers ───────────────────────────────────────────────────────────────────

function vbProduct(string $id, float $width = 10.0, float $height = 20.0, bool $isVertical = true): ScoredProduct
{
    $product = new Product;
    $product->id = $id;
    $product->name = "VB Produto {$id}";
    $product->ean = str_pad($id, 13, '0', STR_PAD_LEFT);
    $product->width = $width;
    $product->height = $height;

    return new ScoredProduct(
        productId: $id,
        ean: str_pad($id, 13, '0', STR_PAD_LEFT),
        score: 100.0,
        product: $product,
        metadata: [
            'facing_final' => 1,
            'facing_ideal' => 1,
            'is_vertical_block' => $isVertical,
        ],
    );
}

function vbShelf(string $sectionId, int $position, int $widthCm = 100): Shelf
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

function vbSection(int $numShelves = 3, int $widthCm = 100, int $shelfSpacing = 40): Section
{
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->gondola_id = (string) Str::ulid();
    $section->width = $widthCm;
    $section->height = 200;
    $section->cremalheira_width = 0;

    $shelves = collect();
    for ($i = 0; $i < $numShelves; $i++) {
        // Start at $shelfSpacing so shelf 0 has clearance = shelfSpacing (not 0)
        $shelves->push(vbShelf($section->id, ($i + 1) * $shelfSpacing, $widthCm));
    }
    $section->setRelation('shelves', $shelves);

    return $section;
}

function vbOrderedBlock(array $products, int $order = 0): OrderedBlock
{
    $children = collect($products);
    $block = new ProductBlock(
        children: $children,
        aggregateScore: 100.0,
        groupingKey: 'test',
        totalWidthEstimate: $children->sum(fn ($p) => (float) ($p->product->width ?? 10)),
    );

    return new OrderedBlock(block: $block, sequenceOrder: $order);
}

function vbSettings(): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        minFacings: 1,
        maxFacings: 3,
        verticalBlockThreshold: 0.20,
        verticalBlockMinShelves: 2,
    );
}

// ── Testes ────────────────────────────────────────────────────────────────────

test('produto vertical em 3 shelves cria 3 segmentos com mesmo position X', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 3, widthCm: 100)]);
    $blocks = collect([vbOrderedBlock([vbProduct('P1', width: 10, isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->toHaveCount(3);

    $positions = $result->verticalSegments->pluck('position')->unique();
    expect($positions)->toHaveCount(1); // mesmo X em todas as prateleiras
});

test('produto vertical é removido dos remainingBlocks', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 3, widthCm: 100)]);

    $verticalProduct = vbProduct('V1', isVertical: true);
    $normalProduct = vbProduct('N1', isVertical: false);
    $blocks = collect([vbOrderedBlock([$verticalProduct, $normalProduct])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    // O segmento vertical foi posicionado
    expect($result->verticalSegments)->toHaveCount(3);

    // O produto normal permanece nos remainingBlocks
    $remainingChildren = $result->remainingBlocks->flatMap(fn ($b) => $b->block->children);
    $remainingIds = $remainingChildren->pluck('productId')->all();
    expect($remainingIds)->toContain('N1')
        ->and($remainingIds)->not->toContain('V1');
});

test('produto sem shelves suficientes é devolvido ao GreedyShelfPlacer', function (): void {
    $placer = new VerticalBlockPlacer;
    // Seção com apenas 1 shelf — não atinge minShelves=2
    $sections = collect([vbSection(numShelves: 1, widthCm: 100)]);
    $blocks = collect([vbOrderedBlock([vbProduct('P1', isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->toHaveCount(0);

    $remainingChildren = $result->remainingBlocks->flatMap(fn ($b) => $b->block->children);
    expect($remainingChildren->pluck('productId')->all())->toContain('P1');
});

test('produto com height maior que clearance é ignorado em shelves sem espaço vertical', function (): void {
    $placer = new VerticalBlockPlacer;
    // Shelves espaçadas de 5cm — clearance é ~5cm
    $section = vbSection(numShelves: 3, widthCm: 100, shelfSpacing: 5);
    $sections = collect([$section]);

    // Produto com 20cm de altura não cabe (clearance ~ 5cm)
    $blocks = collect([vbOrderedBlock([vbProduct('P1', height: 20, isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    // Nenhuma shelf tem clearance suficiente, produto vai pro greedy
    expect($result->verticalSegments)->toHaveCount(0);
});

test('dois candidatos verticais recebem positions X diferentes', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 3, widthCm: 100)]);
    $blocks = collect([
        vbOrderedBlock([
            vbProduct('V1', width: 10, isVertical: true),
            vbProduct('V2', width: 10, isVertical: true),
        ]),
    ]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->toHaveCount(6); // 2 produtos × 3 shelves

    $positionsV1 = $result->verticalSegments
        ->filter(fn ($s) => $s->layers->first()?->productId === 'V1')
        ->pluck('position')
        ->unique()
        ->all();
    $positionsV2 = $result->verticalSegments
        ->filter(fn ($s) => $s->layers->first()?->productId === 'V2')
        ->pluck('position')
        ->unique()
        ->all();

    expect($positionsV1)->not->toEqual($positionsV2);
});

test('reservedWidthPerShelf é preenchido corretamente após placement vertical', function (): void {
    $placer = new VerticalBlockPlacer;
    $section = vbSection(numShelves: 3, widthCm: 100);
    $sections = collect([$section]);
    $blocks = collect([vbOrderedBlock([vbProduct('V1', width: 10, isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    // Cada shelf elegível deve ter 10cm reservados
    expect($result->reservedWidthPerShelf)->not->toBeEmpty();
    foreach ($result->reservedWidthPerShelf as $width) {
        expect($width)->toBe(10.0);
    }
});

test('segmentos verticais têm isVerticalBlock = true', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 3, widthCm: 100)]);
    $blocks = collect([vbOrderedBlock([vbProduct('V1', isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->not->toBeEmpty();
    expect($result->verticalSegments->every(fn ($s) => $s->isVerticalBlock))->toBeTrue();
});
