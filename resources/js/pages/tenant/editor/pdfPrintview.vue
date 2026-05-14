<script setup lang="ts">
import PdfPreview from '@/components/plannerate/print/PdfPreview.vue'
import type { AbcAnalysis, Gondola, Section, StockAnalysis } from '@/types/planogram'

interface Props {
    gondola: Pick<Gondola, 'id' | 'name' | 'scale_factor' | 'alignment'> & {
        location?: string
        side?: string
        flow?: string
        planogram?: {
            id?: string
            name?: string
            type?: string
            start_date?: string
            description?: string
            category?: { name?: string } | null
        } | null
    }
    sections: Section[]
    analysis?: {
        abc?: AbcAnalysis
        stock?: StockAnalysis
        [key: string]: any
    }
    responsavel?: string
}

defineProps<Props>()
</script>

<template>
    <PdfPreview :gondola="gondola" :sections="sections" :analysis="analysis" :responsavel="responsavel" />
</template>
