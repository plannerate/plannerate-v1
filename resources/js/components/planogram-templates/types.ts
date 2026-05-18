export type SlotPriceOrder = 'asc' | 'desc' | 'none';
export type SlotSizeOrder = 'asc' | 'desc' | 'none';
export type SlotBrandExposure = 'vertical' | 'horizontal' | 'mixed';
export type SlotFlavorExposure = 'vertical' | 'horizontal' | 'mixed';
export type SlotSpaceFallback = 'reduce_c' | 'reduce_facings' | 'skip';

export type PlanogramTemplateSlot = {
    id?: string;
    subtemplate_id?: string;
    module_number: number;
    shelf_order: number;
    category?: string | null;
    subcategory?: string | null;
    grouping: string;
    grouping_normalized?: string;
    min_facings: number;
    priority: number;
    price_order: SlotPriceOrder;
    size_order: SlotSizeOrder;
    brand_exposure: SlotBrandExposure;
    flavor_exposure: SlotFlavorExposure;
    space_fallback: SlotSpaceFallback;
    use_target_stock: boolean;
    ordering?: number;
};

export type PlanogramSubtemplate = {
    id: string;
    code: string;
    num_modules: number;
    slots: PlanogramTemplateSlot[];
};

export type PlanogramTemplateProduct = {
    id: string;
    ean: string;
    product_id: string | null;
    description: string;
    brand: string;
    grouping: string;
    category?: string | null;
    subcategory?: string | null;
    package_type?: string | null;
    package_content?: string | null;
};

export type ProductSearchResult = {
    id: string;
    ean: string;
    name: string;
    brand: string;
    description?: string;
};

export type WizardStepStatus = 'complete' | 'active' | 'pending';

export type WizardStep = {
    step: 1 | 2 | 3;
    label: string;
    description?: string;
    href?: string;
};

/** Deterministic HSL color from a grouping string */
export function groupingToColor(grouping: string): { background: string; border: string; color: string } {
    let hash = 0;
    for (let i = 0; i < grouping.length; i++) {
        hash = ((hash << 5) - hash) + grouping.charCodeAt(i);
        hash |= 0;
    }
    const hue = Math.abs(hash) % 360;
    return {
        background: `hsl(${hue}, 60%, 92%)`,
        border: `hsl(${hue}, 50%, 72%)`,
        color: `hsl(${hue}, 45%, 28%)`,
    };
}
