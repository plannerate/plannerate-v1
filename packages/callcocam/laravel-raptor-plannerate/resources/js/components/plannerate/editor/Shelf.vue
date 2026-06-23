<template>
    <!-- Container: Área total da prateleira (do chão/prateleira anterior até esta) -->
    <div
        data-shelf-area="true"
        class="group/shelf absolute hover:bg-primary/10"
        :class="[
            isSelected ? 'bg-primary/10 ring-2 ring-inset ring-primary' : '',
            showZoneIndicators ? shelfZone.bgClass : '',
            isCategoryHighlighted && !isSelected ? 'ring-2 ring-inset ring-green-500' : '',
        ]"
        :style="shelfAreaStyle"
        tabindex="0"
        @focus="handleFocusShelf"
        @click="handleSelectShelf"
        @dragover.prevent="handleProductDragOver"
        @dragleave="handleProductDragLeave"
        @drop.prevent="handleProductDrop"
    >
        <!-- Faixa lateral de zona — lateral esquerda -->
        <div
            v-if="showZoneIndicators"
            class="absolute top-0 left-0 flex h-full w-1.5 cursor-default items-center justify-center"
            :class="shelfZone.borderClass"
            :title="shelfZone.label"
            style="z-index: 135"
            @click.stop
        />
        <!-- Segmentos um do lado do outro (horizontalmente) -->
        <div v-if="segments.length > 0" class="absolute right-0 left-0 flex"
            :class="[alignmentClass, isHookType ? 'items-start' : 'items-end']"
            style="z-index: 50; pointer-events: none" :style="[segmentsPositionStyle, justifyDistributionStyle]">
            <Segment v-for="(segment, index) in segments" :key="segment.id" :segment="segment" :scale="scale"
                :shelf-depth="shelf.shelf_depth" :isFirstInShelf="index === 0"
                :isLastInShelf="index === segments.length - 1" :facing-gap="justifyGap ?? undefined"
                :selected-from-parent="segment.id === selectedSegmentId || multiSelectedSegmentIds.has(segment.id)"
                :layer-selected-from-parent="!!(segment.layer?.id && segment.layer.id === selectedLayerId)"
                style="pointer-events: auto" />
        </div>

        <!-- Drag Handle para mover a shelf -->

        <!-- Área de Drop Personalizada -->
        <Transition enter-active-class="transition-opacity duration-75 ease-out" enter-from-class="opacity-0"
            enter-to-class="opacity-100" leave-active-class="transition-opacity duration-75 ease-in"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="isDropTarget"
                class="pointer-events-none absolute inset-0  flex flex-col items-center justify-center gap-2 rounded-sm border-2 border-dashed border-primary bg-primary/15"
                :style="{
                    zIndex: 140,
                }">
                <svg class="size-8 text-primary drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <span class="text-sm font-semibold text-primary drop-shadow-lg">
                    Solte aqui
                </span>
                <span class="text-xs font-medium text-primary/80 drop-shadow">
                    Prat #{{ shelfDisplayNumber }}
                </span>
                <span class="mt-1 text-[10px] font-light text-primary/70">
                    Ctrl para copiar
                </span>
            </div>
        </Transition>

        <!-- Base da prateleira (superfície física) - ARRASTÁVEL -->
        <div data-shelf="true" draggable="true" :style="{
            height: `${shelfHeight}px`,
            top: `${shelfBasePosition}px`,
            zIndex: isDraggingShelf ? 120 : 130,    
        }"
            class="absolute right-0 left-0 cursor-grab border-t-2 border-slate-700 bg-slate-800/95 active:cursor-grabbing dark:border-slate-600 dark:bg-slate-700/95"
            :class="{
                'cursor-grabbing opacity-50 ring-2 ring-primary':
                    isDraggingShelf,
                'hover:border-slate-600 hover:bg-slate-700/95 hover:ring-1 hover:ring-slate-500':
                    !isDraggingShelf,
                'ring-2 ring-inset ring-green-500': isCategoryHighlighted,
            }" @mousedown="handleMouseDown" @dragstart.stop="handleShelfDragStart" @dragend.stop="handleShelfDragEnd"
            @click.stop="handleSelectShelf">
            <!-- Shelf label -->
            <div class="pointer-events-none absolute top-1/2 left-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center"
                :style="{
                    fontSize: `${Math.max(8, Math.min(16, (10 * scale) / 3))}px`,
                    zIndex: 1,
                }">
                <span class="flex items-center px-2 font-medium text-slate-300">
                    Prat #{{ shelfDisplayNumber }}
                </span>
            </div>

            <!-- Indicador visual de arrasto (aparece no hover) -->
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, toRef } from 'vue';
import { useShelfDrag } from '../../../composables/plannerate/interactions/useShelfDrag';
import { useShelfDragDrop } from '../../../composables/plannerate/interactions/useShelfDragDrop';
import { useShelfLayout } from '../../../composables/plannerate/geometry/useShelfLayout';
import { usePlanogramEditor } from '../../../composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/core/usePlanogramSelection';
import type { Section, Shelf as ShelfType } from '../../../types/planogram';
import Segment from './Segment.vue';
import { selectedTemplateCategoryId, showZoneIndicators } from '../../../composables/plannerate/core/useGondolaState';

