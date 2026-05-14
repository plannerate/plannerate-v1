<script setup lang="ts">
import { ArrowUpIcon, CalendarDaysIcon, ClipboardListIcon, ShoppingCartIcon } from 'lucide-vue-next'
import { computed } from 'vue'
import type { Section } from '@/types/planogram'
import Indicador from '../Indicador.vue'
import PdfSection from './partials/PdfSection.vue'

interface GondolaMeta {
    id: string
    name?: string
    location?: string
    side?: string
    flow?: string
    alignment?: string
    planogram?: {
        name?: string
        type?: string
        start_date?: string
        description?: string
        category?: { name?: string } | null
    } | null
}

interface Props {
    section: Section
    gondola: GondolaMeta
    scaleFactor: number
    alignment: string
    index: number
    total: number
    responsavel?: string
    tenantName?: string
}

const props = defineProps<Props>()

const isLeftToRight = computed(() => props.gondola.flow !== 'right_to_left')

const dimensionsLabel = computed(() => {
    const w = props.section.width ?? 0
    const h = props.section.height ?? 0
    return `L: ${w}  A: ${h}`
})

const flowPositions = computed(() =>
    Array.from({ length: props.total }, (_, i) => ({
        position: i + 1,
        isCurrent: i === props.index,
    }))
)

const flowStartLabel = computed(() => isLeftToRight.value ? 'Início do Fluxo' : 'Fim do Fluxo')
const flowEndLabel = computed(() => isLeftToRight.value ? 'Fim do Fluxo' : 'Início do Fluxo')
</script>

