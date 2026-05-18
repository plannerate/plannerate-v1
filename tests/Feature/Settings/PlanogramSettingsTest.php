<?php

use App\Http\Controllers\Settings\PlanogramSettingsController;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Illuminate\Support\Facades\Validator;

// ── ScoringWeightsValue defaults ──────────────────────────────────────────────

describe('ScoringWeightsValue::default()', function () {
    it('usa blockHierarchyLevel 5 (Subcategoria) como padrão', function () {
        $defaults = ScoringWeightsValue::default();

        expect($defaults->blockHierarchyLevel)->toBe(5);
    });

    it('usa adjacencyHierarchyLevel 4 (Categoria) como padrão', function () {
        $defaults = ScoringWeightsValue::default();

        expect($defaults->adjacencyHierarchyLevel)->toBe(4);
    });

    it('mantém pesos que somam 1.0', function () {
        $defaults = ScoringWeightsValue::default();
        $soma = $defaults->giro + $defaults->margem + $defaults->estrategico + $defaults->doh;

        expect(round($soma, 2))->toBe(1.0);
    });
});

// ── Validação do controller ───────────────────────────────────────────────────

describe('PlanogramSettingsController validação', function () {
    function planogramValidationRules(): array
    {
        return [
            'w_giro' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_margem' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_estrategico' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_doh' => ['required', 'numeric', 'min:0', 'max:1'],
            'sales_window_months' => ['required', 'integer', 'min:1', 'max:24'],
            'block_hierarchy_level' => ['required', 'integer', 'min:2', 'max:7', 'gte:adjacency_hierarchy_level'],
            'adjacency_hierarchy_level' => ['required', 'integer', 'min:2', 'max:7'],
            'vertical_block_threshold' => ['required', 'numeric', 'min:0.05', 'max:0.50'],
            'vertical_block_min_shelves' => ['required', 'integer', 'min:2', 'max:4'],
        ];
    }

    function validPlanogramPayload(array $overrides = []): array
    {
        return array_merge([
            'w_giro' => 0.40,
            'w_margem' => 0.30,
            'w_estrategico' => 0.20,
            'w_doh' => 0.10,
            'sales_window_months' => 4,
            'block_hierarchy_level' => 5,
            'adjacency_hierarchy_level' => 4,
            'vertical_block_threshold' => 0.20,
            'vertical_block_min_shelves' => 2,
        ], $overrides);
    }

    it('aceita payload válido', function () {
        $validator = Validator::make(validPlanogramPayload(), planogramValidationRules());

        expect($validator->fails())->toBeFalse();
    });

    it('rejeita block_hierarchy_level menor que adjacency_hierarchy_level', function () {
        $validator = Validator::make(
            validPlanogramPayload(['block_hierarchy_level' => 3, 'adjacency_hierarchy_level' => 4]),
            planogramValidationRules(),
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('block_hierarchy_level'))->toBeTrue();
    });

    it('aceita block_hierarchy_level igual a adjacency_hierarchy_level', function () {
        $validator = Validator::make(
            validPlanogramPayload(['block_hierarchy_level' => 4, 'adjacency_hierarchy_level' => 4]),
            planogramValidationRules(),
        );

        expect($validator->fails())->toBeFalse();
    });

    it('rejeita vertical_block_threshold fora do range 0.05-0.50', function () {
        $abaixo = Validator::make(validPlanogramPayload(['vertical_block_threshold' => 0.01]), planogramValidationRules());
        $acima = Validator::make(validPlanogramPayload(['vertical_block_threshold' => 0.60]), planogramValidationRules());

        expect($abaixo->fails())->toBeTrue()
            ->and($acima->fails())->toBeTrue();
    });

    it('rejeita vertical_block_min_shelves fora do range 2-4', function () {
        $abaixo = Validator::make(validPlanogramPayload(['vertical_block_min_shelves' => 1]), planogramValidationRules());
        $acima = Validator::make(validPlanogramPayload(['vertical_block_min_shelves' => 5]), planogramValidationRules());

        expect($abaixo->fails())->toBeTrue()
            ->and($acima->fails())->toBeTrue();
    });
});

// ── hierarchyLevels ───────────────────────────────────────────────────────────

describe('PlanogramSettingsController hierarchy_levels', function () {
    it('expõe exatamente 6 níveis (2 a 7)', function () {
        $controller = new PlanogramSettingsController;
        $levels = (new ReflectionMethod($controller, 'hierarchyLevels'))->invoke($controller);

        expect($levels)->toHaveCount(6);
        expect(array_column($levels, 'value'))->toBe([2, 3, 4, 5, 6, 7]);
    });

    it('nível 5 tem label Subcategoria', function () {
        $controller = new PlanogramSettingsController;
        $levels = (new ReflectionMethod($controller, 'hierarchyLevels'))->invoke($controller);
        $level5 = collect($levels)->firstWhere('value', 5);

        expect($level5['label'])->toBe('Subcategoria');
    });

    it('nível 4 tem label Categoria', function () {
        $controller = new PlanogramSettingsController;
        $levels = (new ReflectionMethod($controller, 'hierarchyLevels'))->invoke($controller);
        $level4 = collect($levels)->firstWhere('value', 4);

        expect($level4['label'])->toBe('Categoria');
    });
});
