/**
 * Planogram Types
 *
 * Tipos centralizados para o sistema de planogramas
 */

/**
 * @deprecated Esta interface está deprecated. As dimensões agora estão diretamente no Product.
 * Use os campos width, height, depth diretamente no Product.
 */
export interface Dimension {
    width?: number;
    height?: number;
    depth?: number;
}

export interface Product {
    id: string;
    name?: string;
    code?: string;
    ean?: string;
    barcode?: string;
    image?: string;
    image_url?: string;
    image_url_encoded?: string;
    /**
     * @deprecated Use os campos width, height, depth diretamente no Product.
     * A tabela dimensions foi removida e suas colunas foram migradas para products.
     */
    dimension?: Dimension;
    // Dimensões agora estão diretamente no produto
    width?: number;
    height?: number;
    depth?: number;
    weight?: number;
    volume?: number;
    brand?: string;
    category?: string;
    price?: number;
    status?: string;
    /** Indica se o produto tem dimensões (altura, largura e profundidade) preenchidas. */
    has_dimensions?: boolean;
    category_full_path?: string;
    sales?: any[];
}

export interface Layer {
    id: string;
    segment_id: string;
    product_id?: string;
    product?: Product;
    quantity?: number;
    height?: number;
    alignment?: string;
    spacing?: number;
    deleted_at?: string;
}

export interface Segment {
    id: string;
    shelf_id?: string;
    layer_id?: string;
    layer?: Layer;
    width?: number;
    height?: number;
    depth?: number;
    position_x?: number;
    position_y?: number;
    facings?: number;
    quantity?: number;
    ordering?: number;
    position?: number;
    deleted_at?: string;
}

export interface Shelf {
    id: string;
    section_id?: string;
    code?: string;
    shelf_width: number;
    shelf_height: number;
    shelf_depth: number;
    shelf_position: number;
    ordering: number;
    alignment?: string;
    segments?: Segment[];
    shelves?: Shelf[];
    product_type?: string;
    deleted_at?: string;
}

export interface Section {
    id: string;
    gondola_id: string;
    name: string;
    code?: string;
    width: number;
    height: number;
    num_shelves?: number;
    base_height: number;
    base_depth: number;
    base_width: number;
    cremalheira_width: number;
    ordering: number;
    hole_height: number;
    hole_spacing: number;
    hole_width?: number;
    settings?: {
        holes?: Hole[];
    };
    alignment?: string;
    shelves?: Shelf[];
    deleted_at?: string;
}

export interface Hole {
    width: number;
    height: number;
    spacing: number;
    position: number;
}

export interface Gondola {
    id: string;
    name?: string;
    slug?: string;
    route_gondolas?: string;
    scale_factor?: number;
    status?: string;
    num_modulos?: number;
    side?: string;
    alignment?: 'left' | 'right' | 'center' | 'justify';
    location?: string;
    flow?: string;
    sections?: Section[];
    height?: number;
    width?: number;
    depth?: number;
    planogram_id?: string;
    linked_map_gondola_id?: string | null;
    linked_map_gondola_category?: string | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string;
    planogram?: {
        gondolas?: Gondola[];
        store_id?: string;
        store?: {
            id: string;
            name: string;
            map_image_path?: string;
            map_regions?: Array<{
                id: string;
                label?: string;
                type?: 'rect' | 'circle';
                color?: string;
                x: number;
                y: number;
                width?: number;
                height?: number;
                radius?: number;
            }>;
        };
    };
}

// Selection Types
export type SelectionType =
    | 'product'
    | 'layer'
    | 'segment'
    | 'shelf'
    | 'section'
    | null;

export type ActionType = 'section' | 'shelf' | 'segment' | 'layer' | 'product' | 'section_update' | 'sections_reorder' | 'shelf_update' | 'segment_update' | 'layer_update' | 'product_update' | 'gondola_update' | 'gondola_scale' | 'gondola_alignment' | 'gondola_flow' | 'segment_position' | 'shelf_position' | 'shelf_transfer' | 'segment_transfer' | 'segment_copy' | 'product_placement' | 'product_removal' | 'layer_create' | 'segment_create' | 'shelf_create' | 'section_create' | 'gondola_create';

export type EntityType =
    | 'shelf'
    | 'section'
    | 'product'
    | 'layer'
    | 'segment'
    | 'gondola';

export interface Selection {
    type: SelectionType;
    item: Product | Layer | Segment | Shelf | Section | null;
    context?: {
        section?: Section;
        shelf?: Shelf;
        segment?: Segment;
        layer?: Layer;
        gondolaSections?: Section[];
        shelves?: Shelf[];
        segments?: Segment[];
    };
}

export interface Category {
    id: string;
    name: string;
    parent_id?: string;
    parent?: Category;
    children?: Category[];
}


export interface AbcAnalysis {
    id: string;
    type: 'abc';
    results: Array<{ ean: string; classificacao: 'A' | 'B' | 'C' }>;
    summary: {
        total_products: number;
        class_a_count: number;
        class_b_count: number;
        class_c_count: number;
    };
    analyzed_at: string;
    is_outdated: boolean;
}

export interface StockAnalysis {
    id: string;
    type: 'stock';
    results: Array<{
        ean: string;
        estoque_alvo: number;
        estoque_atual: number;
    }>;
    summary: {
        total_products: number;
        total_target_stock: number;
        total_current_stock: number;
    };
    analyzed_at: string;
    is_outdated: boolean;
}