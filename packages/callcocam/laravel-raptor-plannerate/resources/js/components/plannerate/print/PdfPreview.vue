<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import PdfSection from './partials/PdfSection.vue'
import Indicador from '../v3/Indicador.vue'
import { usePlanogramEditor } from '@/composables/plannerate/v3/usePlanogramEditor'
import { useAbcClassification } from '@/composables/plannerate/v3/useAbcClassification'
import { useTargetStockAnalysis } from '@/composables/plannerate/v3/useTargetStockAnalysis'
import DropdownPerformance from '../v3/DropdownPerformance.vue'
import type { AbcAnalysis, Gondola, Section, StockAnalysis } from '@/types/planogram'
import { usePdfGenerator } from '@/composables/plannerate/usePdfGenerator'
import PdfModuleSelector from './PdfModuleSelector.vue'
import { Columns, Download, Loader2, Minus, Plus, Rows } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'

interface GondolaPdf {
    id: string
    name?: string
    scale_factor?: number
    alignment?: Gondola['alignment']
    location?: string
    side?: string
    flow?: string
    planogram?: { id?: string; name?: string } | null
}

interface Props {
    gondola: GondolaPdf
    sections: Section[]
    analysis?: {
        abc?: AbcAnalysis
        stock?: StockAnalysis
        [key: string]: any
    }
}

const props = defineProps<Props>()
const editor = usePlanogramEditor()
const pdfGenerator = usePdfGenerator()
const abcClassification = useAbcClassification()
const targetStockAnalysis = useTargetStockAnalysis()

const isDownloading = ref(false)
const layoutDirection = ref<'column' | 'row'>('row')
const showModuleSelector = ref(false)

const SCALE_STEP = 0.5
const SCALE_MIN = 0.5
const SCALE_MAX = 5
const PDF_EXPORT_SCALE = 4
const PDF_EXPORT_QUALITY = 1

const localScale = ref(props.gondola.scale_factor ?? 1)
const scaleDisplay = computed(() => `${localScale.value.toFixed(1)}x`)

watch(() => props.gondola.scale_factor, (v) => { if (v) localScale.value = v })

function increaseScale() {
    localScale.value = Math.min(SCALE_MAX, Math.round((localScale.value + SCALE_STEP) * 10) / 10)
}

function decreaseScale() {
    localScale.value = Math.max(SCALE_MIN, Math.round((localScale.value - SCALE_STEP) * 10) / 10)
}

async function generatePDF(autoDownload = false, selectedSectionIds?: string[]) {
    const isExportingRef = autoDownload ? isDownloading : pdfGenerator.isGenerating
    const previousAbcVisibility = abcClassification.isVisible.value
    const previousTargetStockVisibility = targetStockAnalysis.isVisible.value

    try {
        isExportingRef.value = true
        abcClassification.setVisibility(false)
        targetStockAnalysis.setVisibility(false)
        await nextTick()

        const layoutMode = layoutDirection.value === 'row' ? 'single' : 'multiple'
        const orientation = layoutDirection.value === 'row' ? 'landscape' : 'portrait'
        const filename = `gondola_${props.gondola.name}_${new Date().toISOString().split('T')[0]}.pdf`

        // Se foram selecionados módulos específicos, filtra os elementos
        let specificElements: HTMLElement[] | undefined = undefined
        
        if (selectedSectionIds && selectedSectionIds.length > 0) {
            const allModules = document.querySelectorAll<HTMLElement>('[data-module-section]')
            specificElements = Array.from(allModules).filter(element => {
                const sectionId = element.getAttribute('data-section-id')
                return sectionId && selectedSectionIds.includes(sectionId)
            })
        }

        await pdfGenerator.generatePdf(
            {
                mode: layoutMode,
                selector: '[data-module-section]',
            },
            {
                filename,
                orientation,
                format: 'a4',
                marginTop: layoutMode === 'single' ? 10 : 20,
                marginSides: 10,
                marginBottom: 10,
                scale: PDF_EXPORT_SCALE,
                quality: PDF_EXPORT_QUALITY,
            },
            autoDownload,
            specificElements
        )
    } catch (error) {
        alert('Erro ao gerar PDF: ' + (error instanceof Error ? error.message : 'Erro desconhecido'))
    } finally {
        abcClassification.setVisibility(previousAbcVisibility)
        targetStockAnalysis.setVisibility(previousTargetStockVisibility)
        await nextTick()
        isExportingRef.value = false
    }
}

// function handlePreviewPdf() {
//     // Se estiver em modo row (horizontal), gera direto com todos os módulos
//     if (layoutDirection.value === 'row') {
//         generatePDF(false)
//     } else {
//         showModuleSelector.value = true
//     }
// }

function handleDownloadPdf() {
    // Se estiver em modo row (horizontal), gera direto com todos os módulos
    if (layoutDirection.value === 'row') {
        generatePDF(true)
    } else {
        showModuleSelector.value = true
    }
}

