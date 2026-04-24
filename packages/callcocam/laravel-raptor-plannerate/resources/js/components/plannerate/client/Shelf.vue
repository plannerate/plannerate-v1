<template>
    <div
        class="absolute transition-all"
        :style="shelfAreaStyle"
    >
        <!-- Segmentos -->
        <div
            v-if="segments.length > 0"
            class="absolute right-0 bottom-0 left-0 flex items-end gap-0.5"
            :style="{ paddingBottom: `${shelfHeight}px` }"
        >
            <Segment
                v-for="segment in segments"
                :key="segment.id"
                :segment="segment"
                :scale="scale"
                :shelf-depth="shelfDepth"
                @product-click="emit('product-click', $event)"
            />
        </div>

        <!-- Base da prateleira -->
        <div
            :style="{ height: `${shelfHeight}px` }"
            class="absolute right-0 bottom-0 left-0 border-t-2 border-slate-700 bg-slate-800/95 dark:border-slate-600 dark:bg-slate-700/95"
        >
            <!-- Shelf label -->
            <div
                class="pointer-events-none absolute top-1/2 left-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center text-[10px]"
            >
                <span class="px-2 font-medium text-slate-300">
                    Shelf #{{ shelfDisplayNumber }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { Section, Shelf as ShelfType } from '@/types/planogram';
import { computed } from 'vue';
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
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'product-click', ean: string): void;
}>();

const scale = computed(() => props.scale || 3);
const shelfHeightCm = computed(() => props.shelf.shelf_height || 0.3);
const shelfHeight = computed(() => shelfHeightCm.value * scale.value);
const shelfDepth = computed(() => props.shelf.shelf_depth || 40);
const segments = computed(() => props.shelf.segments?.filter(s => !s.deleted_at) || []);

// Calcula número de exibição (de baixo para cima)
const shelfDisplayNumber = computed(() => {
    if (!props.section?.shelves) return 1;
    
    const sortedShelves = [...props.section.shelves]
        .filter((s) => !s.deleted_at)
        .sort((a, b) => (b.shelf_position || 0) - (a.shelf_position || 0));
    
    const index = sortedShelves.findIndex((s) => s.id === props.shelf.id);
    return index >= 0 ? index + 1 : 1;
});

const shelfAreaStyle = computed(() => {
    const shelfPosition = props.shelf.shelf_position || 0;
    const minAreaHeight = 5;
    
    let areaStartCm: number;
    
    if (props.previousShelf) {
        const previousEnd = 
            (props.previousShelf.shelf_position || 0) + 
            (props.previousShelf.shelf_height || 0.3);
        areaStartCm = previousEnd;
    } else {
        areaStartCm = Math.max(0, shelfPosition - minAreaHeight);
    }
    
    const areaEndCm = shelfPosition + shelfHeightCm.value;
    let areaHeightCm = areaEndCm - areaStartCm;
    
    if (areaHeightCm < minAreaHeight) {
        const newStart = Math.max(0, areaEndCm - minAreaHeight);
        
        if (props.previousShelf) {
            const previousEnd =
                (props.previousShelf.shelf_position || 0) +
                (props.previousShelf.shelf_height || 0.3);
            areaStartCm = Math.max(previousEnd, newStart);
        } else {
            areaStartCm = newStart;
        }
        
        areaHeightCm = areaEndCm - areaStartCm;
    }
    
    return {
        top: `${areaStartCm * scale.value}px`,
        height: `${areaHeightCm * scale.value}px`,
        left: `${props.cremalheiraWidth || 0}px`,
        right: `${props.cremalheiraWidth || 0}px`,
    };
});
</script>

