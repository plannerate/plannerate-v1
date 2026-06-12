<?php

/**
 * Testes do AutoGenerateConfigDTO::withOverrides.
 *
 * Garante que o clone com overrides preserva todos os demais campos —
 * a reconstrução manual anterior no AutoGenerationRunner resetava
 * silenciosamente exclude_class_c e os cortes ABC para o default.
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;

function makeFullConfig(): AutoGenerateConfigDTO
{
    return AutoGenerateConfigDTO::fromArray([
        'strategy' => 'margin',
        'use_existing_analysis' => false,
        'start_date' => '2025-01-01',
        'end_date' => '2025-04-30',
        'min_facings' => 2,
        'max_facings' => 8,
        'group_by_subcategory' => false,
        'include_products_without_sales' => false,
        'table_type' => 'sales',
        'category_id' => 'cat-form',
        'facing_expansion' => 'target_stock',
        'use_target_stock' => true,
        'space_fallback' => 'remove_dog',
        'max_share_per_sku' => 25,
        'max_share_per_brand' => 50,
        'max_share_per_subcategory' => 75,
        'abc_cutoff_a' => 0.70,
        'abc_cutoff_b' => 0.85,
        'hot_zone_priority' => 'maior_giro',
        'cold_zone_priority' => 'maior_volume',
        'flow_direction' => 'right_to_left',
        'secondary_criteria' => [['key' => 'marca', 'direction' => 'asc']],
        'exclude_class_c' => true,
    ]);
}

test('withOverrides aplica apenas as chaves informadas', function (): void {
    $config = makeFullConfig();

    $clone = $config->withOverrides([
        'include_products_without_sales' => true,
        'category_id' => null,
    ]);

    expect($clone->includeProductsWithoutSales)->toBeTrue()
        ->and($clone->categoryId)->toBeNull();
});

test('withOverrides preserva exclude_class_c e cortes ABC (regressão do effectiveConfig)', function (): void {
    $config = makeFullConfig();

    $clone = $config->withOverrides([
        'include_products_without_sales' => true,
        'category_id' => null,
    ]);

    expect($clone->excludeClassC)->toBeTrue()
        ->and($clone->abcCutoffA)->toBe(0.70)
        ->and($clone->abcCutoffB)->toBe(0.85);
});

test('withOverrides preserva todos os demais campos do DTO', function (): void {
    $config = makeFullConfig();

    $clone = $config->withOverrides([
        'include_products_without_sales' => true,
        'category_id' => null,
    ]);

    $expected = array_merge($config->toArray(), [
        'include_products_without_sales' => true,
        'category_id' => null,
    ]);

    expect($clone->toArray())->toBe($expected);
});

test('withOverrides sem chaves retorna clone idêntico', function (): void {
    $config = makeFullConfig();

    expect($config->withOverrides([])->toArray())->toBe($config->toArray());
});
