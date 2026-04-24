<script setup lang="ts">
import PdfPreview from '@/components/plannerate/print/PdfPreview.vue'
import type { AbcAnalysis, Gondola, Section, StockAnalysis } from '@/types/planogram'

interface Props {
    gondola: Pick<Gondola, 'id' | 'name' | 'scale_factor' | 'alignment'>
    sections: Section[]
    analysis?: {
        abc?: AbcAnalysis
        stock?: StockAnalysis
        [key: string]: any
    }
}

defineProps<Props>()
</script>

<template>
    <PdfPreview :gondola="gondola" :sections="sections" :analysis="analysis" />
</template>