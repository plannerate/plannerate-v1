<?php

/**
 * Regressão: zona térmica com shelf_position em coordenada de cm.
 *
 * No banco real, shelf_position é a distância em cm a partir do topo (0, 60, 120, 180) —
 * não um índice 0..N-1. O engine deve converter para índice ordenado antes de resolver
 * a zona, senão toda prateleira (exceto o topo) cai em Low/cold e a priorização de
 * zona quente nunca é aplicada.
 */

use App\Enums\ZonePriority;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductOrderingService;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ───────────────────────────────────────────────────────────────────

function makeZoneEngine(ZonePriority $hot, ZonePriority $cold, array $zoneMetricsMap): TemplatePlacementEngine
{
    $engine = new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );

    foreach (['hotZonePriority' => $hot, 'coldZonePriority' => $cold, 'zoneMetricsMap' => $zoneMetricsMap] as $prop => $value) {
        $ref = new ReflectionProperty($engine, $prop);
        $ref->setAccessible(true);
        $ref->setValue($engine, $value);
    }

    return $engine;
}

/**
 * Seção com 4 prateleiras em coordenadas reais de cm (0=topo … 180=chão).
 */
function makeZoneSectionCm(): Section
{
    $shelves = collect([0.0, 60.0, 120.0, 180.0])->map(function (float $pos): Shelf {
        $shelf = new Shelf;
        $shelf->id = (string) Str::ulid();
        $shelf->shelf_position = $pos;

        return $shelf;
    });

    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->width = 100.0;
    $section->cremalheira_width = 0.0;
    $section->setRelation('shelves', $shelves);

    return $section;
}

function makeZoneProduct(string $id): Product
{
    $product = new Product;
    $product->id = $id;
    $product->name = 'Produto '.$id;
    $product->ean = '7890000000000';
    $product->width = 10.0;
    $product->category_id = 'cat1';
    $product->status = 'published';

    return $product;
}

function callApplyZoneOrdering(TemplatePlacementEngine $engine, Collection $products, Section $section, Shelf $shelf): Collection
{
    $ref = new ReflectionMethod($engine, 'applyZoneOrdering');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $products, $section, $shelf);
}

function callResolveZoneForShelf(TemplatePlacementEngine $engine, Section $section, Shelf $shelf): string
{
    $ref = new ReflectionMethod($engine, 'resolveZoneForShelf');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $section, $shelf);
}

// ── testes ────────────────────────────────────────────────────────────────────

test('prateleiras centrais em coordenadas cm são zona quente; topo e chão zona fria', function (): void {
    $engine = makeZoneEngine(ZonePriority::None, ZonePriority::None, []);
    $section = makeZoneSectionCm();
    $shelves = $section->shelves->values();

    // 4 prateleiras: índice 0 (pos 0cm, topo) = High/cold; 1 (60cm) = Eye/hot;
    // 2 (120cm) = Hand/hot; 3 (180cm, chão) = Low/cold
    expect(callResolveZoneForShelf($engine, $section, $shelves[0]))->toBe('cold')
        ->and(callResolveZoneForShelf($engine, $section, $shelves[1]))->toBe('hot')
        ->and(callResolveZoneForShelf($engine, $section, $shelves[2]))->toBe('hot')
        ->and(callResolveZoneForShelf($engine, $section, $shelves[3]))->toBe('cold');
});

test('priorização de zona quente é aplicada em prateleira central com posição em cm', function (): void {
    $metricas = [
        'p-baixa-margem' => ['giro' => 0.0, 'margem' => 10.0],
        'p-alta-margem' => ['giro' => 0.0, 'margem' => 99.0],
    ];

    $engine = makeZoneEngine(ZonePriority::MaiorMargem, ZonePriority::None, $metricas);
    $section = makeZoneSectionCm();
    $hotShelf = $section->shelves->values()[1]; // 60cm = Eye = hot

    $products = collect([
        makeZoneProduct('p-baixa-margem'),
        makeZoneProduct('p-alta-margem'),
    ]);

    $ordered = callApplyZoneOrdering($engine, $products, $section, $hotShelf);

    // Antes da correção, pos=60 era tratado como índice → Low/cold → MaiorMargem nunca aplicava
    expect($ordered->first()->id)->toBe('p-alta-margem');
});

test('priorização de zona fria é aplicada no chão com posição em cm', function (): void {
    $metricas = [
        'p-baixa-margem' => ['giro' => 0.0, 'margem' => 10.0],
        'p-alta-margem' => ['giro' => 0.0, 'margem' => 99.0],
    ];

    $engine = makeZoneEngine(ZonePriority::None, ZonePriority::MenorMargem, $metricas);
    $section = makeZoneSectionCm();
    $coldShelf = $section->shelves->values()[3]; // 180cm = chão = cold

    $products = collect([
        makeZoneProduct('p-alta-margem'),
        makeZoneProduct('p-baixa-margem'),
    ]);

    $ordered = callApplyZoneOrdering($engine, $products, $section, $coldShelf);

    expect($ordered->first()->id)->toBe('p-baixa-margem');
});
