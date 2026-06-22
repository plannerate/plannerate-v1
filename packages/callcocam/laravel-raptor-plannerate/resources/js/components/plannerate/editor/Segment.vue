<template>
    <!-- Segment com drop direto (troca de posições) -->
    <div
        class="relative flex flex-col items-start transition-[transform,box-shadow] duration-200"
        tabindex="0"
        :class="{
            'ring-3 ring-primary ring-offset-2 bg-primary/20 shadow-xl scale-[1.02] z-50': isSegmentSelected,
            'ring-2 ring-amber-500/70 ring-offset-1 bg-amber-100/50 shadow-lg z-40':
                isEanMatch && !isSegmentSelected && !isDropTarget,
            'ring-2 ring-emerald-500 ring-offset-1 bg-emerald-50/70 shadow-lg z-40':
                isGroupingMatch && !isSegmentSelected && !isDropTarget,
            'hover:opacity-90':
                !isSegmentSelected && !isDragging && !isDropTarget,
            'cursor-grabbing opacity-40': isDragging,
            'cursor-grab': !isDragging && !isDropTarget,
            'cursor-pointer': isDropTarget,
            'scale-105 bg-primary/10 shadow-lg ring-4 ring-primary':
                isDropTarget,
        }"
        draggable="true"
        @mousedown="handleMouseDown"
        @focus="handleFocusSegment"
        @click="handleSegmentClick"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
        @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave"
        @drop.prevent="handleDrop"
        :data-segment-id="segment.id"
        :data-layer-id="layer?.id"
        data-segment="true"
    >

        <!-- Badges ABC + Papel lado a lado, centralizados no topo do segmento -->
        <div
            v-if="abcClassification || paperRole"
            class="absolute -top-2.5 left-1/2 z-30 flex -translate-x-1/2 items-center gap-0.5"
        >
            <AbcBadge :classification="abcClassification" />
            <PaperRoleBadge :role="paperRole" />
        </div>

        <!-- Indicador visual de estoque alvo -->
        <StockIndicator :segment="segment" :shelf-depth="shelfDepth" :scale="props.scale" @click="handleSegmentClick" />

        <!-- Indicador visual de drop -->
        <div
            v-if="isDropTarget"
            class="pointer-events-none absolute inset-0 z-50 flex items-center justify-center rounded bg-primary/20 backdrop-blur-sm"
        >
            <div class="rounded-full bg-primary p-2 shadow-lg">
                <svg
                    class="size-6 text-primary-foreground"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="3"
                        d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"
                    />
                </svg>
            </div>
        </div>

        <div
            v-for="(_, index) in getQuantity"
            :key="`segment-layer-${index}`"
            class="flex flex-col"
        >
            <div v-if="layer">
                <LayerRenderer
                    :layer="layer"
                    :segment="segment"
                    :scale="props.scale"
                    :is-selected="isLayerSelected"
                    :facing-gap="props.facingGap"
                />
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, onUpdated, ref } from 'vue';
import {
    draggingSegmentShelfId,
    eanSearchApplied,
} from '../../../composables/plannerate/core/useGondolaState';
import { DND_KEYS, hasSegmentData, setSegmentDragData } from '../../../composables/plannerate/dnd/transfer';
import { useAbcClassification } from '../../../composables/plannerate/analysis/useAbcClassification';
import { usePaperAnalysis } from '../../../composables/plannerate/analysis/usePaperAnalysis';
import { usePlanogramEditor } from '../../../composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/core/usePlanogramSelection';
import type { Layer, Segment } from '../../../types/planogram';
import AbcBadge from './AbcBadge.vue';
import PaperRoleBadge from './PaperRoleBadge.vue';
import LayerRenderer from './Layer.vue';
import StockIndicator from './StockIndicator.vue';