interface Props {
    shelf: ShelfType;
    section: Section;
    scale?: number;
    holes?: any[];
    sectionWidth?: number;
    sectionHeight?: number;
    cremalheiraWidth?: number;
    previousShelf?: ShelfType;
    nextShelf?: ShelfType;
    firstShelf?: ShelfType;
    lastShelf?: ShelfType;
    isLast?: boolean;
    /** Número de exibição ("Prat #N") pré-calculado por Shelves.vue (evita sort O(S) por shelf) */
    displayNumber?: number;
}

const props = defineProps<Props>();

const scale = computed(() => props.scale || 3);

const selection = usePlanogramSelection();
const editor = usePlanogramEditor();

const shelfRef = toRef(props, 'shelf');
const sectionRef = toRef(props, 'section');
const previousShelfRef = toRef(props, 'previousShelf');
const sectionWidthRef = toRef(props, 'sectionWidth');
const cremalheiraWidthRef = toRef(props, 'cremalheiraWidth');
const displayNumberRef = toRef(props, 'displayNumber');

// Usa composable para drag & drop de produtos/segments apenas
const {
    isDropTarget,
    handleDragOver: handleProductDragOver,
    handleDragLeave: handleProductDragLeave,
    handleDrop: handleProductDrop,
} = useShelfDragDrop(shelfRef.value.id);

const {
    isDraggingShelf,
    handleMouseDown,
    handleShelfDragStart,
    handleShelfDragEnd,
} = useShelfDrag({
    shelf: shelfRef,
    sectionId: computed(() => sectionRef.value.id),
});

const {
    shelfHeight,
    shelfAreaStyle,
    shelfBasePosition,
    segments,
    isHookType,
    shelfDisplayNumber,
    shelfZone,
    justifyGap,
    alignmentClass,
    justifyDistributionStyle,
    segmentsPositionStyle,
} = useShelfLayout({
    shelf: shelfRef,
    section: sectionRef,
    previousShelf: previousShelfRef,
    scale,
    sectionWidth: sectionWidthRef,
    cremalheiraWidth: cremalheiraWidthRef,
    alignment: computed(() => editor.currentGondola.value?.alignment),
    displayNumber: displayNumberRef,
});

const isSelected = computed(() => selection.isShelfSelected(shelfRef.value));

// ── Seleção de segmentos calculada aqui (nível Shelf), ESCOPADA a esta shelf ──
// Antes, cada Segment subscrevia individualmente a selectedId e selectedItems,
// causando uma cascata de N recomputações por clique (N = nº de segmentos).
// O passo anterior moveu a subscrição para a Shelf, mas os computeds devolviam o
// ID GLOBAL — logo o valor mudava para TODAS as shelves a cada clique, forçando
// todas a re-renderizar o v-for de segmentos (o lag perceptível).
//
// Agora os computeds só devolvem o ID quando o item selecionado pertence a ESTA
// shelf; caso contrário devolvem null/Set vazio ESTÁVEIS. Assim o valor só muda
// nas 2 shelves envolvidas (a que perde e a que ganha a seleção) — todas as
// outras mantêm o mesmo valor e o Vue pula o re-render delas. O comportamento é
// idêntico: o binding nunca compara IDs de outra shelf.

