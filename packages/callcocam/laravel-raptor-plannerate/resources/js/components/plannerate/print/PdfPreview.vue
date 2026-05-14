<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import { useAbcClassification } from '@/composables/plannerate/useAbcClassification'
import { usePdfGenerator } from '@/composables/plannerate/usePdfGenerator'
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor'
import { useTargetStockAnalysis } from '@/composables/plannerate/useTargetStockAnalysis'
import { useT } from '@/composables/useT'
import type { AbcAnalysis, Gondola, Section, StockAnalysis } from '@/types/planogram'
import Indicador from '../Indicador.vue'
import PdfModulePage from './PdfModulePage.vue'
import PdfModuleSelector from './PdfModuleSelector.vue'
import PdfGondolaCanvas from './partials/PdfGondolaCanvas.vue'
import PdfPageFooter from './partials/PdfPageFooter.vue'
import PdfPageHeader from './partials/PdfPageHeader.vue'
import PdfPreviewToolbar from './partials/PdfPreviewToolbar.vue'

interface GondolaPdf {
    id: string
    name?: string
    scale_factor?: number
    alignment?: Gondola['alignment']
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

interface Props {
    gondola: GondolaPdf
    sections: Section[]
    analysis?: {
        abc?: AbcAnalysis
        stock?: StockAnalysis
        [key: string]: any
    }
    responsavel?: string
}

const props = defineProps<Props>()
const { t } = useT()
const editor = usePlanogramEditor()
const pdfGenerator = usePdfGenerator()
const abcClassification = useAbcClassification()
const targetStockAnalysis = useTargetStockAnalysis()
const page = usePage<{ tenant?: { name?: string } }>()

const isDownloading = ref(false)
const layoutDirection = ref<'column' | 'row'>('row')
const showModuleSelector = ref(false)

const SCALE_STEP = 0.5
const SCALE_MIN = 0.5
const SCALE_MAX = 5
const PDF_EXPORT_SCALE = 4
const PDF_EXPORT_QUALITY = 1

const localScale = ref(props.gondola.scale_factor ?? 1)

const tenantName = computed(() => page.props.tenant?.name ?? '')
const flowLabel = computed(() =>
    props.gondola.flow === 'right_to_left'
        ? t('plannerate.print.preview.right_to_left')
        : t('plannerate.print.preview.left_to_right')
)
const observacoes = computed(
    () => props.gondola.planogram?.description || 'Documento para consulta e execução em loja.'
)

watch(
    () => props.gondola.scale_factor,
    (v) => {
        if (v) {
            localScale.value = v
        }
    }
)

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

        // Seletor de elementos correto por modo
        const pageSelector = layoutMode === 'multiple' ? '[data-pdf-module-page]' : '[data-module-section]'

        let specificElements: HTMLElement[] | undefined = undefined

        if (selectedSectionIds && selectedSectionIds.length > 0) {
            const allModules = document.querySelectorAll<HTMLElement>(pageSelector)
            specificElements = Array.from(allModules).filter((element) => {
                const sectionId = element.getAttribute('data-section-id')
                return sectionId && selectedSectionIds.includes(sectionId)
            })
        }

        await pdfGenerator.generatePdf(
            {
                mode: layoutMode,
                selector: pageSelector,
                containerSelector: layoutMode === 'single' ? '[data-pdf-page]' : undefined,
            },
            {
                filename,
                orientation,
                format: 'a4',
                marginTop: layoutMode === 'single' ? 5 : 10,
                marginSides: 5,
                marginBottom: 5,
                scale: PDF_EXPORT_SCALE,
                quality: PDF_EXPORT_QUALITY,
            },
            autoDownload,
            specificElements
        )
    } catch (error) {
        alert(
            t('plannerate.print.preview.error_prefix') +
            (error instanceof Error ? error.message : t('plannerate.header.auto_generate.unknown_error'))
        )
    } finally {
        abcClassification.setVisibility(previousAbcVisibility)
        targetStockAnalysis.setVisibility(previousTargetStockVisibility)
        await nextTick()
        isExportingRef.value = false
    }
}

function handleDownloadPdf() {
    if (layoutDirection.value === 'row') {
        generatePDF(true)
    } else {
        showModuleSelector.value = true
    }
}

async function handleGenerateFromSelector(data: { sectionIds: string[]; autoDownload: boolean }) {
    showModuleSelector.value = false
    await generatePDF(data.autoDownload, data.sectionIds)
}

function toggleLayout() {
    layoutDirection.value = layoutDirection.value === 'column' ? 'row' : 'column'
}

const flowDirection = computed(() => editor.currentGondola.value?.flow || 'left_to_right')
const isLeftToRight = computed(() => flowDirection.value === 'left_to_right')
</script>

<template>
    <div
        class="min-h-screen bg-slate-100 dark:bg-[#010912] text-slate-900 dark:text-slate-100 transition-colors flex flex-col">
        <!-- Toolbar fixo -->
        <PdfPreviewToolbar :gondola="(gondola as any)" :sections-count="sections.length" :flow-label="flowLabel"
            :local-scale="localScale" :scale-min="SCALE_MIN" :scale-max="SCALE_MAX" :layout-direction="layoutDirection"
            :is-generating="pdfGenerator.isGenerating.value" :is-downloading="isDownloading" :analysis="analysis"
            @increase-scale="increaseScale" @decrease-scale="decreaseScale" @toggle-layout="toggleLayout"
            @download-pdf="handleDownloadPdf" />


        <!-- MODO ROW: página de visualização completa -->
        <div v-if="layoutDirection === 'row'" class="flex-1 flex flex-col relative mt-16 ">

            <!-- Página capturada para PDF single-page -->
            <div data-pdf-page class="bg-white dark:bg-slate-900 flex-1 flex flex-col shadow-sm">
                <PdfPageHeader :gondola="(gondola as any)" :tenant-name="tenantName" :responsavel="responsavel"
                    :flow-label="flowLabel" />
                <!-- Indicador de direção — apenas no modo row -->
                <div class="h-10 relative z-10">
                    <Indicador :isLeftToRight="isLeftToRight" />
                </div>
                <PdfGondolaCanvas :sections="sections" :local-scale="localScale"
                    :alignment="gondola.alignment ?? 'justify'" />
                <PdfPageFooter :observacoes="observacoes" />
            </div>
        </div>

        <!-- MODO COLUMN: uma página por módulo (estilo PdfModulePage) -->
        <div v-else class="pt-6 pb-12  mt-14">
            <div class="flex flex-col gap-10 w-full lg:max-w-7xl mx-auto px-4">
                <PdfModulePage v-for="(section, index) in sections" :key="section.id" :section="section"
                    :gondola="(gondola as any)" :scale-factor="localScale" :alignment="gondola.alignment ?? 'justify'"
                    :index="index" :total="sections.length" :responsavel="responsavel" :tenant-name="tenantName" />
            </div>
        </div>

        <!-- Modal de seleção de módulos -->
        <PdfModuleSelector v-model:open="showModuleSelector" :sections="sections"
            @generate="handleGenerateFromSelector" />
    </div>
</template>
