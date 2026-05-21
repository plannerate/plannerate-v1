import type { CategoryRole, PlanogramTemplateSlot } from './types';

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