// ─── Diagnóstico: conta quantos Segment re-renderizam por interação ──────────
// Ativar: localStorage.setItem('perf:segment', '1') e recarregar.
// Contador em nível de MÓDULO (compartilhado por todas as instâncias) — agrega
// corretamente o total de re-renders disparados por um único clique.
const PERF_ENABLED = typeof window !== 'undefined' && window.localStorage.getItem('perf:segment') === '1';
let _renderCount = 0;
let _flushTimer: ReturnType<typeof setTimeout> | null = null;

/** Acumula um re-render e agenda o relatório agregado (300ms de debounce). */
function _trackRender(): void {
    if (!PERF_ENABLED) return;
    _renderCount++;
    if (_flushTimer) clearTimeout(_flushTimer);
    _flushTimer = setTimeout(() => {
        console.log(`%c[Segment] ${_renderCount} re-render(s) na última interação`,
            'color:#e11d48;font-weight:bold');
        _renderCount = 0;
        _flushTimer = null;
    }, 300);
}
// ──────────────────────────────────────────────────────────────────────────

interface Props {
    segment: Segment;
    scale: number;
    isFirstInShelf?: boolean;
    isLastInShelf?: boolean;
    shelfDepth?: number;
    /**
     * Gap uniforme (px) para o modo justificar. Quando definido, é repassado ao
     * layer como column-gap entre as frentes, fazendo os produtos do segmento
     * se distribuírem com o mesmo espaçamento dos demais segmentos da prateleira.
     */
    facingGap?: number;
    highlightGroupingNormalized?: string | null;
    /**
     * Pré-computado pela Shelf pai — evita que cada Segment subscreva diretamente
     * ao estado global de seleção (selectedId / selectedItems), eliminando a cascata
     * de N recomputações por clique. Quando o selectedId muda, apenas a Shelf
     * re-renderiza; o vdom diff do Vue atualiza somente os 2 segmentos cujos
     * props realmente mudaram (anterior + novo selecionado).
     */
    selectedFromParent?: boolean;
    layerSelectedFromParent?: boolean;
}

const props = defineProps<Props>();
const layer = computed<Layer | undefined>(() => props.segment.layer);
const selection = usePlanogramSelection();
const editor = usePlanogramEditor();
const { getClassification } = useAbcClassification();
const { getPaperRole } = usePaperAnalysis();

const getQuantity = computed(() => props.segment.quantity || 1);

// Busca classificação ABC do produto pelo EAN
const abcClassification = computed(() => getClassification(layer.value?.product?.ean));

// Busca papel estratégico do produto pelo EAN
const paperRole = computed(() => getPaperRole(layer.value?.product?.ean));

const isEanMatch = computed(() => {
    const query = eanSearchApplied.value.trim();
    const productEan = String(layer.value?.product?.ean ?? '').trim();
    return !!(query && productEan && productEan.includes(query));
});

const isGroupingMatch = computed(() => {
    const targetGrouping = (props.highlightGroupingNormalized ?? '').trim();
    const productGrouping = String(layer.value?.product?.category_id ?? '').trim();
    return !!(targetGrouping && productGrouping && targetGrouping === productGrouping);
});

/**
 * Recebido como prop da Shelf pai — não depende mais do estado global aqui.
 * Garante que apenas os segmentos cujo estado de seleção mudou re-renderizam.
 */
const isSegmentSelected = computed(() => props.selectedFromParent ?? false);

/**
 * Recebido como prop da Shelf pai — idem ao isSegmentSelected.
 */
const isLayerSelected = computed(() => props.layerSelectedFromParent ?? false);

// Estado de dragging e drop
const isDragging = ref(false);
const isDropTarget = ref(false);
const clearDropTarget = () => {
    isDropTarget.value = false;
};

// Previne double-fire: ao clicar, o browser dispara @focus ANTES de @click.
// Sem essa flag, selectItem() seria chamado duas vezes no mesmo clique,
// dobrando a cascata de atualizações para todos os segmentos na tela.
let _skipFocusFromMouse = false;

