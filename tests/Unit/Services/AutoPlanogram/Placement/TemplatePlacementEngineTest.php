<?php

use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ──────────────────────────────────────────────────────────────────

function makeSlotForGrouping(string $groupingNormalized): PlanogramTemplateSlot
{
    $slot = new PlanogramTemplateSlot;
    $slot->grouping_normalized = $groupingNormalized;
    $slot->grouping = strtoupper($groupingNormalized);

    return $slot;
}

function makeProductForGrouping(string $groupingNormalized, string $status = 'published'): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = "Produto {$groupingNormalized}";
    $product->ean = '7890000000000';
    $product->width = 10.0;
    $product->grouping_normalized = $groupingNormalized;
    $product->status = $status;

    return $product;
}

function makeFindCandidatesCallable(): Closure
{
    $engine = new TemplatePlacementEngine(
        new ProductWidthResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
    );

    $reflection = new ReflectionMethod($engine, 'findCandidates');
    $reflection->setAccessible(true);

    return fn (PlanogramTemplateSlot $slot, PlacementSettings $settings): Collection => $reflection->invoke($engine, $slot, $settings);
}

function makeSettings(Collection $products): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'sales',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        templateId: 'template-test',
        products: $products,
    );
}

// ── tests ─────────────────────────────────────────────────────────────────────

test('findCandidates inclui produto com grouping_normalized igual ao slot', function (): void {
    $findCandidates = makeFindCandidatesCallable();
    $product = makeProductForGrouping('cereais | biscoitos');
    $slot = makeSlotForGrouping('cereais | biscoitos');
    $settings = makeSettings(collect([$product]));

    $result = $findCandidates($slot, $settings);

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($product->id);
});

test('findCandidates exclui produto com grouping_normalized diferente', function (): void {
    $findCandidates = makeFindCandidatesCallable();
    $product = makeProductForGrouping('laticínios | queijos');
    $slot = makeSlotForGrouping('cereais | biscoitos');
    $settings = makeSettings(collect([$product]));

    $result = $findCandidates($slot, $settings);

    expect($result)->toBeEmpty();
});

test('findCandidates exclui produto com status draft mesmo com grouping correto', function (): void {
    $findCandidates = makeFindCandidatesCallable();
    $product = makeProductForGrouping('cereais | biscoitos', 'draft');
    $slot = makeSlotForGrouping('cereais | biscoitos');
    $settings = makeSettings(collect([$product]));

    $result = $findCandidates($slot, $settings);

    expect($result)->toBeEmpty();
});

test('findCandidates inclui produto published com grouping correto', function (): void {
    $findCandidates = makeFindCandidatesCallable();
    $product = makeProductForGrouping('cereais | biscoitos', 'published');
    $slot = makeSlotForGrouping('cereais | biscoitos');
    $settings = makeSettings(collect([$product]));

    $result = $findCandidates($slot, $settings);

    expect($result)->toHaveCount(1);
});

test('findCandidates inclui produto synced com grouping correto', function (): void {
    $findCandidates = makeFindCandidatesCallable();
    $product = makeProductForGrouping('cereais | biscoitos', 'synced');
    $slot = makeSlotForGrouping('cereais | biscoitos');
    $settings = makeSettings(collect([$product]));

    $result = $findCandidates($slot, $settings);

    expect($result)->toHaveCount(1);
});

test('findCandidates retorna vazio quando não há produtos', function (): void {
    $findCandidates = makeFindCandidatesCallable();
    $slot = makeSlotForGrouping('cereais | biscoitos');
    $settings = makeSettings(collect());

    $result = $findCandidates($slot, $settings);

    expect($result)->toBeEmpty();
});
