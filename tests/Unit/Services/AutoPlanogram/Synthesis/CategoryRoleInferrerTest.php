<?php

use App\Models\Category;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\Synthesis\CategoryRoleInferrer;
use Callcocam\LaravelRaptorPlannerate\Enums\CategoryRole;
use Illuminate\Support\Str;

function makeSummary(float $quantity, float $margem, int $skuCount = 5): CategoryAbcSummary
{
    return new CategoryAbcSummary(
        categoryId: Str::ulid()->toBase32(),
        totalQuantity: $quantity,
        totalMargem: $margem,
        skuCount: $skuCount,
        dominantAbcClass: null,
    );
}

function makeCategoryWithRole(?CategoryRole $role): Category
{
    $category = new Category;
    $category->role = $role;

    return $category;
}

test('respeita categories.role quando definido manualmente', function (): void {
    $inferrer = new CategoryRoleInferrer;
    $category = makeCategoryWithRole(CategoryRole::Sazonal);
    $summary = makeSummary(0.1, 0.1);

    $result = $inferrer->infer($category, $summary);

    expect($result)->toBe(CategoryRole::Sazonal);
});

test('giro alto + margem alta infere destino', function (): void {
    $inferrer = new CategoryRoleInferrer;
    $category = makeCategoryWithRole(null);
    $summary = makeSummary(0.8, 0.8);

    $result = $inferrer->infer($category, $summary);

    expect($result)->toBe(CategoryRole::Destino);
});

test('giro alto + margem media infere rotina', function (): void {
    $inferrer = new CategoryRoleInferrer;
    $category = makeCategoryWithRole(null);
    // giro alto, margem média (não baixa)
    $summary = makeSummary(0.7, 0.45);

    $result = $inferrer->infer($category, $summary);

    expect($result)->toBe(CategoryRole::Rotina);
});

test('margem alta + giro baixo infere impulso', function (): void {
    $inferrer = new CategoryRoleInferrer;
    $category = makeCategoryWithRole(null);
    $summary = makeSummary(0.1, 0.75);

    $result = $inferrer->infer($category, $summary);

    expect($result)->toBe(CategoryRole::Impulso);
});

test('giro baixo + margem baixa infere complementar', function (): void {
    $inferrer = new CategoryRoleInferrer;
    $category = makeCategoryWithRole(null);
    $summary = makeSummary(0.1, 0.1);

    $result = $inferrer->infer($category, $summary);

    expect($result)->toBe(CategoryRole::Complementar);
});

test('valores medianos sem role definido retornam rotina como default', function (): void {
    $inferrer = new CategoryRoleInferrer;
    $category = makeCategoryWithRole(null);
    $summary = makeSummary(0.45, 0.45);

    $result = $inferrer->infer($category, $summary);

    expect($result)->toBe(CategoryRole::Rotina);
});
