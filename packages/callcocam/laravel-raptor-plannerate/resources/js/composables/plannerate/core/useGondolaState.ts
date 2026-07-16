// ============================================================================
// ESTADO GLOBAL DO EDITOR (SINGLETON PATTERN)
// Refs compartilhados entre todas as instâncias do editor
// ============================================================================

import { ref, watch } from 'vue';
import type { Gondola } from '@/types/planogram';

/** Chave de persistência do estado de exibição das zonas de exposição */
const STORAGE_KEY_ZONE_INDICATORS = 'plannerate:showZoneIndicators';

/** Chave de persistência do indicador de produto selecionado (Preço, Margem, etc.) */
const STORAGE_KEY_PRODUCT_INDICATOR = 'plannerate:selectedIndicator';

/** Chave de persistência da orientação do selo de indicador (vertical/horizontal) */
const STORAGE_KEY_INDICATOR_ORIENTATION = 'plannerate:indicatorOrientation';

/** Chave de persistência do estilo visual da tábua da prateleira */
const STORAGE_KEY_SHELF_BOARD_STYLE = 'plannerate:shelfBoardStyle';

/** Orientações possíveis do selo de indicador exibido na frente do produto. */
export type IndicatorOrientation = 'vertical' | 'horizontal';

/**
 * Estilos visuais da tábua da prateleira (superfície física com profundidade
 * pseudo-3D). Renderizados em `ShelfBoard.vue`.
 */
export type ShelfBoardStyle = 'slate' | 'wood' | 'white' | 'chrome' | 'persp' | 'deck' | 'glass';

/** Estilos válidos — usado para validar o valor lido do localStorage. */
export const SHELF_BOARD_STYLES: readonly ShelfBoardStyle[] = ['slate', 'wood', 'white', 'chrome', 'persp', 'deck', 'glass'];

/**
 * Lê a orientação do selo de indicador do localStorage.
 * Retorna `'vertical'` (default, comportamento legado com rotate-90) quando não
 * há valor salvo ou fora do browser.
 */
function readIndicatorOrientationFromStorage(): IndicatorOrientation {
    if (typeof window === 'undefined') {
        return 'vertical';
    }

    return window.localStorage.getItem(STORAGE_KEY_INDICATOR_ORIENTATION) === 'horizontal'
        ? 'horizontal'
        : 'vertical';
}

/**
 * Lê o estilo da tábua da prateleira do localStorage.
 * Retorna `'slate'` (metálico escuro, comportamento atual) quando não há valor
 * salvo, fora do browser, ou o valor persistido é inválido.
 */
function readShelfBoardStyleFromStorage(): ShelfBoardStyle {
    if (typeof window === 'undefined') {
        return 'slate';
    }

    const stored = window.localStorage.getItem(STORAGE_KEY_SHELF_BOARD_STYLE);

    return SHELF_BOARD_STYLES.includes(stored as ShelfBoardStyle) ? (stored as ShelfBoardStyle) : 'slate';
}

/**
 * Lê o indicador de produto selecionado do localStorage.
 * Retorna `'none'` (nenhum) quando não há valor salvo ou fora do browser.
 */
function readSelectedIndicatorFromStorage(): string {
    if (typeof window === 'undefined') {
        return 'none';
    }

    return window.localStorage.getItem(STORAGE_KEY_PRODUCT_INDICATOR) ?? 'none';
}

/**
 * Lê o estado inicial das zonas de exposição do localStorage.
 * Retorna `true` (default) quando não há valor salvo ou fora do browser.
 */
function readZoneIndicatorsFromStorage(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    const stored = window.localStorage.getItem(STORAGE_KEY_ZONE_INDICATORS);

    return stored === null ? true : stored === 'true';
}

/**
 * Estado da gôndola atual.
 *
 * Usa `ref` PROFUNDO (não `shallowRef`) intencionalmente: a árvore de
 * componentes (Section/Shelf/Segment/Layer) depende da reatividade profunda para
 * re-renderizar quando seu pedaço aninhado da árvore muda. Computeds como
 * `Section.sortedShelves` leem `props.section.shelves` e só recalculam porque o
 * acesso aninhado é rastreado pelo Proxy reativo. Sob `shallowRef`, esses
 * acessos deixariam de ser rastreados e nada re-renderizaria sem trocar a
 * identidade de prop em todo o caminho raiz→folha — o que as operações de
 * move/swap não fazem (uma tentativa de `shallowRef` foi revertida por quebrar o
 * drag-and-drop).
 */
