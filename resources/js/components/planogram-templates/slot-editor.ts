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

export const visualCriterionMeta: Record<VisualCriterionKey, { label: string; supportsDirection: boolean }> = {
    marca: { label: 'Marca', supportsDirection: true },
    preco: { label: 'Preço', supportsDirection: true },
    tamanho: { label: 'Tamanho', supportsDirection: true },
    score_abc: { label: 'Curva ABC', supportsDirection: false },
    margem: { label: 'Margem', supportsDirection: true },
    embalagem: { label: 'Embalagem', supportsDirection: false },
};

export const categoryRoleOptions: { value: CategoryRole | ''; label: string }[] = [
    { value: '', label: 'Herdar da categoria' },
    { value: 'destino', label: 'Destino — gera tráfego, área nobre' },
    { value: 'rotina', label: 'Rotina — exposição equilibrada, centro' },
    { value: 'conveniencia', label: 'Conveniência — leitura simples, acesso fácil' },
    { value: 'impulso', label: 'Impulso — área quente, maior visibilidade' },
    { value: 'sazonal', label: 'Sazonal — destaque temporário' },
    { value: 'complementar', label: 'Complementar — zona fria, área de associação' },
];
