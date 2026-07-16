<template>
    <!-- Segment com drop direto (troca de posições) -->
    <div
        class="relative flex flex-col items-start"
        tabindex="0"
        :class="{
            'ring-2 ring-primary/70 ring-offset-1 bg-primary/10 z-50': isSegmentSelected,
            'ring-2 ring-amber-500/70 ring-offset-1 bg-amber-100/50 z-40':
                isEanMatch && !isSegmentSelected && !isDropTarget,
            'hover:opacity-90':
                !isSegmentSelected && !isDragging && !isDropTarget,
            'cursor-grabbing opacity-40': isDragging,
            'cursor-grab': !isDragging && !isDropTarget,
            'cursor-pointer': isDropTarget,
            'scale-105 bg-primary/10 shadow-lg ring-4 ring-primary':
                isDropTarget,
        }"
        draggable="true"
        @pointerdown="handlePointerDown"
        @focus="handleFocusSegment"
        @click="handleSegmentClick"
        @dblclick="handleSegmentDoubleClick"
        @contextmenu="handleContextMenu"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
        @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave"
        @drop.prevent="handleDrop"
        :data-segment-id="segment.id"
        :data-layer-id="layer?.id"
        :data-module="moduleNumber"
        :data-shelf="shelfNumber"
        data-segment="true"
    >

        <!--
            Selos de papel e de quadrante BCG dividem o topo do segmento.
            Ficam na MESMA linha de propósito: são duas análises distintas sobre o
            mesmo produto, e empilhá-las ou sobrepô-las por z-index esconderia uma.
        -->
        <div
            v-if="paperRole"
            class="absolute -top-2.5 left-1/2 z-30 flex -translate-x-1/2 items-center gap-0.5"
        >
            <PaperRoleBadge :role="paperRole" />
        </div>

        <!--
            Selo BCG no topo. Wrapper em left-1/2 sem -translate-x-1/2: o próprio pill se
            centraliza/rotaciona (igual ao selo ABC), respeitando a orientação da tag.
        -->
        <div
            v-if="bcgBadgeData"
            class="pointer-events-none absolute left-1/2 z-90 flex items-center"
            :style="bcgBadgeWrapperStyle"
        >
            <BcgBadge :data="bcgBadgeData" :scale="props.scale" />
        </div>

        <!-- Badge de sortimento ABC, na base do segmento -->
        <div
            v-if="abcClassification"
            class="pointer-events-none absolute left-1/2 z-90 flex items-center"
            :style="abcBadgeWrapperStyle"
        >
            <AbcBadge :classification="abcClassification" :recommendation="abcRecommendation" :scale="props.scale" />
        </div>

        <!-- Indicador visual de estoque alvo -->
        <StockIndicator :segment="segment" :shelf-depth="shelfDepth" :scale="props.scale" @click="handleSegmentClick" />

        <!-- Selo configurável de indicador (Preço, Margem, Estoque, Ruptura) -->
        <ProductIndicatorBadge :product="layer?.product" :scale="props.scale" />

        <!-- Indicador visual de drop -->
        <div
            v-if="isDropTarget"
            class="pointer-events-none absolute inset-0 z-50 flex items-center justify-center rounded bg-primary/30"
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
                    :module-number="props.moduleNumber"
                    :shelf-number="props.shelfNumber"
                />
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAbcClassification } from '../../../composables/plannerate/analysis/useAbcClassification';
import { useBcgAnalysis } from '../../../composables/plannerate/analysis/useBcgAnalysis';
import { usePaperAnalysis } from '../../../composables/plannerate/analysis/usePaperAnalysis';
import {
    draggingSegmentId,
    draggingSegmentShelfId,
    eanSearchApplied,
} from '../../../composables/plannerate/core/useGondolaState';
import { usePlanogramEditor } from '../../../composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/core/usePlanogramSelection';
import { DND_KEYS, hasSegmentData, isCopyModifier, setSegmentDragData } from '../../../composables/plannerate/dnd/transfer';
import type { Layer, Segment } from '../../../types/planogram';
import AbcBadge from './AbcBadge.vue';
import BcgBadge from './BcgBadge.vue';
import LayerRenderer from './Layer.vue';
import PaperRoleBadge from './PaperRoleBadge.vue';
import ProductIndicatorBadge from './ProductIndicatorBadge.vue';
import StockIndicator from './StockIndicator.vue';

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
    /**
     * Pré-computado pela Shelf pai — evita que cada Segment subscreva diretamente
     * ao estado global de seleção (selectedId / selectedItems), eliminando a cascata
     * de N recomputações por clique. Quando o selectedId muda, apenas a Shelf
     * re-renderiza; o vdom diff do Vue atualiza somente os 2 segmentos cujos
     * props realmente mudaram (anterior + novo selecionado).
     */
    selectedFromParent?: boolean;
    layerSelectedFromParent?: boolean;
    /** Diagnóstico: nº do módulo (section.ordering) e nº da prateleira ("Prat -N"),
     *  repassados às imagens como data-attrs para correlacionar lentidão no profiling. */
    moduleNumber?: number | string;
    shelfNumber?: number | string;
}

