<template>
    <div
        :style="sectionStyle"
        class="relative transition-all"
    >
        <Cremalheira :section="section" side="left" :scale="scale" />
        <Shelves
            :shelves="sortedShelves"
            :section="section"
            :scale="scale"
            :sectionWidth="sectionWidth"
            :sectionHeight="sectionHeight"
            :holes="holes"
            :cremalheiraWidth="cremalheiraWidth"
            @product-click="emit('product-click', $event)"
        />
        <div
            class="absolute bottom-0 left-0 flex w-full items-center justify-center"
        >
            <div class="text-xs text-muted-foreground">
                Modulo #{{ section.ordering }}
            </div>
        </div>
        <Cremalheira :section="section" side="right" :scale="scale" />
    </div>
</template>

<script setup lang="ts">
import {
    calculateHoles,
} from '@/composables/plannerate/v3/useSectionHoles';
import type { Section as SectionType } from '@/types/planogram';
import { computed } from 'vue';
import Cremalheira from './Cremalheira.vue';
import Shelves from './Shelves.vue';

interface Props {
    section: SectionType;
    lastSection?: boolean;
    firstSection?: boolean;
    scale: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'product-click', ean: string): void;
}>();

const scale = computed(() => props.scale || 3);

const sectionHeight = computed(() => props.section.height * scale.value);
const cremalheiraWidth = computed(
    () => props.section.cremalheira_width * scale.value,
);
const sectionWidth = computed(() => props.section.width * scale.value);
const totalWidth = computed(
    () => sectionWidth.value + 2 * cremalheiraWidth.value,
);

const sortedShelves = computed(() => {
    return [...(props.section.shelves || [])]
        .filter(s => !s.deleted_at)
        .sort((a, b) => (a.shelf_position || 0) - (b.shelf_position || 0));
});

const holes = computed(() => calculateHoles(props.section));
// const _holePositions = computed(() => calculateHolePositions(props.section));

const sectionStyle = computed(() => ({
    height: `${sectionHeight.value}px`,
    width: `${totalWidth.value}px`,
}));
</script>

