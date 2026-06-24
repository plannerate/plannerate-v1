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
        :displayNumber="shelfDisplayNumbers.get(shelf.id)"
    />
</template>

<script setup lang="ts">
import { computed, toRef } from 'vue';
import { useArrayNavigation } from '../../../composables/plannerate/shared/useArrayNavigation';
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

/**
 * Mapa `shelfId → número de exibição` ("Prat #N"), calculado UMA vez por seção.
 *
 * Antes cada instância de Shelf recalculava seu próprio número ordenando TODAS
 * as prateleiras da seção (sort O(S) por shelf → O(S²) na seção), e isso
 * reinvalidava a cada mutação de segmento (porque `section.shelves` é
 * reatribuído). Centralizar aqui reduz para um único sort O(S) por mutação.
 *
 * Mantém a fórmula original: maior `shelf_position` (mais ao topo) recebe #1.
 */
const shelfDisplayNumbers = computed(() => {
    const map = new Map<string, number>();

    [...shelvesRef.value]
        .filter((s) => !s.deleted_at)
        .sort((a, b) => (b.shelf_position || 0) - (a.shelf_position || 0))
        .forEach((s, index) => {
            map.set(s.id, index + 1);
        });

    return map;
});
</script>

<style scoped></style>