<template>
    <div
        :data-pdf-module-page="section.id"
        :data-section-id="section.id"
        class="bg-white border border-slate-200 shadow-lg w-full overflow-hidden"
    >
        <!-- HEADER -->
        <div class="px-6 py-4 border-b border-slate-200">
            <div class="flex items-start justify-between gap-6">
                <!-- Logo + título -->
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 bg-primary rounded-xl flex items-center justify-center flex-shrink-0">
                        <ShoppingCartIcon class="w-7 h-7 text-primary-foreground" />
                    </div>
                    <div>
                        <p v-if="tenantName" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-none mb-0.5">
                            {{ tenantName }}
                        </p>
                        <h1 class="text-xl font-black text-slate-900 uppercase leading-none tracking-wide">PLANOGRAMA</h1>
                        <p class="text-[9px] font-semibold text-primary uppercase tracking-widest mt-1">
                            ONDE QUISER. QUANDO QUISER.
                        </p>
                    </div>
                </div>

                <!-- Campos informativos: data / loja / cód. loja -->
                <div class="grid grid-cols-3 gap-x-4 gap-y-1 border border-slate-200 rounded-lg p-3">
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-1">
                            <CalendarDaysIcon class="w-3 h-3 text-primary flex-shrink-0" />
                            <span class="text-[9px] text-slate-400 uppercase tracking-wider">Data de Publicação</span>
                        </div>
                        <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-300 pb-0.5 min-w-[80px]">
                            {{ gondola.planogram?.start_date || '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[9px] text-slate-400 uppercase tracking-wider">Loja</span>
                        <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-300 pb-0.5 min-w-[80px]">
                            {{ gondola.location || '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[9px] text-slate-400 uppercase tracking-wider">Cód. Loja</span>
                        <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-300 pb-0.5 min-w-[60px]">—</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- INFO BAR -->
        <div class="bg-slate-50 border-b border-slate-200 px-6 py-2">
            <div class="flex items-start gap-0 divide-x divide-slate-200">
                <div class="pr-4 flex flex-col gap-0.5 min-w-[90px]">
                    <span class="text-[9px] text-slate-400 uppercase tracking-wider">Categoria</span>
                    <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-400 pb-0.5">
                        {{ gondola.planogram?.category?.name || '—' }}
                    </span>
                </div>
                <div class="px-4 flex flex-col gap-0.5 min-w-[90px]">
                    <span class="text-[9px] text-slate-400 uppercase tracking-wider">Subcategoria</span>
                    <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-400 pb-0.5">
                        {{ gondola.side || '—' }}
                    </span>
                </div>
                <div class="px-4 flex flex-col gap-0.5 min-w-[90px]">
                    <span class="text-[9px] text-slate-400 uppercase tracking-wider">Tipo de Gôndola</span>
                    <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-400 pb-0.5">
                        {{ gondola.name || '—' }}
                    </span>
                </div>
                <div class="px-4 flex flex-col gap-0.5 min-w-[60px]">
                    <span class="text-[9px] text-slate-400 uppercase tracking-wider">Módulo</span>
                    <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-400 pb-0.5">
                        {{ section.ordering }} / {{ total }}
                    </span>
                </div>
                <div class="px-4 flex flex-col gap-0.5 min-w-[110px]">
                    <span class="text-[9px] text-slate-400 uppercase tracking-wider">Dimensões do Módulo</span>
                    <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-400 pb-0.5">
                        {{ dimensionsLabel }} mm
                    </span>
                </div>
                <div class="pl-4 flex flex-col gap-0.5 min-w-[90px]">
                    <span class="text-[9px] text-slate-400 uppercase tracking-wider">Nível de Execução</span>
                    <span class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-400 pb-0.5">
                        {{ gondola.planogram?.type || '—' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- CONTEÚDO PRINCIPAL -->
        <div class="flex">
            <!-- Esquerda: Vista Frontal + seção -->
            <div class="flex-1 p-4 border-r border-slate-200 min-w-0">
                <!-- Indicador de fluxo -->
                <div class="relative h-8 mb-2">
                    <Indicador :is-left-to-right="isLeftToRight" />
                </div>

                <!-- Badge Vista Frontal -->
                <div class="flex justify-center mb-3">
                    <span class="bg-primary text-primary-foreground text-[10px] font-bold px-5 py-1.5 uppercase tracking-widest">
                        Vista Frontal
                    </span>
                </div>

                <!-- Seção + indicador de altura -->
                <div class="flex items-start gap-4">
                    <!-- Gondola section rendering — layout row evita mt-12 do modo coluna -->
                    <div
                        class="flex-1 flex justify-center overflow-x-auto"
                        :style="{ paddingTop: `${Math.ceil(scaleFactor * 50)}px` }"
                    >
                        <PdfSection
                            :section="section"
                            :scale-factor="scaleFactor"
                            :alignment="alignment"
                            layout-direction="row"
                            :index="0"
                            :extra-height="0"
                        />
                    </div>

                    <!-- Indicador de altura -->
                    <div class="flex flex-col items-center self-stretch py-2 gap-1">
                        <ArrowUpIcon class="w-3 h-3 text-slate-400" />
                        <div class="flex-1 w-px bg-slate-300"></div>
                        <span
                            class="text-[9px] text-slate-500 uppercase tracking-wider whitespace-nowrap"
                            style="writing-mode: vertical-rl; transform: rotate(180deg)"
                        >
                            Altura Total: {{ section.height }}mm
                        </span>
                        <div class="flex-1 w-px bg-slate-300"></div>
                        <ArrowUpIcon class="w-3 h-3 text-slate-400 rotate-180" />
                    </div>
                </div>

                <!-- Identificação do módulo + largura -->
                <div class="mt-4 text-center">
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                        Módulo #{{ section.ordering }} — Frente
                    </p>
                    <div class="flex items-center gap-2 mt-1 justify-center">
                        <div class="h-px flex-1 bg-slate-400 relative">
                            <span class="absolute left-0 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]">←</span>
                        </div>
                        <span class="text-[10px] text-slate-600 whitespace-nowrap px-1">
                            Largura: {{ section.width }}mm
                        </span>
                        <div class="h-px flex-1 bg-slate-400 relative">
                            <span class="absolute right-0 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]">→</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Direita: Posição do Fluxo + Observações -->
            <div class="w-44 p-4 flex flex-col gap-5 flex-shrink-0">
                <!-- Posição do Fluxo -->
                <div>
                    <div class="flex justify-center mb-3">
                        <span class="bg-primary text-primary-foreground text-[9px] font-bold px-3 py-1.5 uppercase tracking-widest">
                            Posição do Fluxo
                        </span>
                    </div>

                    <div class="flex flex-col items-center gap-0.5">
                        <p class="text-[9px] text-slate-500 uppercase tracking-wider mb-1">{{ flowStartLabel }}</p>

                        <template v-for="pos in flowPositions" :key="pos.position">
                            <!-- Seta para cima -->
                            <div class="flex flex-col items-center">
                                <div
                                    class="w-0 h-0 border-x-[5px] border-x-transparent border-b-[7px]"
                                    :class="pos.isCurrent ? 'border-b-primary' : 'border-b-slate-300'"
                                ></div>
                                <div class="w-px h-3" :class="pos.isCurrent ? 'bg-primary' : 'bg-slate-300'"></div>
                            </div>

                            <!-- Caixa de posição -->
                            <div
                                class="w-24 rounded px-2 py-1.5 text-center border-2"
                                :class="pos.isCurrent
                                    ? 'border-primary bg-primary/10'
                                    : 'border-dashed border-slate-300'"
                            >
                                <p
                                    class="text-[10px] font-semibold"
                                    :class="pos.isCurrent ? 'text-primary' : 'text-slate-500'"
                                >
                                    Posição
                                </p>
                                <p
                                    class="text-[9px]"
                                    :class="pos.isCurrent ? 'text-primary font-bold' : 'text-slate-400'"
                                >
                                    {{ pos.position }}
                                </p>
                            </div>
                        </template>

                        <!-- Última seta -->
                        <div class="w-px h-3 bg-slate-300 mt-0.5"></div>
                        <p class="text-[9px] text-slate-500 uppercase tracking-wider mt-1">{{ flowEndLabel }}</p>
                    </div>
                </div>

                <!-- Observações -->
                <div class="flex-1 flex flex-col">
                    <div class="flex justify-center mb-2">
                        <span class="bg-primary text-primary-foreground text-[9px] font-bold px-3 py-1.5 uppercase tracking-widest">
                            Observações
                        </span>
                    </div>
                    <div class="border border-slate-200 rounded p-2 flex-1 min-h-[80px]">
                        <p class="text-[10px] text-slate-500 leading-relaxed">
                            {{ gondola.planogram?.description || '' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- RODAPÉ -->
        <div class="border-t border-slate-200 bg-slate-50 px-6 py-3 flex items-center gap-4">
            <div class="flex-1 border-r border-slate-200 pr-4 flex items-center gap-2">
                <ClipboardListIcon class="w-4 h-4 text-primary flex-shrink-0" />
                <div>
                    <p class="text-[9px] text-slate-400 uppercase tracking-wider">Responsável</p>
                    <p class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-300 pb-0.5 min-w-[100px]">
                        {{ responsavel || '—' }}
                    </p>
                </div>
            </div>
            <div class="flex-1 border-r border-slate-200 px-4">
                <p class="text-[9px] text-slate-400 uppercase tracking-wider">Aprovado Por</p>
                <p class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-300 pb-0.5 min-w-[100px]">—</p>
            </div>
            <div class="flex-1 border-r border-slate-200 px-4">
                <p class="text-[9px] text-slate-400 uppercase tracking-wider">Data de Aprovação</p>
                <p class="text-xs font-medium text-slate-700 border-b border-dashed border-slate-300 pb-0.5">—/—/—</p>
            </div>
            <div class="flex-shrink-0">
                <div class="bg-primary text-primary-foreground px-4 py-2 rounded text-center">
                    <p class="text-[9px] uppercase tracking-wider leading-none mb-0.5">Versão</p>
                    <p class="text-sm font-black leading-none">V1.0</p>
                </div>
            </div>
        </div>
    </div>
</template>