export const currentGondola = ref<Gondola | null>(null);

// Estado de drag & drop
export const draggingSegmentShelfId = ref<string | null>(null);
/**
 * ID do segmento sendo arrastado. Mantido globalmente para que os handlers de
 * `dragover` possam identificar o próprio segmento sem chamar
 * `dataTransfer.getData()` — que retorna string vazia durante o dragover (o drag
 * data store fica em "protected mode" por spec) e é trabalho desperdiçado ~60×/s.
 */
export const draggingSegmentId = ref<string | null>(null);
export const draggingShelfId = ref<string | null>(null);
export const draggingShelfSectionId = ref<string | null>(null);
export const draggingShelfOffset = ref(0); // Offset em pixels de onde clicou na shelf

// Configurações visuais
export const scaleFactor = ref(3);
export const showProductsPanel = ref(true);
export const showPropertiesPanel = ref(false);

// Estados de UI
export const showPerformanceModal = ref(false);
export const showGrid = ref(false);
export const showZoneIndicators = ref(readZoneIndicatorsFromStorage());

// Persiste no localStorage sempre que o estado das zonas mudar
watch(showZoneIndicators, (value) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY_ZONE_INDICATORS, String(value));
    }
});

/**
 * Indicador de produto exibido na frente de cada item da gôndola.
 * Guarda a `key` do indicador ativo (ex.: 'price', 'margin', 'stock', 'rupture')
 * ou 'none' quando nenhum está selecionado. Veja `editor/indicators.ts`.
 */
export const selectedIndicator = ref<string>(readSelectedIndicatorFromStorage());

// Persiste no localStorage sempre que o indicador selecionado mudar
watch(selectedIndicator, (value) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY_PRODUCT_INDICATOR, value);
    }
});

/**
 * Orientação do selo de indicador na frente do produto:
 * - `'vertical'`: selo rotacionado 90° (padrão legado), economiza largura.
 * - `'horizontal'`: selo na horizontal, mais legível.
 */
export const indicatorOrientation = ref<IndicatorOrientation>(readIndicatorOrientationFromStorage());

// Persiste no localStorage sempre que a orientação do selo mudar
watch(indicatorOrientation, (value) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY_INDICATOR_ORIENTATION, value);
    }
});

/**
 * Estilo visual da tábua da prateleira, compartilhado por todas as instâncias de
 * `ShelfBoard.vue`. Trocar o valor re-renderiza apenas as tábuas (ação rara,
 * disparada pelo usuário) — mesmo padrão de `showZoneIndicators`.
 */
export const shelfBoardStyle = ref<ShelfBoardStyle>(readShelfBoardStyleFromStorage());

// Persiste no localStorage sempre que o estilo da tábua mudar
watch(shelfBoardStyle, (value) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY_SHELF_BOARD_STYLE, value);
    }
});

// Valor do campo de busca por EAN. Bound ao <Input> e preenchido automaticamente
// ao clicar num produto. NÃO dispara o highlight sozinho — apenas reflete o que
// está digitado/preenchido no campo.
export const eanSearchQuery = ref('');

/**
 * Busca efetivamente APLICADA — só muda quando o usuário clica em "Buscar"
 * (ou tecla Enter no campo). É esta ref que dispara as reações CARAS:
 * - o highlight `isEanMatch` em cada Segment
 * - a varredura da gôndola que seleciona o produto encontrado
 *
 * Separar o "campo" da "busca aplicada" evita que cada tecla (ou cada clique
 * que preenche o campo) recalcule o highlight de centenas de segmentos e
 * percorra a gôndola inteira — especialmente porque EANs brasileiros começam
 * todos com "789", então prefixos curtos casariam com quase todos os produtos.
 */
export const eanSearchApplied = ref('');

/** category_id da categoria de template selecionada para highlight no canvas */
export const selectedTemplateCategoryId = ref<string | null>(null);

// Produtos rejeitados na última geração automática
export interface RejectedProduct {
    id: string;
    product_id: string;
    product_name: string;
    ean: string | null;
    image_url: string | null;
    product_width: number | null;
    product_height: number | null;
    rejection_reason: string;
    rejection_reason_label: string;
    slot_id: string | null;
    category_name: string | null;
    category_id: string | null;
    module_number: number | null;
    shelf_order: number | null;
    rejected_shelf_orders: number[] | null;
}

export const rejectedProducts = ref<RejectedProduct[]>([]);
export const isLoadingRejectedProducts = ref(false);
