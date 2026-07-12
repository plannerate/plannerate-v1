<?php

/**
 * O empacotador exato dentro do motor de verdade (distributeInShelf).
 *
 * Os testes do ShelfKnapsackPackerTest provam o algoritmo isolado. Estes provam que ele está
 * de fato ligado no caminho que gera as gôndolas — e medem, lado a lado, o que ele ganha
 * contra o motor guloso antigo (config `packer` = greedy).
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\SpaceFallback;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ───────────────────────────────────────────────────────────────────

function packerEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

function packerProduct(float $width, string $name): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = $name;
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->height = 10.0;
    $product->status = 'published';
    $product->category_id = (string) Str::ulid();

    return $product;
}

function packerShelf(): Shelf
{
    $shelf = new Shelf;
    $shelf->id = (string) Str::ulid();
    $shelf->shelf_position = 0;
    $shelf->shelf_depth = 40;
    $shelf->shelf_height = 4;

    return $shelf;
}

function packerSection(Shelf $shelf): Section
{
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->width = 100.0;
    $section->cremalheira_width = 0.0;
    $section->setRelation('shelves', collect([$shelf]));

    return $section;
}

function packerSlot(
    int $minFacings = 1,
    int $maxFacings = 12,
    FacingExpansion $expansion = FacingExpansion::Equal,
    ?SpaceFallback $spaceFallback = null,
): PlanogramTemplateSlot {
    $slot = new PlanogramTemplateSlot;
    $slot->min_facings = $minFacings;
    $slot->max_facings = $maxFacings;
    $slot->facing_expansion = $expansion;
    $slot->space_fallback = $spaceFallback;
    $slot->use_target_stock = false;
    $slot->max_share_per_sku = null;
    $slot->max_share_per_brand = null;
    $slot->max_share_per_subcategory = null;

    return $slot;
}

/**
 * Roda distributeInShelf (private) e devolve o resultado + a largura total ocupada.
 *
 * @return array{placed: Collection<int, PlacedSegment>, rejected: Collection<int, array>, occupied: int}
 */
function distribute(Collection $products, PlanogramTemplateSlot $slot, float $available = 100.0): array
{
    $shelf = packerShelf();
    $section = packerSection($shelf);

    $method = new ReflectionMethod(packerEngine(), 'distributeInShelf');
    $method->setAccessible(true);

    $result = $method->invoke(packerEngine(), $products, $section, $shelf, $slot, $available, 0, null);

    return [
        'placed' => $result['placed'],
        'rejected' => $result['rejected'],
        'occupied' => (int) $result['placed']->sum(fn (PlacedSegment $segment): int => $segment->width),
    ];
}

// ── o ganho de precisão, medido no motor ──────────────────────────────────────

test('empacotador fecha a prateleira exata onde o round-robin guloso deixava 3cm de vão', function (): void {
    /*
     * Prateleira de 100cm com produtos de 7cm e 11cm.
     *
     * Guloso: coloca os dois com 1 frente (18cm) e vai alternando +1 frente até travar em
     *   6×7 + 5×11 = 97cm — a partir dali nem 7 nem 11 cabem nos 3cm que sobraram, e não há
     *   como voltar atrás nas frentes já dadas.
     * Empacotador: enxerga a prateleira inteira e acha 8×7 + 4×11 = 100cm — encaixe EXATO.
     */
    $products = collect([
        packerProduct(7.0, 'Estreito'),
        packerProduct(11.0, 'Largo'),
    ]);

    config()->set('plannerate.auto_planogram.placement.packer', 'greedy');
    $greedy = distribute($products, packerSlot());

    config()->set('plannerate.auto_planogram.placement.packer', 'knapsack');
    $knapsack = distribute($products, packerSlot());

    expect($greedy['occupied'])->toBe(97)
        ->and($knapsack['occupied'])->toBe(100)
        ->and($knapsack['placed'])->toHaveCount(2);
});

test('empacotador não perde nenhum SKU que o guloso colocaria', function (): void {
    // Mix heterogêneo — o tipo de prateleira onde o guloso mais deixa sobra.
    $products = collect([
        packerProduct(13.5, 'A'),
        packerProduct(6.2, 'B'),
        packerProduct(21.0, 'C'),
        packerProduct(4.8, 'D'),
        packerProduct(9.9, 'E'),
    ]);

    config()->set('plannerate.auto_planogram.placement.packer', 'greedy');
    $greedy = distribute($products, packerSlot(maxFacings: 4));

    config()->set('plannerate.auto_planogram.placement.packer', 'knapsack');
    $knapsack = distribute($products, packerSlot(maxFacings: 4));

    $placedIds = fn (Collection $placed): array => $placed
        ->flatMap(fn (PlacedSegment $segment) => $segment->layers->pluck('productId'))
        ->sort()
        ->values()
        ->all();

    // Não-regressão: todo produto que o guloso colocaria continua na prateleira…
    expect($placedIds($knapsack['placed']))->toBe($placedIds($greedy['placed']))
        // …e a prateleira fica pelo menos tão cheia quanto antes.
        ->and($knapsack['occupied'])->toBeGreaterThanOrEqual($greedy['occupied']);
});

