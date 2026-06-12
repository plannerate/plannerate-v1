// ============================================================================
// DRAG & DROP — contrato central de dataTransfer do editor
// ============================================================================
// Fonte única dos MIME types customizados e dos payloads serializados usados
// no drag & drop (produto → prateleira, segmento → prateleira/segmento,
// prateleira → seção, rejeitado → prateleira).
//
// Antes da refatoração essas strings viviam espalhadas em Card.vue, Segment.vue,
// RejectedProductsDrawer.vue, useShelfDragDrop e useShelfDrag — qualquer typo
// quebrava o drop silenciosamente. Toda leitura/escrita passa por aqui.
// ============================================================================

import type { Product } from '@/types/planogram';

/** MIME types customizados (e chaves simples legadas do drag de prateleira). */
export const DND_KEYS = {
    /** JSON do produto único arrastado do painel de produtos */
    PRODUCT: 'application/x-product',
    /** id do produto único (consultas leves sem parse do JSON) */
    PRODUCT_ID: 'application/x-product-id',
    /** JSON do array de produtos (seleção múltipla) */
    PRODUCTS: 'application/x-products',
    /** flag 'true' indicando drag de múltiplos produtos */
    PRODUCTS_MULTIPLE: 'application/x-products-multiple',
    /** id do segmento arrastado no canvas */
    SEGMENT_ID: 'application/x-segment-id',
    /** id da prateleira de origem do segmento arrastado */
    SEGMENT_SHELF_ID: 'application/x-segment-shelf-id',
    /** flag 'true'/'false': Ctrl pressionado no dragstart = copiar em vez de mover */
    IS_COPY: 'application/x-is-copy',
    /** id do registro de produto rejeitado (drag a partir do drawer de rejeitados) */
    REJECTED_ID: 'application/x-rejected-id',
    /** chaves simples do drag de prateleira (mantidas por compatibilidade) */
    SHELF_ID: 'shelfId',
    SECTION_ID: 'sectionId',
} as const;

// ─── Escrita (dragstart) ──────────────────────────────────────────────────────

/** Prepara o dataTransfer para o drag de um produto único do painel. */
export function setProductDragData(dt: DataTransfer, product: Product, label: string): void {
    dt.effectAllowed = 'copy';
    dt.setData(DND_KEYS.PRODUCT_ID, product.id);
    dt.setData(DND_KEYS.PRODUCT, JSON.stringify(product));
    dt.setData('text/plain', label);
}

/** Prepara o dataTransfer para o drag de múltiplos produtos selecionados. */
export function setMultipleProductsDragData(dt: DataTransfer, products: Product[], label: string): void {
    dt.effectAllowed = 'copy';
    dt.setData(DND_KEYS.PRODUCTS_MULTIPLE, 'true');
    dt.setData(DND_KEYS.PRODUCTS, JSON.stringify(products));
    dt.setData('text/plain', label);
}

/** Prepara o dataTransfer para o drag de um segmento (move; Ctrl = copy). */
export function setSegmentDragData(
    dt: DataTransfer,
    segmentId: string,
    shelfId: string,
    isCopy: boolean,
): void {
    dt.effectAllowed = isCopy ? 'copy' : 'move';
    dt.setData(DND_KEYS.SEGMENT_ID, segmentId);
    dt.setData(DND_KEYS.SEGMENT_SHELF_ID, shelfId);
    dt.setData(DND_KEYS.IS_COPY, String(isCopy));
    dt.setData('text/plain', `Segment ${segmentId}`);
}

/** Prepara o dataTransfer para o drag de um produto rejeitado (drawer). */
export function setRejectedProductDragData(
    dt: DataTransfer,
    rejectedId: string,
    product: Product | Record<string, unknown>,
): void {
    dt.effectAllowed = 'copy';
    dt.setData(DND_KEYS.PRODUCT_ID, String((product as Product).id));
    dt.setData(DND_KEYS.PRODUCT, JSON.stringify(product));
    dt.setData(DND_KEYS.REJECTED_ID, rejectedId);
}

/** Prepara o dataTransfer para o drag de uma prateleira (chaves legadas). */
export function setShelfDragData(dt: DataTransfer, shelfId: string, sectionId: string): void {
    dt.effectAllowed = 'move';
    dt.setData(DND_KEYS.SHELF_ID, shelfId);
    dt.setData(DND_KEYS.SECTION_ID, sectionId);
}

// ─── Leitura (dragover/drop) ──────────────────────────────────────────────────

/** O drag atual carrega produto(s)? (dragover só expõe types, não dados) */
export function hasProductData(dt: DataTransfer): boolean {
    return dt.types.includes(DND_KEYS.PRODUCT);
}

export function hasMultipleProductsData(dt: DataTransfer): boolean {
    return dt.types.includes(DND_KEYS.PRODUCTS_MULTIPLE);
}

export function hasSegmentData(dt: DataTransfer): boolean {
    return dt.types.includes(DND_KEYS.SEGMENT_ID);
}

/** Lê o produto único do drop (null se ausente/inválido). */
export function getProductDragData(dt: DataTransfer): Product | null {
    const raw = dt.getData(DND_KEYS.PRODUCT);

    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw) as Product;
    } catch {
        return null;
    }
}

/** Lê o array de produtos do drop múltiplo (vazio se ausente/inválido). */
export function getMultipleProductsDragData(dt: DataTransfer): Product[] {
    if (dt.getData(DND_KEYS.PRODUCTS_MULTIPLE) !== 'true') {
        return [];
    }

    const raw = dt.getData(DND_KEYS.PRODUCTS);

    if (!raw) {
        return [];
    }

    try {
        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? (parsed as Product[]) : [];
    } catch {
        return [];
    }
}

/** Payload do drop de segmento. */
export interface SegmentDragPayload {
    segmentId: string;
    sourceShelfId: string;
    isCopy: boolean;
}

/** Lê o payload de segmento do drop (null se não houver segmento no drag). */
export function getSegmentDragData(dt: DataTransfer): SegmentDragPayload | null {
    const segmentId = dt.getData(DND_KEYS.SEGMENT_ID);

    if (!segmentId) {
        return null;
    }

    return {
        segmentId,
        sourceShelfId: dt.getData(DND_KEYS.SEGMENT_SHELF_ID),
        isCopy: dt.getData(DND_KEYS.IS_COPY) === 'true',
    };
}
