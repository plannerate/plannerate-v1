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
        :nextShelf="nextShelf(shelf)"
        :isLast="isLast(shelf)"
        :firstShelf="firstShelf()"
        :lastShelf="lastShelf()"
    />
</template>

<script setup lang="ts">
import { toRef } from 'vue';
import { useArrayNavigation } from '../../../composables/plannerate/useArrayNavigation';
import type { Section, Shelf as ShelfType } from '../../../types/planogram';
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

// Torna shelves reativo para que useArrayNavigation recalcule quando mudar
const shelvesRef = toRef(props, 'shelves');

const {
    getPrevious: previousShelf,
    getNext: nextShelf,
    getFirst: firstShelf,
    getLast: lastShelf,
    isLast,
} = useArrayNavigation(shelvesRef);
</script>

<style scoped></style>