const props = defineProps<Props>();
const layer = computed<Layer | undefined>(() => props.segment.layer);
const selection = usePlanogramSelection();
const editor = usePlanogramEditor();

/** Abre o painel de propriedades (injetado pelo PlanogramEditor) */
const openProperties = inject<() => void>('openProperties');
const { getClassification, getRecommendation } = useAbcClassification();
const { getPaperRole } = usePaperAnalysis();
const { getBcgData } = useBcgAnalysis();

const getQuantity = computed(() => props.segment.quantity || 1);

// Busca classificação ABC do produto pelo EAN
const abcClassification = computed(() => getClassification(layer.value?.product?.ean));

// Recomendação de sortimento (proteger/potencializar/monitorar/retirar) do produto pelo EAN
const abcRecommendation = computed(() => getRecommendation(layer.value?.product?.ean));

/**
 * Distância da base do selo ABC ao rodapé do segmento, proporcional à escala
 * do planograma (como o restante das medidas), com piso mínimo para não colar
 * na prateleira em escalas pequenas.
 */
const abcBadgeWrapperStyle = computed(() => ({
    bottom: `${Math.max((props.scale || 3) * 2, 4)}px`,
}));

/** Selo BCG na mesma posição do ABC (base do segmento), escalonado. */
const bcgBadgeWrapperStyle = computed(() => ({
    bottom: `${Math.max((props.scale || 3) * 2, 4)}px`,
}));

// Busca papel estratégico do produto pelo EAN
const paperRole = computed(() => getPaperRole(layer.value?.product?.ean));

// Busca quadrante BCG + ação de espaço: pelo EAN (por produto) ou, no modo por
// categoria, pelo id do produto (cai no selo do grupo ao qual ele pertence).
const bcgBadgeData = computed(() => getBcgData(layer.value?.product?.ean, layer.value?.product?.id));