async function handleGenerateFromSelector(data: { sectionIds: string[], autoDownload: boolean }) {
    showModuleSelector.value = false
    await generatePDF(data.autoDownload, data.sectionIds)
}

function toggleLayout() {
    layoutDirection.value = layoutDirection.value === 'column' ? 'row' : 'column'
}
// Computed para direção do fluxo
const flowDirection = computed(
    () => editor.currentGondola.value?.flow || 'left_to_right',
);
const isLeftToRight = computed(() => flowDirection.value === 'left_to_right');

const extraHeight = 0; // Sem espaço extra — seção usa altura real igual ao editor
</script>

<template>
    <div class="bg-transparent">
        <!-- Toolbar fixo -->
        <div class="fixed top-0 left-0 right-0 z-[500] bg-white/95 border-b border-slate-200 shadow-sm">
            <div class="max-w-screen-2xl mx-auto flex h-auto min-h-16 items-center justify-between gap-4 px-4 py-2">
                <!-- Info do planograma + gôndola -->
                <div class="flex min-w-0 flex-col gap-0.5">
                    <p v-if="gondola.planogram?.name" class="truncate text-xs font-medium text-primary">
                        {{ gondola.planogram.name }}
                    </p>
                    <h1 class="truncate text-base font-semibold text-slate-800">{{ gondola.name }}</h1>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5">
                        <span class="flex items-center gap-1 text-xs text-slate-500">
                            <span class="text-slate-400">⊞</span>
                            {{ sections.length }} módulo{{ sections.length !== 1 ? 's' : '' }}
                        </span>
                        <span v-if="gondola.location" class="flex items-center gap-1 text-xs text-slate-500">
                            <span class="text-slate-400">📍</span>
                            {{ gondola.location }}
                        </span>
                        <span v-if="gondola.side" class="flex items-center gap-1 text-xs text-slate-500">
                            <span class="text-slate-400">◧</span>
                            {{ gondola.side }}
                        </span>
                        <span class="flex items-center gap-1 text-xs text-slate-500">
                            <span class="text-slate-400">→</span>
                            {{ gondola.flow === 'right_to_left' ? 'Direita → Esquerda' : 'Esquerda → Direita' }}
                        </span>
                        <span class="flex items-center gap-1 text-xs text-slate-500">
                            <span class="text-slate-400">⊡</span>
                            Scale {{ gondola.scale_factor ?? 1 }}×
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Controle de zoom -->
                    <div class="flex items-center gap-1 rounded-md border bg-background p-1">
                        <Button variant="ghost" size="icon" class="size-7" :disabled="localScale <= SCALE_MIN" @click="decreaseScale">
                            <Minus class="size-3.5" />
                        </Button>
                        <Input :model-value="scaleDisplay" class="h-7 w-14 text-center text-xs" readonly />
                        <Button variant="ghost" size="icon" class="size-7" :disabled="localScale >= SCALE_MAX" @click="increaseScale">
                            <Plus class="size-3.5" />
                        </Button>
                    </div>

                    <DropdownPerformance :gondola="(gondola as any)" :analysis="analysis" />
                    <Button @click="toggleLayout" variant="outline" size="sm"
                        :title="layoutDirection === 'column' ? 'Mudar para linha' : 'Mudar para coluna'">
                        <Rows v-if="layoutDirection === 'column'" class="mr-2 h-4 w-4" />
                        <Columns v-else class="mr-2 h-4 w-4" />
                        {{ layoutDirection === 'column' ? 'Em Linha' : 'Em Coluna' }}
                    </Button> 

                    <Button @click="handleDownloadPdf" :disabled="pdfGenerator.isGenerating.value || isDownloading" size="sm">
                        <Loader2 v-if="isDownloading" class="mr-2 h-4 w-4 animate-spin" />
                        <Download v-else class="mr-2 h-4 w-4" />
                        {{ isDownloading ? 'Baixando...' : 'Baixar PDF' }}
                    </Button>
                </div>
            </div>
        </div>
        <div class="mt-20 relative">
            <!-- Indicador de Direção da Gôndola - Discreto -->
            <Indicador :isLeftToRight="isLeftToRight" />
        </div>
        <!-- Conteúdo dos módulos -->
        <div class="pt-24 pb-12 w-full h-full"
            :class="layoutDirection === 'row' ? 'overflow-x-auto' : 'overflow-visible'">
            <div class="flex h-full"
                :class="[
                    layoutDirection === 'column' ? 'flex-col items-center gap-24 w-full' : 'flex-row items-start gap-0 px-6 w-max'
                ]">
                <PdfSection v-for="(section, index) in sections" :key="section.id" :section="section"
                    :scale-factor="localScale" :alignment="gondola.alignment ?? 'justify'" :index="index"
                    :layout-direction="layoutDirection" :extra-height="extraHeight" :data-section-id="section.id" />
            </div>
        </div>

        <!-- Modal de seleção de módulos -->
        <PdfModuleSelector
            v-model:open="showModuleSelector"
            :sections="sections"
            @generate="handleGenerateFromSelector"
        />
    </div>
</template>
