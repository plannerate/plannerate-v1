<script setup lang="ts">
import { computed } from 'vue'
import PdfCremalheira from './PdfCremalheira.vue'
import PdfShelf from './PdfShelf.vue'
import { useArrayNavigation } from '@/composables/plannerate/useArrayNavigation'
import type { Section } from '@/types/planogram'

interface Props {
    index?: number
    layoutDirection?: 'column' | 'row'
    section: Section
    scaleFactor: number
    alignment: string
    extraHeight?: number
}

const props = defineProps<Props>()

const cremalheiraWidth = computed(() => (props.section.cremalheira_width  ?? 0) * props.scaleFactor)
const sectionWidth = computed(() => props.section.width * props.scaleFactor)
const sectionHeight = computed(() => (props.section.height + (props.extraHeight ?? 0)) * props.scaleFactor)

// Em modo row, apenas o primeiro módulo tem cremalheira esquerda (visual only)
const showLeftCremalheira = computed(() => {
    if (props.layoutDirection === 'column') {
        return true
    } else {
        return (props.index ?? 0) === 0
    }
})

// Sempre 2 cremalheiras como no editor
const totalWidth = computed(() => sectionWidth.value + cremalheiraWidth.value * 2)

// Prateleiras filtradas e ordenadas (igual ao editor)
const sortedShelves = computed(() =>
    (props.section.shelves || [])
        .filter((s: any) => !s.deleted_at)
        .sort((a: any, b: any) => (a.shelf_position || 0) - (b.shelf_position || 0))
)

// Helper para navegação nas shelves (usa array ordenado para cálculo correto de areaStartCm)
const { getPrevious: previousShelf } = useArrayNavigation(sortedShelves)
</script>

<template>
    <div :data-module-section="section.id" :data-section-id="section.id" :data-module-order="section.ordering" 
        class="relative bg-white"
        :class="props.layoutDirection === 'row' ? 'mt-0' : 'mt-12'"
        :style="{
            width: `${totalWidth}px`,
            height: `${sectionHeight}px`,
            marginLeft: layoutDirection === 'row' && (index ?? 0) > 0 ? `-${cremalheiraWidth}px` : '0',
        }">
        <!-- Cremalheira Esquerda (apenas no primeiro módulo em modo row, ou sempre em column) -->
        <PdfCremalheira
            :width="cremalheiraWidth"
            side="left"
            :section="section"
            :scale="scaleFactor"
            :extra-height="extraHeight"
            v-if="showLeftCremalheira"
        />

        <!-- Prateleiras -->
        <PdfShelf
            v-for="shelf in sortedShelves"
            :key="shelf.id"
            :shelf="shelf"
            :section="section"
            :section-width="sectionWidth"
            :scale-factor="scaleFactor"
            :cremalheira-width="cremalheiraWidth"
            :alignment="alignment"
            :extra-height="extraHeight"
            :previous-shelf="previousShelf(shelf)"
        />

        <!-- Cremalheira Direita (sempre visível) -->
        <PdfCremalheira 
            :width="cremalheiraWidth" 
            side="right"
            :section="section"
            :scale="scaleFactor"
            :extra-height="extraHeight"
        />

        <!-- Label do módulo -->
        <div class="absolute bottom-0 left-0 flex w-full items-center justify-center">
            <div class="text-xs text-slate-500">Módulo #{{ section.ordering }}</div>
        </div>
    </div>
</template>
