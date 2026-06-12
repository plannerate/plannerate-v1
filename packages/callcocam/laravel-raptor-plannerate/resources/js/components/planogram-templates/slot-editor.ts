import type { CategoryRole, PlanogramTemplateSlot, VisualCriterionItem, VisualCriterionKey } from './types';

export type SlotDraft = {
    module_number: number;
    shelf_order: number;
    category_id: string | null;
    min_facings: number;
    max_facings: number;
    priority: number;
    price_order: PlanogramTemplateSlot['price_order'];
    size_order: PlanogramTemplateSlot['size_order'];
    brand_exposure: PlanogramTemplateSlot['brand_exposure'];
    flavor_exposure: PlanogramTemplateSlot['flavor_exposure'];
    space_fallback: PlanogramTemplateSlot['space_fallback'];
    use_target_stock: boolean;
    facing_expansion: PlanogramTemplateSlot['facing_expansion'];
    role_override: CategoryRole | null;
    /** null = usar legado (price_order/size_order/brand_exposure); array = critérios arrastáveis */
    visual_criteria: VisualCriterionItem[] | null;
    /** Porcentagem máxima do slot que um SKU pode ocupar (1-100). null = sem limite. */
    max_share_per_sku: number | null;
    /** Porcentagem máxima do slot que uma marca pode ocupar (1-100). null = sem limite. */
    max_share_per_brand: number | null;
    /** Porcentagem máxima do slot que uma subcategoria pode ocupar (1-100). null = sem limite. */
    max_share_per_subcategory: number | null;
};

/**
 * Metadados estruturais dos critérios visuais (sem labels — textos visíveis
 * vêm de `planogram-templates.visual_criteria.criteria_labels.*` via useT()).
 */
export const visualCriterionMeta: Record<VisualCriterionKey, { supportsDirection: boolean }> = {
    marca: { supportsDirection: true },
    preco: { supportsDirection: true },
    tamanho: { supportsDirection: true },
    score_abc: { supportsDirection: false },
    margem: { supportsDirection: true },
    embalagem: { supportsDirection: false },
    tipo: { supportsDirection: true },
    sabor: { supportsDirection: true },
    atributo: { supportsDirection: true },
};

/**
 * Valores possíveis do papel da categoria ('' = herdar da categoria).
 * Labels traduzidos em `planogram-templates.role_options.*` via useT().
 */
export const categoryRoleValues: (CategoryRole | '')[] = [
    '',
    'destino',
    'rotina',
    'conveniencia',
    'impulso',
    'sazonal',
    'complementar',
];
