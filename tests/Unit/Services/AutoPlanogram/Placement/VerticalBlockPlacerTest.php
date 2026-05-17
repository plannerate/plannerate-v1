<?php

use App\Enums\ShelfLevel;
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

/**
 * Cria uma section com N prateleiras posicionadas por Y-coordenada.
 * Com shelfSpacing=40, uma gôndola de 4 shelves fica:
 *   idx 0 (pos=40)  → HIGH
 *   idx 1 (pos=80)  → EYE
 *   idx 2 (pos=120) → HAND
 *   idx 3 (pos=160) → LOW
 */
function vbSection(int $numShelves = 4, int $widthCm = 100, int $shelfSpacing = 40): Section
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

// ── Testes comportamento principal ────────────────────────────────────────────

test('bloco vertical ocupa apenas prateleiras EYE e HAND — nunca HIGH ou LOW', function (): void {
    $placer = new VerticalBlockPlacer;
    // 4 shelves: idx 0=HIGH, idx 1=EYE, idx 2=HAND, idx 3=LOW
    $sections = collect([vbSection(numShelves: 4)]);
    $blocks = collect([vbOrderedBlock([vbProduct('P1', width: 10, isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    // Deve ter posicionado exatamente nas 2 prateleiras EYE+HAND
    expect($result->verticalSegments)->toHaveCount(2);

    $levels = $result->verticalSegments->pluck('shelfLevel');
    expect($levels->contains(ShelfLevel::Eye))->toBeTrue();
    expect($levels->contains(ShelfLevel::Hand))->toBeTrue();
    expect($levels->contains(ShelfLevel::High))->toBeFalse();
    expect($levels->contains(ShelfLevel::Low))->toBeFalse();
});

test('produto vertical: segmentos em EYE e HAND têm mesmo position X', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 4)]);
    $blocks = collect([vbOrderedBlock([vbProduct('P1', width: 10, isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->toHaveCount(2);

    $positions = $result->verticalSegments->pluck('position')->unique();
    expect($positions)->toHaveCount(1); // mesmo X em EYE e HAND
});

test('produto vertical é removido dos remainingBlocks', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 4)]);

    $verticalProduct = vbProduct('V1', isVertical: true);
    $normalProduct = vbProduct('N1', isVertical: false);
    $blocks = collect([vbOrderedBlock([$verticalProduct, $normalProduct])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->toHaveCount(2);

    $remainingIds = $result->remainingBlocks
        ->flatMap(fn ($b) => $b->block->children)
        ->pluck('productId')
        ->all();

    expect($remainingIds)->toContain('N1')
        ->and($remainingIds)->not->toContain('V1');
});

test('produto sem shelves EYE/HAND suficientes volta pro GreedyShelfPlacer', function (): void {
    $placer = new VerticalBlockPlacer;
    // 1 shelf: fromShelfPosition(0,1) → EYE (caso especial) — mas ainda < minShelves=2
    $sections = collect([vbSection(numShelves: 1)]);
    $blocks = collect([vbOrderedBlock([vbProduct('P1', isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->toHaveCount(0);

    $remainingChildren = $result->remainingBlocks->flatMap(fn ($b) => $b->block->children);
    expect($remainingChildren->pluck('productId')->all())->toContain('P1');
});

test('gôndola só com EYE e minShelves=2 → produto volta pro greedy', function (): void {
    $placer = new VerticalBlockPlacer;
    // 3 shelves: idx 0=HIGH, idx 1=EYE, idx 2=LOW — sem HAND → só 1 EYE < minShelves=2
    $sections = collect([vbSection(numShelves: 3)]);
    $blocks = collect([vbOrderedBlock([vbProduct('P1', isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->toHaveCount(0);

    $remainingChildren = $result->remainingBlocks->flatMap(fn ($b) => $b->block->children);
    expect($remainingChildren->pluck('productId')->all())->toContain('P1');
});

test('produto com height maior que clearance é ignorado em shelves sem espaço vertical', function (): void {
    $placer = new VerticalBlockPlacer;
    // Shelves espaçadas de 5cm — clearance EYE e HAND é ~1cm (muito pequeno)
    $section = vbSection(numShelves: 4, widthCm: 100, shelfSpacing: 5);
    $sections = collect([$section]);

    // Produto com 20cm de altura não cabe (clearance ~ 1cm)
    $blocks = collect([vbOrderedBlock([vbProduct('P1', height: 20, isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->toHaveCount(0);
});

test('dois candidatos verticais recebem positions X diferentes', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 4)]);
    $blocks = collect([
        vbOrderedBlock([
            vbProduct('V1', width: 10, isVertical: true),
            vbProduct('V2', width: 10, isVertical: true),
        ]),
    ]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    // 2 produtos × 2 shelves (EYE+HAND) = 4 segmentos
    expect($result->verticalSegments)->toHaveCount(4);

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
    $section = vbSection(numShelves: 4);
    $sections = collect([$section]);
    $blocks = collect([vbOrderedBlock([vbProduct('V1', width: 10, isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    // Apenas EYE e HAND reservadas (2 shelves × 10cm)
    expect($result->reservedWidthPerShelf)->toHaveCount(2);
    foreach ($result->reservedWidthPerShelf as $width) {
        expect($width)->toBe(10.0);
    }
});

test('segmentos verticais têm isVerticalBlock = true', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 4)]);
    $blocks = collect([vbOrderedBlock([vbProduct('V1', isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->not->toBeEmpty();
    expect($result->verticalSegments->every(fn ($s) => $s->isVerticalBlock))->toBeTrue();
});

test('segmentos verticais têm shelfLevel preenchido como EYE ou HAND', function (): void {
    $placer = new VerticalBlockPlacer;
    $sections = collect([vbSection(numShelves: 4)]);
    $blocks = collect([vbOrderedBlock([vbProduct('V1', isVertical: true)])]);

    $result = $placer->place($blocks, $sections, vbSettings(), minShelves: 2);

    expect($result->verticalSegments)->not->toBeEmpty();
    $result->verticalSegments->each(function ($s): void {
        expect($s->shelfLevel)->toBeIn([ShelfLevel::Eye, ShelfLevel::Hand]);
    });
});
