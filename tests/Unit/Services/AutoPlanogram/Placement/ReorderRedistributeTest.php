<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AlterationClassifier;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\AlterationLevel;
use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\FlavorExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Illuminate\Support\Str;

// ── helpers ──────────────────────────────────────────────────────────────────

function makeOrderingService(): ProductOrderingService
{
    return new ProductOrderingService(new ProductSizeResolver);
}

function makeOrderingSlot(array $overrides = []): PlanogramTemplateSlot
{
    $slot = new PlanogramTemplateSlot;
    $slot->price_order = PriceOrder::None;
    $slot->size_order = SizeOrder::None;
    $slot->brand_exposure = BrandExposure::Horizontal;
    $slot->flavor_exposure = FlavorExposure::Horizontal;
    $slot->visual_criteria = null;

    foreach ($overrides as $key => $value) {
        $slot->$key = $value;
    }

    return $slot;
}

function makeOrderingProduct(string $id, float $price, string $brand = 'Marca A', float $width = 10.0): Product
{
    $p = new Product;
    $p->id = $id;
    $p->name = "Produto {$id}";
    $p->ean = '0000000000000';
    $p->price = $price;
    $p->brand = $brand;
    $p->width = $width;
    $p->category_id = (string) Str::ulid();
    $p->status = 'published';

    return $p;
}

// ── AlterationClassifier ─────────────────────────────────────────────────────

describe('AlterationClassifier', function (): void {

    test('visual_criteria mudado → Reorder', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['visual_criteria']))->toBe(AlterationLevel::Reorder);
    });

    test('price_order mudado → Reorder', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['price_order']))->toBe(AlterationLevel::Reorder);
    });

    test('brand_exposure mudado → Redistribute', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['brand_exposure']))->toBe(AlterationLevel::Redistribute);
    });

    test('flavor_exposure mudado → Redistribute', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['flavor_exposure']))->toBe(AlterationLevel::Redistribute);
    });

    test('category_id mudado → Regenerate', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['category_id']))->toBe(AlterationLevel::Regenerate);
    });

    test('min_facings mudado → Regenerate', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['min_facings']))->toBe(AlterationLevel::Regenerate);
    });

    test('Regenerate tem precedência sobre Redistribute e Reorder', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['visual_criteria', 'brand_exposure', 'category_id']))
            ->toBe(AlterationLevel::Regenerate);
    });

    test('Redistribute tem precedência sobre Reorder', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['price_order', 'brand_exposure']))
            ->toBe(AlterationLevel::Redistribute);
    });

    test('sem campos classificados → null', function (): void {
        $classifier = new AlterationClassifier;
        expect($classifier->classify(['algum_campo_desconhecido']))->toBeNull();
    });

    test('diffFields detecta campos alterados entre dois estados', function (): void {
        $classifier = new AlterationClassifier;
        $before = ['price_order' => 'asc', 'category_id' => 'cat-1', 'visual_criteria' => null];
        $after = ['price_order' => 'desc', 'category_id' => 'cat-1', 'visual_criteria' => null];

        $changed = $classifier->diffFields($before, $after);

        expect($changed)->toContain('price_order')
            ->and($changed)->not->toContain('category_id')
            ->and($changed)->not->toContain('visual_criteria');
    });

});

// ── ProductOrderingService ────────────────────────────────────────────────────

describe('ProductOrderingService', function (): void {

    test('visual_criteria=[preco desc] ordena por preço decrescente', function (): void {
        $slot = makeOrderingSlot([
            'visual_criteria' => [['key' => 'preco', 'direction' => 'desc']],
        ]);

        $products = collect([
            makeOrderingProduct('p1', 5.0),
            makeOrderingProduct('p2', 20.0),
            makeOrderingProduct('p3', 10.0),
        ]);

        $result = makeOrderingService()->orderBySlot($products, $slot);

        expect($result->pluck('id')->values()->toArray())->toBe(['p2', 'p3', 'p1']);
    });

    test('visual_criteria=[marca asc, preco desc] aplica cascata: marca domina', function (): void {
        $slot = makeOrderingSlot([
            'visual_criteria' => [
                ['key' => 'marca', 'direction' => 'asc'],
                ['key' => 'preco', 'direction' => 'desc'],
            ],
        ]);

        $products = collect([
            makeOrderingProduct('p1', 15.0, 'Marca B'),
            makeOrderingProduct('p2', 10.0, 'Marca A'),
            makeOrderingProduct('p3', 20.0, 'Marca A'),
        ]);

        $ids = makeOrderingService()->orderBySlot($products, $slot)->pluck('id')->values()->toArray();

        // Marca A primeiro (asc), dentro de A: maior preço (desc)
        expect($ids[0])->toBe('p3')
            ->and($ids[1])->toBe('p2')
            ->and($ids[2])->toBe('p1');
    });

    test('visual_criteria=null sem ordenação mantém ordem original', function (): void {
        $slot = makeOrderingSlot(['visual_criteria' => null]);

        $products = collect([
            makeOrderingProduct('p1', 5.0),
            makeOrderingProduct('p2', 20.0),
        ]);

        $result = makeOrderingService()->orderBySlot($products, $slot);

        expect($result->pluck('id')->values()->toArray())->toBe(['p1', 'p2']);
    });

    test('visual_criteria=null com price_order=asc usa legado', function (): void {
        $slot = makeOrderingSlot([
            'visual_criteria' => null,
            'price_order' => PriceOrder::Asc,
        ]);

        $products = collect([
            makeOrderingProduct('p1', 20.0),
            makeOrderingProduct('p2', 5.0),
        ]);

        $result = makeOrderingService()->orderBySlot($products, $slot);

        expect($result->pluck('id')->values()->toArray())->toBe(['p2', 'p1']);
    });

    test('applyExposureGrouping vertical agrupa produtos por marca', function (): void {
        $slot = makeOrderingSlot(['brand_exposure' => BrandExposure::Vertical]);

        $products = collect([
            makeOrderingProduct('p1', 1.0, 'Coca-Cola'),
            makeOrderingProduct('p2', 1.0, 'Pepsi'),
            makeOrderingProduct('p3', 1.0, 'Coca-Cola'),
        ]);

        $ids = makeOrderingService()->applyExposureGrouping($products, $slot)->pluck('id')->values()->toArray();

        $colaPositions = array_keys(array_filter($ids, fn ($id) => in_array($id, ['p1', 'p3'])));
        expect(abs($colaPositions[0] - $colaPositions[1]))->toBe(1);
    });

});