function handleMouseDown() {
    _skipFocusFromMouse = true;
    // Reset após o clique completar (focus + click acontecem em < 50ms)
    setTimeout(() => { _skipFocusFromMouse = false; }, 50);
}

// Diagnóstico: cada re-render deste componente incrementa o contador global
onUpdated(_trackRender);

onMounted(() => {
    window.addEventListener('dragend', clearDropTarget, true);
    window.addEventListener('drop', clearDropTarget, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('dragend', clearDropTarget, true);
    window.removeEventListener('drop', clearDropTarget, true);
});

function handleFocusSegment() {
    // Ignora focus gerado por mouse — o @click vai tratar a seleção
    if (_skipFocusFromMouse) return;
    selection.selectItem('segment', props.segment.id, props.segment);
}

function handleSegmentClick(event: MouseEvent) {
    event.stopPropagation();
    selection.selectItem('segment', props.segment.id, props.segment);
}

function handleDragStart(event: DragEvent) {
    event.stopPropagation();
    isDragging.value = true;

    // Armazena o shelf_id globalmente para que outras shelves possam verificar
    draggingSegmentShelfId.value = props.segment.shelf_id || null;

    if (event.dataTransfer) {
        // Copy se Ctrl estiver pressionado, senão move (contrato em dnd/transfer)
        setSegmentDragData(
            event.dataTransfer,
            props.segment.id,
            props.segment.shelf_id || '',
            event.ctrlKey || event.metaKey,
        );

        // Define uma imagem de arrastar customizada
        const dragImage = event.currentTarget as HTMLElement;

        if (dragImage) {
            event.dataTransfer.setDragImage(dragImage, 20, 20);
        }
    }
}

function handleDragEnd() {
    isDragging.value = false;
    isDropTarget.value = false;
    // Limpa o shelf_id global
    draggingSegmentShelfId.value = null;
}

// Handler para dragover - aceita segments da mesma shelf
function handleDragOver(event: DragEvent) {
    if (!event.dataTransfer) {
return;
}

    if (!hasSegmentData(event.dataTransfer)) {
        isDropTarget.value = false;

        return;
    }

    // Só aceita segments (não produtos) da mesma shelf usando o estado global
    const draggedSegmentId = event.dataTransfer.getData(DND_KEYS.SEGMENT_ID);

    // Nunca marca o próprio segmento como alvo
    if (draggedSegmentId === props.segment.id) {
        isDropTarget.value = false;

        return;
    }

    // Verifica se é da mesma shelf usando o estado global
    if (draggingSegmentShelfId.value === props.segment.shelf_id) {
        event.dataTransfer.dropEffect = 'move';
        isDropTarget.value = true;

        return;
    }

    isDropTarget.value = false;
}

function handleDragLeave(event: DragEvent) {
    const target = event.currentTarget as HTMLElement;
    const relatedTarget = event.relatedTarget as Node | null;

    // Se ainda está dentro do segmento, não limpa (evita flicker em filhos)
    if (relatedTarget && target.contains(relatedTarget)) {
        return;
    }

    const rect = target.getBoundingClientRect();
    const x = event.clientX;
    const y = event.clientY;
    const isOutside = x < rect.left || x >= rect.right || y < rect.top || y >= rect.bottom;

    // Alguns browsers podem reportar 0/0 em dragleave; trate como saída
    if (isOutside || (x === 0 && y === 0)) {
        isDropTarget.value = false;
    }
}

// Handler para drop - troca de posições
function handleDrop(event: DragEvent) {
    if (!event.dataTransfer) {
return;
}

    isDropTarget.value = false;

    const draggedSegmentId = event.dataTransfer.getData(DND_KEYS.SEGMENT_ID);

    if (draggedSegmentId && draggedSegmentId !== props.segment.id) {
        // Troca posições usando o editor (registra no histórico)
        editor.swapSegmentPositions(draggedSegmentId, props.segment.id);
    }

}
</script>
