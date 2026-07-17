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

/** MIME types customizados dos drags do editor. */
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
    /** flag 'true'/'false': modificador de cópia pressionado no dragstart */
    IS_COPY: 'application/x-is-copy',
    /** id do registro de produto rejeitado (drag a partir do drawer de rejeitados) */
    REJECTED_ID: 'application/x-rejected-id',
} as const;

// ─── Modificador de cópia ─────────────────────────────────────────────────────

/**
 * O usuário está pedindo CÓPIA em vez de MOVER?
 *
 * Aceita Alt/Option, Ctrl e Cmd porque a convenção muda por sistema: no macOS
 * o gesto de copiar-arrastando é Option (Ctrl+mousedown é o clique secundário
 * do sistema, que abre o menu de contexto e nem chega a iniciar o drag HTML5);
 * no Windows/Linux é Ctrl.
 *
 * Deve ser consultado no dragstart E no dragover/drop: o modificador pode ser
 * pressionado depois que o arraste começou — no Mac, na prática, é sempre esse
 * o caso.
 */
export function isCopyModifier(event: DragEvent | MouseEvent): boolean {
    return event.altKey || event.ctrlKey || event.metaKey;
}

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

/**
 * Prepara o dataTransfer para o drag de um segmento (mover; com modificador,
 * copiar — ver `isCopyModifier`).
 *
 * `effectAllowed` é sempre 'copyMove', NUNCA travado no efeito escolhido no
 * dragstart: fixá-lo em 'move' fazia o browser recusar `dropEffect = 'copy'`
 * (resolvendo para 'none') quando o modificador só era pressionado no meio do
 * arraste — o caso normal no macOS.
 */
export function setSegmentDragData(
    dt: DataTransfer,
    segmentId: string,
    shelfId: string,
    isCopy: boolean,
): void {
    dt.effectAllowed = 'copyMove';
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

/**
 * Prepara o dataTransfer para o drag de uma prateleira.
 *
 * Os dados reais (shelf/section de origem, offset) vivem nos refs globais
 * `draggingShelfId`/`draggingShelfSectionId`/`draggingShelfOffset` — o drop lê
 * de lá (dataTransfer.getData é vazio no dragover por protected mode). O
 * `text/plain` mínimo existe porque alguns browsers (Firefox) não iniciam o
 * drag com dataTransfer vazio.
 */
export function setShelfDragData(dt: DataTransfer, shelfId: string, sectionId: string): void {
    void sectionId;
    dt.effectAllowed = 'move';
    dt.setData('text/plain', shelfId);
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
