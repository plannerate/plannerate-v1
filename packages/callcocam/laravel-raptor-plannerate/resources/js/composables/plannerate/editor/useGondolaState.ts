// ============================================================================
// ESTADO GLOBAL DO EDITOR (SINGLETON PATTERN)
// Refs compartilhados entre todas as instâncias do editor
// ============================================================================

import { ref } from 'vue';
import type { Gondola, Section, Segment, Shelf } from '@/types/planogram';

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

// Estado de seleção
export const selectedType = ref<
    'section' | 'shelf' | 'segment' | 'layer' | null
>(null);
export const selectedId = ref<string | null>(null);
export const selectedItem = ref<Section | Shelf | Segment | null>(null);

// Estados de UI
export const showDeleteConfirmation = ref(false);
export const showAddModuleDrawer = ref(false);
export const showPerformanceModal = ref(false);
export const showGrid = ref(false);
export const showZoneIndicators = ref(true);

// Busca de produto por EAN na gondola atual
export const eanSearchQuery = ref('');

// Grouping de template selecionado para highlight no canvas
export const selectedTemplateGroupingNormalized = ref<string | null>(null);

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
    grouping: string | null;
    grouping_normalized: string | null;
    module_number: number | null;
    shelf_order: number | null;
}

export const rejectedProducts = ref<RejectedProduct[]>([]);
export const isLoadingRejectedProducts = ref(false);
