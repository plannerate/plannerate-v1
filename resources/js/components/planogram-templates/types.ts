export type SlotPriceOrder = 'asc' | 'desc' | 'none';
export type SlotSizeOrder = 'asc' | 'desc' | 'none';
export type SlotBrandExposure = 'vertical' | 'horizontal' | 'mixed';
export type SlotFlavorExposure = 'vertical' | 'horizontal' | 'mixed';
export type SlotSpaceFallback = 'reduce_c' | 'reduce_facings' | 'skip';
export type SlotFacingExpansion = 'none' | 'score' | 'current_stock' | 'equal';

export type PlanogramTemplateSlot = {
    id?: string;
    subtemplate_id?: string;
    module_number: number;
    shelf_order: number;
    category_id: string | null;
    category_name?: string | null;
    category_path?: string | null;
    min_facings: number;
    max_facings: number;
    priority: number;
    price_order: SlotPriceOrder;
    size_order: SlotSizeOrder;
    brand_exposure: SlotBrandExposure;
    flavor_exposure: SlotFlavorExposure;
    space_fallback: SlotSpaceFallback;
    use_target_stock: boolean;
    facing_expansion: SlotFacingExpansion;
    ordering?: number;
    rejected_count?: number;
};

export type PlanogramSlotDefaults = Pick<
    PlanogramTemplateSlot,
    | 'category_id'
    | 'min_facings'
    | 'max_facings'
    | 'priority'
    | 'price_order'
    | 'size_order'
    | 'brand_exposure'
    | 'flavor_exposure'
    | 'space_fallback'
    | 'use_target_stock'
    | 'facing_expansion'
>;

export type PlanogramSubtemplate = {
    id: string;
    code: string;
    num_modules: number;
    slot_defaults?: PlanogramSlotDefaults | null;
    slots: PlanogramTemplateSlot[];
};

export type SlotProduct = {
    id: string;
    name: string;
    ean: string;
    brand: string;
    category_id: string;
};

export type SlotAnalysisRow = {
    product_id: string;
    name: string;
    ean: string;
    codigo_erp: string;
    brand: string;
    has_sales: boolean;
    dimensions: string;
    status: 'entrou' | 'fora' | 'outro_slot';
    reason: string;
    facing_used: number;
    required_width_cm: number;
    url: string;
};

export type SlotAnalysisSummary = {
    slot_id: string;
    category_id: string | null;
    shelf_width_cm: number;
    occupied_width_cm: number;
    free_width_cm: number;
    total_products: number;
    previous_slots_placed: number;
    placed_products: number;
    outro_slot_products: number;
    rejected_products: number;
};

export type SlotAnalysisData = {
    summary: SlotAnalysisSummary;
    rows: SlotAnalysisRow[];
};

export type WizardStepStatus = 'complete' | 'active' | 'pending';

export type WizardStep = {
    step: 1 | 2 | 3;
    label: string;
    description?: string;
    href?: string;
};

/** Deterministic HSL color from a slot identifier (category_id or any string) */
export function slotColor(id: string): {
    background: string;
    border: string;
    color: string;
} {
    let hash = 0;

    for (let i = 0; i < id.length; i++) {
        hash = (hash << 5) - hash + id.charCodeAt(i);
        hash |= 0;
    }

    const hue = Math.abs(hash) % 360;

    return {
        background: `hsl(${hue}, 60%, 92%)`,
        border: `hsl(${hue}, 50%, 72%)`,
        color: `hsl(${hue}, 45%, 28%)`,
    };
}
