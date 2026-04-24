<template>
    <!-- Segment com drop direto (troca de posições) -->
    <div
        class="relative flex flex-col items-start transition-all duration-200"
        :style="segmentStyle"
        :class="{
            'ring-3 ring-primary ring-offset-2 bg-primary/20 shadow-xl scale-[1.02] animate-pulse z-50': isSegmentSelected,
            'ring-2 ring-amber-500/70 ring-offset-1 bg-amber-100/50 shadow-lg z-40':
                isEanMatch && !isSegmentSelected && !isDropTarget,
            'hover:opacity-90':
                !isSegmentSelected && !isDragging && !isDropTarget,
            'cursor-grabbing opacity-40': isDragging,
            'cursor-grab': !isDragging && !isDropTarget,
            'cursor-pointer': isDropTarget,
            'scale-105 bg-primary/10 shadow-lg ring-4 ring-primary animate-pulse':
                isDropTarget,
            'w-full': props.fillSectionWidth,
        }"
        draggable="true"
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
        <!-- Indicador visual de performance A, B, C -->
        <AbcBadge :classification="abcClassification" />
        
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
                    :distribution-width="distributionWidth"
                    :internal-alignment="props.internalAlignment"
                />
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">
import {
    draggingSegmentShelfId,
    eanSearchQuery,
} from '../../../composables/plannerate/editor/useGondolaState';
import { usePlanogramEditor } from '../../../composables/plannerate/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/usePlanogramSelection';
import { useAbcClassification } from '../../../composables/plannerate/useAbcClassification';
import { Layer, Segment } from '../../../types/planogram';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import LayerRenderer from './Layer.vue';
import AbcBadge from './AbcBadge.vue';
import StockIndicator from './StockIndicator.vue';

interface Props {
    segment: Segment;
    scale: number;
    isFirstInShelf?: boolean;
    isLastInShelf?: boolean;
    shelfDepth?: number;
    fillSectionWidth?: boolean;
    sectionWidth?: number;
    internalAlignment?: 'left' | 'right' | 'center' | 'justify';
}

const props = defineProps<Props>();
const layer = computed<Layer | undefined>(() => props.segment.layer);
const selection = usePlanogramSelection();
const editor = usePlanogramEditor();
const { getClassification } = useAbcClassification();

const getQuantity = computed(() => props.segment.quantity || 1);

// Busca classificação ABC do produto pelo EAN
const abcClassification = computed(() => {
    const ean = layer.value?.product?.ean;
    return getClassification(ean);
});

const isEanMatch = computed(() => {
    const query = eanSearchQuery.value.trim();
    const productEan = String(layer.value?.product?.ean ?? '').trim();

    if (!query || !productEan) {
        return false;
    }

    return productEan.includes(query);
});

const isSegmentSelected = computed(() => {
    return selection.isSegmentSelected(props.segment);
});

const isLayerSelected = computed(() => {
    return layer.value ? selection.isLayerSelected(layer.value) : false;
});

const distributionWidth = computed(() => {
    if (!props.fillSectionWidth || !props.sectionWidth) {
        return undefined;
    }

    return props.sectionWidth;
});

const segmentStyle = computed(() => {
    if (!props.fillSectionWidth || !props.sectionWidth) {
        return undefined;
    }

    return {
        width: `${props.sectionWidth}px`,
    };
});

// Estado de dragging e drop
const isDragging = ref(false);
const isDropTarget = ref(false);
const clearDropTarget = () => {
    isDropTarget.value = false;
};

onMounted(() => {
    window.addEventListener('dragend', clearDropTarget, true);
    window.addEventListener('drop', clearDropTarget, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('dragend', clearDropTarget, true);
    window.removeEventListener('drop', clearDropTarget, true);
});

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
        // Define o tipo de operação: copy se Ctrl estiver pressionado, senão move
        event.dataTransfer.effectAllowed =
            event.ctrlKey || event.metaKey ? 'copy' : 'move';

        // Define os dados do segmento
        event.dataTransfer.setData(
            'application/x-segment-id',
            props.segment.id,
        );
        event.dataTransfer.setData(
            'application/x-segment-shelf-id',
            props.segment.shelf_id || '',
        );
        event.dataTransfer.setData('text/plain', `Segment ${props.segment.id}`);

        // Armazena se é cópia ou movimento
        event.dataTransfer.setData(
            'application/x-is-copy',
            (event.ctrlKey || event.metaKey).toString(),
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
    if (!event.dataTransfer) return;

    const hasSegment = event.dataTransfer.types.includes('application/x-segment-id');
    if (!hasSegment) {
        isDropTarget.value = false;
        return;
    }

    // Só aceita segments (não produtos) da mesma shelf usando o estado global
    const draggedSegmentId = event.dataTransfer.getData('application/x-segment-id');

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
    if (!event.dataTransfer) return;

    isDropTarget.value = false;

    const draggedSegmentId = event.dataTransfer.getData(
        'application/x-segment-id',
    );

    if (draggedSegmentId && draggedSegmentId !== props.segment.id) {
        // Troca posições usando o editor (registra no histórico)
        editor.swapSegmentPositions(draggedSegmentId, props.segment.id);
    }

}
</script>
