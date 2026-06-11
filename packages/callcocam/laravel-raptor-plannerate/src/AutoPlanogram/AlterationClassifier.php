<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram;

use Callcocam\LaravelRaptorPlannerate\Enums\AlterationLevel;

/**
 * Fonte única de verdade: mapeia campos de slot para o nível mínimo de alteração.
 * Espelhado no frontend em resources/js/components/planogram-templates/alteration-classifier.ts
 */
final class AlterationClassifier
{
    /**
     * Campos que afetam apenas a ordenação visual (ordering/position).
     * Produtos e frentes permanecem intactos.
     *
     * @var list<string>
     */
    public const REORDER_FIELDS = [
        'visual_criteria',
        'price_order',
        'size_order',
    ];

    /**
     * Campos que afetam o agrupamento/exposição (vertical/horizontal).
     * Mantém {produto: frentes} mas recalcula posições físicas.
     *
     * @var list<string>
     */
    public const REDISTRIBUTE_FIELDS = [
        'brand_exposure',
        'flavor_exposure',
    ];

    /**
     * Campos que exigem regeneração total (produtos, frentes, rejeitados, ocupação).
     *
     * @var list<string>
     */
    public const REGENERATE_FIELDS = [
        'category_id',
        'min_facings',
        'max_facings',
        'space_fallback',
        'use_target_stock',
        'facing_expansion',
        'priority',
        'role_override',
    ];

    /**
     * Retorna o nível mínimo necessário dado o conjunto de campos alterados.
     * Precedência: Regenerate > Redistribute > Reorder > null.
     *
     * @param  list<string>  $changedFields
     */
    public function classify(array $changedFields): ?AlterationLevel
    {
        foreach (self::REGENERATE_FIELDS as $field) {
            if (in_array($field, $changedFields, true)) {
                return AlterationLevel::Regenerate;
            }
        }

        foreach (self::REDISTRIBUTE_FIELDS as $field) {
            if (in_array($field, $changedFields, true)) {
                return AlterationLevel::Redistribute;
            }
        }

        foreach (self::REORDER_FIELDS as $field) {
            if (in_array($field, $changedFields, true)) {
                return AlterationLevel::Reorder;
            }
        }

        return null;
    }

    /**
     * Compara dois estados de slot e retorna os campos que mudaram.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public function diffFields(array $before, array $after): array
    {
        $allFields = array_merge(self::REORDER_FIELDS, self::REDISTRIBUTE_FIELDS, self::REGENERATE_FIELDS);
        $changed = [];

        foreach ($allFields as $field) {
            $b = $before[$field] ?? null;
            $a = $after[$field] ?? null;

            if (json_encode($b) !== json_encode($a)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