const isEanMatch = computed(() => {
    const query = eanSearchApplied.value.trim();
    const productEan = String(layer.value?.product?.ean ?? '').trim();

    return !!(query && productEan && productEan.includes(query));
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

/**
 * Seleciona ESTE segmento, evitando re-selecionar o que já está ativo.
 * Re-selecionar recria `selectedItem` e dispara re-render desnecessário do
 * painel de propriedades — o guard abaixo elimina esse custo no re-clique.
 */
function selectThisSegment() {
    if (
        selection.selectedType.value === 'segment' &&
        selection.selectedId.value === props.segment.id
    ) {
        return;
    }

    selection.selectItem('segment', props.segment.id, props.segment);
}

/**
 * Seleciona já no `pointerdown` (não no `click`).
 *
 * O atributo `draggable="true"` faz o browser converter um micro-movimento
 * durante o clique em `dragstart` e ENGOLE o evento `click` — então a seleção
 * "não pegava" e o usuário precisava reclicar (a sensação de "demora pra
 * liberar o clique"). O `pointerdown` sempre dispara antes de qualquer drag,
 * garantindo que todo clique registre de primeira.
 */
function handlePointerDown(event: PointerEvent) {
    // Só botão primário do mouse / toque — ignora botão direito/auxiliar.
    if (event.button !== 0) {
        return;
    }

    _skipFocusFromMouse = true;
    // Reset após o clique completar (focus acontece em < 50ms)
    setTimeout(() => {
 _skipFocusFromMouse = false; 
}, 50);
    selectThisSegment();
}

onMounted(() => {
    window.addEventListener('dragend', clearDropTarget, true);
    window.addEventListener('drop', clearDropTarget, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('dragend', clearDropTarget, true);
    window.removeEventListener('drop', clearDropTarget, true);
});

function handleFocusSegment() {
    // Ignora focus gerado por mouse — o pointerdown já tratou a seleção.
    // Só seleciona em focus por teclado (Tab).
    if (_skipFocusFromMouse) {
return;
}

    selectThisSegment();
}

function handleSegmentClick(event: MouseEvent) {
    // A seleção já ocorreu no pointerdown; aqui só impedimos o clique de
    // borbulhar para a área da prateleira (que selecionaria a shelf).
    event.stopPropagation();
}

/**
 * Duplo clique: garante que o segmento está selecionado e abre o painel
 * de propriedades caso esteja fechado.
 */
function handleSegmentDoubleClick(event: MouseEvent) {
    event.stopPropagation();
    selectThisSegment();
    openProperties?.();
}

/**
 * No macOS, Ctrl + botão esquerdo é o clique secundário do sistema: abre o menu
 * de contexto e aborta o arraste antes mesmo do dragstart — por isso "segurar
 * Ctrl e arrastar para copiar" não funcionava lá. Suprimimos o menu só nesse
 * caso (contextmenu com Ctrl pressionado); o botão direito puro segue normal.
 */
function handleContextMenu(event: MouseEvent) {
    if (event.ctrlKey) {
        event.preventDefault();
    }
}

function handleDragStart(event: DragEvent) {
    event.stopPropagation();
    isDragging.value = true;

    // Armazena shelf_id e id globalmente para que os handlers de dragover
    // identifiquem origem e o próprio segmento sem ler dataTransfer.getData()
    draggingSegmentShelfId.value = props.segment.shelf_id || null;
    draggingSegmentId.value = props.segment.id;

    if (event.dataTransfer) {
        // Copy com modificador (Alt/Ctrl/Cmd), senão move. A decisão final é
        // reavaliada no drop — ver isCopyModifier (contrato em dnd/transfer).
        setSegmentDragData(
            event.dataTransfer,
            props.segment.id,
            props.segment.shelf_id || '',
            isCopyModifier(event),
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
    // Limpa o estado global de arraste
    draggingSegmentShelfId.value = null;
    draggingSegmentId.value = null;
}

/**
 * Atualiza isDropTarget só quando o valor muda — evita disparar o setter
 * reativo (e potencial re-render) a cada dragover (~60×/s).
 */
function setDropTarget(value: boolean): void {
    if (isDropTarget.value !== value) {
        isDropTarget.value = value;
    }
}

// Handler para dragover - aceita segments da mesma shelf
function handleDragOver(event: DragEvent) {
    if (!event.dataTransfer) {
        return;
    }

    if (!hasSegmentData(event.dataTransfer)) {
        setDropTarget(false);

        return;
    }

    // Identifica origem pelo estado global (getData retorna vazio no dragover).
    // Nunca marca o próprio segmento como alvo, e só aceita da mesma shelf.
    const isOwnSegment = draggingSegmentId.value === props.segment.id;
    const isSameShelf = draggingSegmentShelfId.value === props.segment.shelf_id;

    if (!isOwnSegment && isSameShelf) {
        event.dataTransfer.dropEffect = 'move';
        setDropTarget(true);

        return;
    }

    setDropTarget(false);
}

function handleDragLeave(event: DragEvent) {
    const target = event.currentTarget as HTMLElement;
    const relatedTarget = event.relatedTarget as Node | null;

    // Se ainda está dentro do segmento, não limpa (evita flicker em filhos)
    if (relatedTarget && target.contains(relatedTarget)) {
        return;
    }

    // Saiu do segmento (ou da janela, quando relatedTarget é null) — sem ler
    // geometria (getBoundingClientRect força reflow síncrono no hot path).
    setDropTarget(false);
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
