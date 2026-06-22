// ============================================================================
// ESTADO GLOBAL DO EDITOR (SINGLETON PATTERN)
// Refs compartilhados entre todas as instâncias do editor
// ============================================================================

import { ref, watch } from 'vue';
import type { Gondola } from '@/types/planogram';

/** Chave de persistência do estado de exibição das zonas de exposição */
const STORAGE_KEY_ZONE_INDICATORS = 'plannerate:showZoneIndicators';

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

// Estado da gôndola atual
export const currentGondola = ref<Gondola | null>(null);

// Estado de drag & drop
export const draggingSegmentShelfId = ref<string | null>(null);
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

// Busca de produto por EAN na gondola atual.
// Bound diretamente ao <Input> — atualiza a cada tecla (digitação instantânea).
export const eanSearchQuery = ref('');

/**
 * Versão "debounced" de eanSearchQuery, usada pelas reações CARAS:
 * - o highlight `isEanMatch` em cada Segment
 * - o watcher que percorre a gondola e seleciona o produto encontrado
 *
 * Sem isto, cada tecla recalculava o highlight de centenas de segmentos e
 * percorria a gondola inteira. Pior: EANs brasileiros começam todos com "789",
 * então prefixos curtos casam com quase todos os produtos, ligando centenas de
 * transições CSS de uma vez. O debounce faz a reação cara rodar só ~250ms após
 * o usuário parar de digitar.
 */
export const eanSearchDebounced = ref('');

let _eanDebounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(eanSearchQuery, (value) => {
    if (_eanDebounceTimer) {
        clearTimeout(_eanDebounceTimer);
    }

    _eanDebounceTimer = setTimeout(() => {
        eanSearchDebounced.value = value;
    }, 250);
});

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
