<template>
    <!-- Container: Área total da prateleira (do chão/prateleira anterior até esta) -->
    <div data-shelf-area="true" class="group/shelf absolute hover:bg-primary/10" :class="{
        'bg-primary/10 ring-2 ring-primary': isSelected,
    }" :style="shelfAreaStyle" @click="handleSelectShelf" @dragover.prevent="handleProductDragOver"
        @dragleave="handleProductDragLeave" @drop.prevent="handleProductDrop">
        <!-- Segmentos um do lado do outro (horizontalmente) -->
        <div v-if="segments.length > 0" class="absolute right-0 left-0 flex" :class="[
            alignmentClass,
            isHookType ? 'items-start' : 'items-end'
        ]" style="z-index: 50; pointer-events: none" :style="segmentsPositionStyle">
            <Segment v-for="(segment, index) in segments" :key="segment.id" :segment="segment" :scale="scale"
                :sectionWidth="sectionWidth" :shelf-depth="shelf.shelf_depth" :isFirstInShelf="index === 0"
                :isLastInShelf="index === segments.length - 1" :fill-section-width="isSingleSegmentJustify"
                :internal-alignment="isSingleSegmentJustify ? 'justify' : undefined" style="pointer-events: auto" />
        </div>

        <!-- Drag Handle para mover a shelf -->

        <!-- Área de Drop Personalizada -->
        <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100" leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="isDropTarget"
                class="pointer-events-none absolute inset-0 z-[500] flex flex-col items-center justify-center gap-2 rounded-sm border-2 border-dashed border-primary bg-primary/10 backdrop-blur-sm">
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
            top: `${shelfBasePosition}px`
        }"
            class="absolute right-0 z-[100] left-0 hover:z-[1000] cursor-grab active:cursor-grabbing border-t-2 border-slate-700 bg-slate-800/95 dark:border-slate-600 dark:bg-slate-700/95"
            :class="{
                'opacity-50 ring-2 ring-primary cursor-grabbing': isDraggingShelf,
                'hover:border-slate-600 hover:bg-slate-700/95 hover:ring-1 hover:ring-slate-500':
                    !isDraggingShelf,
            }" @mousedown="handleMouseDown" @dragstart.stop="handleShelfDragStart" @dragend.stop="handleShelfDragEnd"
            @click.stop="handleSelectShelf">
            <!-- Shelf label -->
            <div class="pointer-events-none absolute top-1/2 left-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center"
                :style="{ fontSize: `${Math.max(8, Math.min(16, 10 * scale / 3))}px`, zIndex: 1 }">
                <span class="px-2 font-medium text-slate-300 flex items-center">
                    Prat #{{ shelfDisplayNumber }}
                </span>
            </div>

            <!-- Indicador visual de arrasto (aparece no hover) -->

        </div>
    </div>
</template>

<script setup lang="ts">
import { useShelfDrag } from '../../../composables/plannerate/editor/useShelfDrag';
import { useShelfDragDrop } from '../../../composables/plannerate/editor/useShelfDragDrop';
import { useShelfLayout } from '../../../composables/plannerate/editor/useShelfLayout';
import { usePlanogramEditor } from '../../../composables/plannerate/usePlanogramEditor';
import { usePlanogramSelection } from '../../../composables/plannerate/usePlanogramSelection';
import type { Section, Shelf as ShelfType } from '../../../types/planogram';
import { computed, toRef } from 'vue';
import Segment from './Segment.vue';

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

// Usa composable para drag & drop de produtos/segments apenas
const {
    isDropTarget,
    handleDragOver: handleProductDragOver,
    handleDragLeave: handleProductDragLeave,
    handleDrop: handleProductDrop,
} = useShelfDragDrop(shelfRef.value.id);

const { isDraggingShelf, handleMouseDown, handleShelfDragStart, handleShelfDragEnd } =
    useShelfDrag({
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
    isSingleSegmentJustify,
    alignmentClass,
    segmentsPositionStyle,
} = useShelfLayout({
    shelf: shelfRef,
    section: sectionRef,
    previousShelf: previousShelfRef,
    scale,
    sectionWidth: sectionWidthRef,
    cremalheiraWidth: cremalheiraWidthRef,
    alignment: computed(() => editor.currentGondola.value?.alignment),
});

const isSelected = computed(() => selection.isShelfSelected(shelfRef.value));

function handleSelectShelf(event: MouseEvent) {
    event.stopPropagation();
    selection.selectItem('shelf', props.shelf.id, props.shelf, {
        section: props.section,
        lastShelf: props.lastShelf,
        firstShelf: props.firstShelf,
    });
}
</script>