// Set estável reutilizado quando esta shelf não tem nenhum item multi-selecionado.
// Manter a MESMA referência evita que o computed "mude" de valor (Set é comparado
// por identidade) e dispare re-render desnecessário.
const EMPTY_ID_SET: ReadonlySet<string> = new Set();

/** IDs dos segmentos desta shelf — recalcula só quando os segmentos mudam (não na seleção). */
const shelfSegmentIdSet = computed<Set<string>>(
    () => new Set(segments.value.map((s) => s.id)),
);

/** IDs das layers desta shelf — idem, independente da seleção. */
const shelfLayerIdSet = computed<Set<string>>(() => {
    const ids = new Set<string>();
    for (const s of segments.value) {
        if (s.layer?.id) {
            ids.add(s.layer.id);
        }
    }
    return ids;
});

/** ID do segmento em single-select SE pertencer a esta shelf; senão null estável. */
const selectedSegmentId = computed<string | null>(() => {
    if (selection.selectedType.value !== 'segment') {
        return null;
    }
    const id = selection.selectedId.value;
    return id && shelfSegmentIdSet.value.has(id) ? id : null;
});

/** IDs em multi-select restritos a esta shelf (Set para lookup O(1)). */
const multiSelectedSegmentIds = computed<ReadonlySet<string>>(() => {
    const items = selection.selectedItems.value;
    if (!items.length) {
        return EMPTY_ID_SET;
    }
    const own = shelfSegmentIdSet.value;
    let mine: Set<string> | null = null;
    for (const i of items) {
        if (i.type === 'segment' && own.has(i.id)) {
            (mine ??= new Set()).add(i.id);
        }
    }
    return mine ?? EMPTY_ID_SET;
});

/** ID da layer selecionada SE pertencer a esta shelf; senão null estável. */
const selectedLayerId = computed<string | null>(() => {
    if (selection.selectedType.value !== 'layer') {
        return null;
    }
    const id = selection.selectedId.value;
    return id && shelfLayerIdSet.value.has(id) ? id : null;
});
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Verdadeiro quando a categoria do template_slot desta prateleira bate com a
 * categoria selecionada no CategoryConfigPanel.
 * Usado para destaque visual bidirecional (categoria ↔ prateleira).
 *
 * Lê `selectedTemplateCategoryId` direto do estado global (em vez de receber via
 * prop drilada). Só as shelves cujo booleano vira re-renderizam ao trocar a
 * categoria; Canvas/Sections/Section/Shelves não re-renderizam mais em cascata.
 */
const isCategoryHighlighted = computed(
    () =>
        selectedTemplateCategoryId.value != null &&
        !!props.shelf.template_slot?.category_id &&
        props.shelf.template_slot.category_id === selectedTemplateCategoryId.value,
);

function handleFocusShelf() {
    selection.selectItem('shelf', props.shelf.id, props.shelf, {
        section: props.section,
        lastShelf: props.lastShelf,
        firstShelf: props.firstShelf,
    });
    selectedTemplateCategoryId.value = props.shelf.template_slot?.category_id ?? null;
}

function handleSelectShelf(event: MouseEvent) {
    event.stopPropagation();
    selection.selectItem('shelf', props.shelf.id, props.shelf, {
        section: props.section,
        lastShelf: props.lastShelf,
        firstShelf: props.firstShelf,
    });
    // Sincroniza a categoria do template com o estado global para que o
    // CategoryConfigPanel realce o card correspondente.
    selectedTemplateCategoryId.value = props.shelf.template_slot?.category_id ?? null;
}
</script>
