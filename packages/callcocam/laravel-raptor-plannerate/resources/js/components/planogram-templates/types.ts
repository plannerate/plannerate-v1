export type VisualCriterionKey = 'marca' | 'preco' | 'tamanho' | 'score_abc' | 'margem' | 'embalagem' | 'tipo' | 'sabor' | 'atributo';
export type VisualCriterionDirection = 'asc' | 'desc' | 'none';

export type VisualCriterionItem = {
    key: VisualCriterionKey;
    direction: VisualCriterionDirection;
    /** Ordem de tipos de embalagem — usado apenas quando key = 'embalagem' */
    packaging_order?: string[];
};

export type ZonePriority =
    | 'none'
    | 'maior_margem'
    | 'maior_giro'
    | 'maior_valor_vendido'
    | 'curva_a'
    | 'menor_margem'
    | 'complementar_fria'
    | 'maior_volume'
    | 'menor_prioridade';

export type SlotPriceOrder = 'asc' | 'desc' | 'none';
export type SlotSizeOrder = 'asc' | 'desc' | 'none';
export type SlotBrandExposure = 'vertical' | 'horizontal' | 'mixed';
export type SlotFlavorExposure = 'vertical' | 'horizontal' | 'mixed';
export type SlotSpaceFallback = 'reduce_c' | 'reduce_facings' | 'skip';
export type SlotFacingExpansion = 'none' | 'score' | 'current_stock' | 'target_stock' | 'equal';
export type CategoryRole = 'destino' | 'rotina' | 'conveniencia' | 'impulso' | 'sazonal' | 'complementar';
export type FlowDirection = 'left_to_right' | 'right_to_left';

/** Disposição dos produtos: horizontal (legado) ou vertical (blocagem por marca em colunas alinhadas) */
export type LayoutOrientation = 'horizontal' | 'vertical';

/** Tipo de alerta no relatório de explicação */
export type ExplanationAlertType =
    | 'missing_dimensions'
    | 'mix_excede_gondola'
    | 'target_stock_not_met'
    | 'vertical_nao_aplicado';

export type ExplanationAlert = {
    type: ExplanationAlertType;
    /** Alertas por-produto trazem contagem; alertas de layout (ex.: vertical) não têm. */
    count?: number;
    message: string;
};

/** Justificativa de um produto alocado */
export type AllocationEntry = {
    product_id: string;
    product_name: string;
    slot_id: string | null;
    category_name: string | null;
    abc_class: 'A' | 'B' | 'C' | null;
    is_mandatory: boolean;
    facings: number;
    facings_expanded: boolean;
    zone: 'hot' | 'cold' | 'neutral';
    role: string | null;
    has_target_stock: boolean;
};

/** Justificativa de um produto rejeitado */
export type RejectionEntry = {
    product_id: string;
    product_name: string;
    slot_id: string | null;
    category_name: string | null;
    abc_class: 'A' | 'B' | 'C' | null;
    motivo: string;
    motivo_label: string;
};

/** Relatório de explicação gerado pelo motor de placement (modo template) */
export type ExplanationReport = {
    allocated: AllocationEntry[];
    rejected: RejectionEntry[];
    alerts: ExplanationAlert[];
};

export type PlanogramTemplateSlot = {
    id?: string;
    subtemplate_id?: string;
    module_number: number;
    shelf_order: number;
    category_id: string | null;
    category_name?: string | null;
    category_path?: string | null;
    category_role?: CategoryRole | null;
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
    role_override?: CategoryRole | null;
    visual_criteria?: VisualCriterionItem[] | null;
    max_share_per_sku?: number | null;
    max_share_per_brand?: number | null;
    max_share_per_subcategory?: number | null;
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
    | 'role_override'
>;

export type PlanogramSubtemplate = {
    id: string;
    code: string;
    num_modules: number;
    slot_defaults?: PlanogramSlotDefaults | null;
    hot_zone_priority?: ZonePriority | null;
    cold_zone_priority?: ZonePriority | null;
    flow_direction?: FlowDirection | null;
    layout_orientation?: LayoutOrientation | null;
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
    position_cm: number;
    abc_class: 'A' | 'B' | 'C' | null;
    is_mandatory: boolean;
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
    zone?: 'hot' | 'cold' | 'neutral';
    num_shelves?: number;
};

export type SlotAnalysisData = {
    summary: SlotAnalysisSummary;
    rows: SlotAnalysisRow[];
};

export type ProductRuleType = 'mandatory' | 'blocked';

export type ProductRule = {
    id: string;
    type: ProductRuleType;
    type_label: string;
    product_id: string | null;
    product_name: string | null;
    product_ean: string | null;
    brand: string | null;
    subcategory_id: string | null;
    subcategory_name: string | null;
    reason: string | null;
    created_at: string | null;
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
