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