test('empacotador nunca ultrapassa a largura da prateleira', function (): void {
    $products = collect([
        packerProduct(17.3, 'A'),
        packerProduct(8.4, 'B'),
        packerProduct(11.9, 'C'),
        packerProduct(23.1, 'D'),
    ]);

    $result = distribute($products, packerSlot(maxFacings: 6), available: 100.0);

    $end = $result['placed']->max(fn (PlacedSegment $segment): int => $segment->position + $segment->width);

    expect($result['occupied'])->toBeLessThanOrEqual(100)
        ->and($end)->toBeLessThanOrEqual(100);
});

test('segmentos saem contíguos, sem sobreposição nem buraco entre eles', function (): void {
    $products = collect([
        packerProduct(7.3, 'A'),
        packerProduct(11.7, 'B'),
        packerProduct(5.1, 'C'),
    ]);

    $result = distribute($products, packerSlot(maxFacings: 5));

    $segments = $result['placed']->sortBy('position')->values();
    $cursor = 0;

    foreach ($segments as $segment) {
        expect($segment->position)->toBe($cursor);
        $cursor = $segment->position + $segment->width;
    }
});

test('em prateleiras sortidas, o empacotador nunca ocupa menos que o guloso', function (): void {
    /*
     * Propriedade que sustenta a troca do motor: a solução do guloso é sempre viável no espaço
     * de busca do empacotador (os produtos que ele colocaria entram como obrigatórios), e o DP
     * devolve a de maior valor — com a ocupação como termo do valor. Logo, empacotador >= guloso,
     * prateleira a prateleira. Se este teste falhar, a garantia de não-regressão quebrou.
     *
     * Semente fixa: sortido, mas reproduzível.
     */
    mt_srand(42);

    $shelfWidths = [100.0, 130.0, 90.0, 120.0];

    foreach ([3, 4, 6, 12] as $maxFacings) {
        for ($trial = 0; $trial < 15; $trial++) {
            $available = $shelfWidths[$trial % count($shelfWidths)];
            $products = collect();

            // 4 a 12 SKUs com larguras típicas de supermercado (4,0 a 28,0 cm)
            for ($i = 0; $i < 4 + ($trial % 9); $i++) {
                $products->push(packerProduct(round(4.0 + mt_rand(0, 240) / 10, 1), "P{$i}"));
            }

            $slot = packerSlot(maxFacings: $maxFacings);

            config()->set('plannerate.auto_planogram.placement.packer', 'greedy');
            $greedy = distribute($products, $slot, $available);

            config()->set('plannerate.auto_planogram.placement.packer', 'knapsack');
            $knapsack = distribute($products, $slot, $available);

            expect($knapsack['occupied'])->toBeGreaterThanOrEqual($greedy['occupied'])
                ->and($knapsack['occupied'])->toBeLessThanOrEqual((int) $available);
        }
    }
});

// ── fallback reduce_facings: o bug de sobreposição que existia ─────────────────

test('reduce_facings: produto que não cabia com a frente mínima entra com 1 frente, sem sobrepor os outros', function (): void {
    /*
     * Frente mínima 2. A e B ocupam 2×22 = 88cm; sobram 12cm — menos que os 16cm que C
     * precisaria com 2 frentes. Com `reduce_facings`, C entra com 1 frente (8cm).
     *
     * A versão anterior colocava esse produto a partir de x=0, SOBREPONDO os já posicionados
     * (ele era montado fora da esteira que cuida do cursor). Aqui exigimos o contrário:
     * ele entra depois dos outros, contíguo.
     */
    $products = collect([
        packerProduct(22.0, 'A'),
        packerProduct(22.0, 'B'),
        packerProduct(8.0, 'C'),
    ]);

    $slot = packerSlot(minFacings: 2, maxFacings: 2, spaceFallback: SpaceFallback::ReduceFacings);
    $result = distribute($products, $slot);

    $positions = $result['placed']->pluck('position')->sort()->values()->all();

    expect($result['placed'])->toHaveCount(3)
        ->and($positions)->toBe([0, 44, 88])   // contíguos: 44 + 44 + 8
        ->and($result['occupied'])->toBe(96)
        ->and($result['rejected'])->toBeEmpty();
});
