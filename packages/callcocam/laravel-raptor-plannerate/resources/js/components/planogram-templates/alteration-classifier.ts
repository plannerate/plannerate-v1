/**
 * Espelho TypeScript do AlterationClassifier PHP.
 * Fonte única de verdade compartilhada front/back para mapear campos → nível de alteração.
 * @see app/Services/AutoPlanogram/AlterationClassifier.php
 */

export type AlterationLevel = 'reorder' | 'redistribute' | 'regenerate';

/** Campos que afetam apenas a ordenação visual (ordering/position). Produtos e frentes intactos. */
export const REORDER_FIELDS: readonly string[] = ['visual_criteria', 'price_order', 'size_order'];

/** Campos que afetam o agrupamento/exposição. Mantém {produto: frentes}, recalcula posições. */
export const REDISTRIBUTE_FIELDS: readonly string[] = ['brand_exposure', 'flavor_exposure'];

/** Campos que exigem regeneração total (produtos, frentes, rejeitados, ocupação). */
export const REGENERATE_FIELDS: readonly string[] = [
    'category_id',
    'min_facings',
    'max_facings',
    'space_fallback',
    'use_target_stock',
    'facing_expansion',
    'priority',
    'role_override',
];

export const ALTERATION_LEVEL_LABELS: Record<AlterationLevel, string> = {
    reorder: 'Reordenando…',
    redistribute: 'Redistribuindo…',
    regenerate: 'Regerando planograma…',
};

/**
 * Retorna o nível mínimo necessário dado o conjunto de campos alterados.
 * Precedência: regenerate > redistribute > reorder > null.
 */
export function classifyAlteration(changedFields: string[]): AlterationLevel | null {
    if (changedFields.some((f) => REGENERATE_FIELDS.includes(f))) {
return 'regenerate';
}

    if (changedFields.some((f) => REDISTRIBUTE_FIELDS.includes(f))) {
return 'redistribute';
}

    if (changedFields.some((f) => REORDER_FIELDS.includes(f))) {
return 'reorder';
}

    return null;
}

/**
 * Compara dois estados de slot e retorna os campos que mudaram.
 * Compara apenas campos classificados (reorder + redistribute + regenerate).
 */
export function diffSlotFields(
    before: Record<string, unknown>,
    after: Record<string, unknown>,
): string[] {
    const all = [...REORDER_FIELDS, ...REDISTRIBUTE_FIELDS, ...REGENERATE_FIELDS];

    return all.filter((field) => {
        return JSON.stringify(before[field] ?? null) !== JSON.stringify(after[field] ?? null);
    });
}
