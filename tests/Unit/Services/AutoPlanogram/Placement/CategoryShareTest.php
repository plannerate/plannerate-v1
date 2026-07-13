<?php

/**
 * Repartição do sortimento entre os slots da mesma categoria.
 *
 * O plano de slots dá N prateleiras a uma categoria (dimensionando pela largura total dos
 * produtos), mas o motor colocava TODOS os produtos no primeiro slot — porque com a frente
 * mínima eles cabem numa prateleira só. Os N−1 slots seguintes ficavam vazios.
 *
 * Numa gôndola real: 7 de 16 prateleiras zeradas, 39,7% de ocupação, com o mix inteiro cabendo.
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

function shareEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

function shareProduct(float $width, string $name): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = $name;
    $product->width = $width;
    $product->status = 'published';

    return $product;
}

/**
 * Chama takeCategoryShare (private) via reflection.
 */
function takeShare(Collection $ordered, int $pendingSlots): Collection
{
    $slot = new PlanogramTemplateSlot;
    $slot->category_id = (string) Str::ulid();

    $method = new ReflectionMethod(TemplatePlacementEngine::class, 'takeCategoryShare');
    $method->setAccessible(true);

    return $method->invoke(shareEngine(), $ordered, $slot, $pendingSlots);
}

test('slot único da categoria leva o sortimento inteiro', function (): void {
    $products = collect([
        shareProduct(10.0, 'A'),
        shareProduct(10.0, 'B'),
        shareProduct(10.0, 'C'),
    ]);

    expect(takeShare($products, 1))->toHaveCount(3);
});

test('categoria com 3 slots reparte o sortimento em três fatias, sem deixar slot vazio', function (): void {
    // 12 produtos de 10cm = 120cm. Com 3 slots, cada fatia mira 40cm ≈ 4 produtos.
    $products = collect(array_map(
        fn (int $i): Product => shareProduct(10.0, "P{$i}"),
        range(1, 12),
    ));

    $primeira = takeShare($products, 3);

    // A fatia do 1º slot cobre ~1/3 e sobra sortimento para os outros dois.
    expect($primeira->count())->toBeGreaterThanOrEqual(3)
        ->and($primeira->count())->toBeLessThanOrEqual(5)
        ->and($primeira->count())->toBeLessThan($products->count());

    // O que sobrou vai para os slots seguintes (o motor os reencontra via findCandidates).
    $restante = $products->reject(fn (Product $p): bool => $primeira->contains('id', $p->id))->values();
    $segunda = takeShare($restante, 2);

    expect($segunda->count())->toBeLessThan($restante->count());

    // O ÚLTIMO slot leva tudo o que sobrou — ninguém fica para trás.
    $sobra = $restante->reject(fn (Product $p): bool => $segunda->contains('id', $p->id))->values();
    $terceira = takeShare($sobra, 1);

    expect($terceira->count())->toBe($sobra->count())
        // Nenhuma fatia vazia, e a soma cobre o sortimento inteiro.
        ->and($primeira->count() + $segunda->count() + $terceira->count())->toBe(12)
        ->and($primeira)->not->toBeEmpty()
        ->and($segunda)->not->toBeEmpty()
        ->and($terceira)->not->toBeEmpty();
});

test('a fatia respeita a LARGURA, não a contagem: produtos largos formam fatias menores', function (): void {
    // 1 produto de 60cm + 6 de 10cm = 120cm. Com 2 slots, o alvo é 60cm:
    // o produto largo sozinho já cumpre a cota do primeiro slot.
    $largo = shareProduct(60.0, 'LARGO');
    $products = collect([$largo]);

    foreach (range(1, 6) as $i) {
        $products->push(shareProduct(10.0, "P{$i}"));
    }

    $primeira = takeShare($products, 2);

    expect($primeira)->toHaveCount(1)
        ->and($primeira->first()->id)->toBe($largo->id);
});

test('categoria com mais slots que produtos não devolve fatia vazia', function (): void {
    // 2 produtos e 4 slots: os dois primeiros slots levam 1 cada; os outros ficam sem
    // candidato (o motor os marca como vazios) — mas nenhuma fatia pode sair vazia aqui,
    // senão a prateleira ficaria zerada com produto disponível na mão.
    $products = collect([shareProduct(10.0, 'A'), shareProduct(10.0, 'B')]);

    expect(takeShare($products, 4))->toHaveCount(1);
});
