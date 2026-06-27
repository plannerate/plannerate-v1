<script setup lang="ts">
import { defineAsyncComponent } from 'vue'
import PdfPreview from '@/components/plannerate/print/PdfPreview.vue'
import type { AbcAnalysis, Gondola, Section, StockAnalysis } from '@/types/planogram'
import type { ExecutionPayload } from '@/components/plannerate/execution/types'

// A camada de execução só é baixada (chunk) para quem tem a responsabilidade.
const ExecutionLayer = defineAsyncComponent(
    () => import('@/components/plannerate/execution/ExecutionLayer.vue'),
)

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
    /** Gate barato: indica se o usuário opera a Execução em Loja desta gôndola. */
    canExecute?: boolean
    /** Payload da execução (carregado sob demanda via Inertia::optional). */
    execution?: ExecutionPayload | null
}

defineProps<Props>()
</script>

<template>
    <PdfPreview :gondola="gondola" :sections="sections" :analysis="analysis" :responsavel="responsavel" :execution-mode="canExecute" />
    <ExecutionLayer v-if="canExecute" :execution="execution ?? null" :sections="sections" />
</template>