// ── Invariante Reorder: mesmos produtos e frentes, só ordering/position mudam ─

describe('Invariante Reorder', function (): void {

    test('reordenar não altera o conjunto de product_ids', function (): void {
        $slot = makeOrderingSlot([
            'visual_criteria' => [['key' => 'preco', 'direction' => 'asc']],
        ]);

        $products = collect([
            makeOrderingProduct('p1', 30.0),
            makeOrderingProduct('p2', 10.0),
            makeOrderingProduct('p3', 20.0),
        ]);

        $before = $products->pluck('id')->sort()->values()->toArray();
        $sorted = makeOrderingService()->orderBySlot($products, $slot);
        $after = $sorted->pluck('id')->sort()->values()->toArray();

        expect($after)->toBe($before);
    });

    test('reordenar não altera a contagem de produtos', function (): void {
        $slot = makeOrderingSlot([
            'visual_criteria' => [['key' => 'preco', 'direction' => 'desc']],
        ]);

        $products = collect([
            makeOrderingProduct('p1', 5.0),
            makeOrderingProduct('p2', 50.0),
            makeOrderingProduct('p3', 25.0),
        ]);

        $sorted = makeOrderingService()->orderBySlot($products, $slot);

        expect($sorted->count())->toBe($products->count());
    });

    test('reordenar muda a ordem quando critério difere da ordem atual', function (): void {
        $slot = makeOrderingSlot([
            'visual_criteria' => [['key' => 'preco', 'direction' => 'asc']],
        ]);

        $products = collect([
            makeOrderingProduct('p1', 30.0), // mais caro primeiro
            makeOrderingProduct('p2', 5.0),
            makeOrderingProduct('p3', 15.0),
        ]);

        $sorted = makeOrderingService()->orderBySlot($products, $slot);

        // Deve ordenar crescente: p2, p3, p1
        expect($sorted->pluck('id')->values()->toArray())->toBe(['p2', 'p3', 'p1']);
    });

});

// ── Invariante Redistribute: mesmo {produto: frentes}, posições mudam ─────────

describe('Invariante Redistribute', function (): void {

    test('redistribuir mantém exatamente os mesmos produtos', function (): void {
        $slot = makeOrderingSlot(['brand_exposure' => BrandExposure::Vertical]);

        $products = collect([
            makeOrderingProduct('p1', 1.0, 'Marca B'),
            makeOrderingProduct('p2', 1.0, 'Marca A'),
            makeOrderingProduct('p3', 1.0, 'Marca B'),
        ]);

        $before = $products->pluck('id')->sort()->values()->toArray();
        $redistributed = makeOrderingService()->applyExposureGrouping($products, $slot);
        $after = $redistributed->pluck('id')->sort()->values()->toArray();

        expect($after)->toBe($before);
    });

    test('redistribuir por marca vertical agrupa produtos da mesma marca adjacentes', function (): void {
        $slot = makeOrderingSlot(['brand_exposure' => BrandExposure::Vertical]);

        $products = collect([
            makeOrderingProduct('p1', 1.0, 'Marca B'),
            makeOrderingProduct('p2', 1.0, 'Marca A'),
            makeOrderingProduct('p3', 1.0, 'Marca B'),
        ]);

        $ids = makeOrderingService()->applyExposureGrouping($products, $slot)->pluck('id')->values()->toArray();

        $posP1 = array_search('p1', $ids);
        $posP3 = array_search('p3', $ids);
        expect(abs((int) $posP1 - (int) $posP3))->toBe(1);
    });

    test('redistribuir horizontal não altera ordenação original', function (): void {
        $slot = makeOrderingSlot(['brand_exposure' => BrandExposure::Horizontal]);

        $products = collect([
            makeOrderingProduct('p1', 1.0, 'Marca B'),
            makeOrderingProduct('p2', 1.0, 'Marca A'),
            makeOrderingProduct('p3', 1.0, 'Marca B'),
        ]);

        $ids = makeOrderingService()->applyExposureGrouping($products, $slot)->pluck('id')->values()->toArray();

        expect($ids)->toBe(['p1', 'p2', 'p3']);
    });

});

// ── AlterationLevel enum ──────────────────────────────────────────────────────

test('AlterationLevel labels corretos', function (): void {
    expect(AlterationLevel::Reorder->label())->toBe('Reordenando…')
        ->and(AlterationLevel::Redistribute->label())->toBe('Redistribuindo…')
        ->and(AlterationLevel::Regenerate->label())->toBe('Regerando planograma…');
});
