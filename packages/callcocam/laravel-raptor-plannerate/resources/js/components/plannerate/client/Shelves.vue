<template>
    <Shelf
        v-for="shelf in shelves"
        :key="shelf.id"
        :shelf="shelf"
        :section="section"
        :scale="scale"
        :sectionWidth="sectionWidth"
        :sectionHeight="sectionHeight"
        :cremalheiraWidth="cremalheiraWidth"
        :holes="holes"
        :previousShelf="previousShelf(shelf)"
        @product-click="emit('product-click', $event)"
    />
</template>

<script setup lang="ts">
import type { Section, Shelf as ShelfType } from '@/types/planogram';
import Shelf from './Shelf.vue';

interface Props {
    shelves: ShelfType[];
    section: Section;
    scale: number;
    sectionWidth: number;
    sectionHeight?: number;
    cremalheiraWidth?: number;
    holes: any[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'product-click', ean: string): void;
}>();

const previousShelf = (_shelf: ShelfType) => {
    const index = props.shelves.indexOf(_shelf);
    if (index <= 0) return undefined;
    return props.shelves[index - 1];
};
</script>

