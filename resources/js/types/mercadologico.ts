export interface CategoryProduct {
    id: string;
    name: string;
    ean: string | null;
}

export interface CategoryNode {
    id: string;
    name: string;
    slug: string | null;
    depth?: number;
    children: CategoryNode[];
    children_count?: number;
    products_count?: number;
    planograms_count?: number;
    products?: CategoryProduct[];
    full_path?: string;
    [key: string]: unknown;
}

/** Nomes dos níveis hierárquicos (espelha MercadologicoController::HIERARCHY_LEVEL_NAMES). */
export type HierarchyLevelNames = Record<number, string>;
