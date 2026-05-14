<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    ClipboardListIcon,
    Columns,
    Download,
    FileTextIcon,
    Loader2,
    Minus,
    PackageIcon,
    Plus,
    Rows,
    ShoppingCartIcon,
    StoreIcon,
    UserIcon,
} from 'lucide-vue-next'
import { computed, nextTick, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useAbcClassification } from '@/composables/plannerate/useAbcClassification'
import { usePdfGenerator } from '@/composables/plannerate/usePdfGenerator'
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor'
import { useTargetStockAnalysis } from '@/composables/plannerate/useTargetStockAnalysis'
import { useT } from '@/composables/useT'
import type { AbcAnalysis, Gondola, Section, StockAnalysis } from '@/types/planogram'
import DropdownPerformance from '../DropdownPerformance.vue'
import Indicador from '../Indicador.vue'
import PdfModulePage from './PdfModulePage.vue'
import PdfModuleSelector from './PdfModuleSelector.vue'
import PdfSection from './partials/PdfSection.vue'

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
const scaleDisplay = computed(() => `${localScale.value.toFixed(1)}x`)

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
    <div class="min-h-screen bg-slate-100 dark:bg-[#010912] text-slate-900 dark:text-slate-100 transition-colors flex flex-col">
        <!-- Toolbar fixo -->
        <div
            class="fixed top-0 left-0 right-0 z-[500] border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/95"
        >
            <div
                class="max-w-screen-2xl mx-auto flex h-auto min-h-16 items-center justify-between gap-4 px-4 py-2"
            >
                <!-- Info do planograma + gôndola -->
                <div class="flex min-w-0 flex-col gap-0.5">
                    <p v-if="gondola.planogram?.name" class="truncate text-xs font-medium text-primary">
                        {{ gondola.planogram.name }}
                    </p>
                    <h1 class="truncate text-base font-semibold text-slate-800 dark:text-slate-100">
                        {{ gondola.name }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5">
                        <span class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-300">
                            <span class="text-slate-400 dark:text-slate-500">⊞</span>
                            {{ sections.length }}
                            {{ t('plannerate.print.module_selector.module') }}{{ sections.length !== 1 ? 's' : '' }}
                        </span>
                        <span
                            v-if="gondola.location"
                            class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-300"
                        >
                            <span class="text-slate-400 dark:text-slate-500">📍</span>
                            {{ gondola.location }}
                        </span>
                        <span
                            v-if="gondola.side"
                            class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-300"
                        >
                            <span class="text-slate-400 dark:text-slate-500">◧</span>
                            {{ gondola.side }}
                        </span>
                        <span class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-300">
                            <span class="text-slate-400 dark:text-slate-500">→</span>
                            {{ flowLabel }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Controle de zoom -->
                    <div
                        class="flex items-center gap-1 rounded-md border border-slate-200 bg-background p-1 dark:border-slate-700"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :disabled="localScale <= SCALE_MIN"
                            @click="decreaseScale"
                        >
                            <Minus class="size-3.5" />
                        </Button>
                        <Input :model-value="scaleDisplay" class="h-7 w-14 text-center text-xs" readonly />
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :disabled="localScale >= SCALE_MAX"
                            @click="increaseScale"
                        >
                            <Plus class="size-3.5" />
                        </Button>
                    </div>

                    <DropdownPerformance :gondola="(gondola as any)" :analysis="analysis" />

                    <Button
                        variant="outline"
                        size="sm"
                        :title="
                            layoutDirection === 'column'
                                ? t('plannerate.print.preview.switch_to_row')
                                : t('plannerate.print.preview.switch_to_column')
                        "
                        @click="toggleLayout"
                    >
                        <Rows v-if="layoutDirection === 'column'" class="mr-2 h-4 w-4" />
                        <Columns v-else class="mr-2 h-4 w-4" />
                        {{
                            layoutDirection === 'column'
                                ? t('plannerate.print.preview.in_row')
                                : t('plannerate.print.preview.in_column')
                        }}
                    </Button>

                    <Button
                        size="sm"
                        :disabled="pdfGenerator.isGenerating.value || isDownloading"
                        @click="handleDownloadPdf"
                    >
                        <Loader2 v-if="isDownloading" class="mr-2 h-4 w-4 animate-spin" />
                        <Download v-else class="mr-2 h-4 w-4" />
                        {{
                            isDownloading
                                ? t('plannerate.print.preview.downloading')
                                : t('plannerate.print.preview.download_pdf')
                        }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Indicador de direção — apenas no modo row -->
        <div v-if="layoutDirection === 'row'" class="mt-16 h-10 relative z-10">
            <Indicador :isLeftToRight="isLeftToRight" />
        </div>
        <!-- Spacer no modo column para compensar a toolbar fixa -->
        <div v-else class="mt-16"></div>

        <!-- MODO ROW: página de visualização completa -->
        <div v-if="layoutDirection === 'row'" class="flex-1 flex flex-col">
            <!-- Página capturada para PDF single-page -->
            <div data-pdf-page class="bg-white dark:bg-slate-900 flex-1 flex flex-col shadow-sm">

                <!-- HEADER da página -->
                <div class="px-6 py-5 border-b-2 border-primary">
                    <div class="flex flex-wrap items-start justify-between gap-6">
                        <!-- Logo + empresa + título -->
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-primary rounded-xl flex items-center justify-center shrink-0">
                                <ShoppingCartIcon class="w-8 h-8 text-primary-foreground" />
                            </div>
                            <div>
                                <p v-if="tenantName" class="text-sm font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest leading-none mb-1">
                                    {{ tenantName }}
                                </p>
                                <div class="flex items-center gap-2">
                                    <div class="w-1 h-8 bg-primary rounded-full"></div>
                                    <h2 class="text-2xl font-black text-slate-900 dark:text-slate-100 uppercase tracking-wide leading-none">
                                        Planograma de Exposição
                                    </h2>
                                </div>
                            </div>
                        </div>

                        <!-- Grid de metadados -->
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid grid-cols-2 gap-x-8 gap-y-3">
                            <!-- Loja -->
                            <div class="flex items-center gap-2 text-sm">
                                <StoreIcon class="w-4 h-4 text-primary shrink-0" />
                                <span class="text-slate-500 dark:text-slate-400">Loja:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ gondola.location || '—' }}</span>
                            </div>
                            <!-- Data de publicação -->
                            <div class="flex items-center gap-2 text-sm">
                                <CalendarDaysIcon class="w-4 h-4 text-primary shrink-0" />
                                <span class="text-slate-500 dark:text-slate-400">Data de publicação:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ gondola.planogram?.start_date || '—' }}</span>
                            </div>
                            <!-- Setor -->
                            <div class="flex items-center gap-2 text-sm">
                                <FileTextIcon class="w-4 h-4 text-primary shrink-0" />
                                <span class="text-slate-500 dark:text-slate-400">Setor:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ gondola.side || '—' }}</span>
                            </div>
                            <!-- Versão -->
                            <div class="flex items-center gap-2 text-sm">
                                <FileTextIcon class="w-4 h-4 text-primary shrink-0" />
                                <span class="text-slate-500 dark:text-slate-400">Versão:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">V1.0</span>
                            </div>
                            <!-- Categoria -->
                            <div class="flex items-center gap-2 text-sm">
                                <PackageIcon class="w-4 h-4 text-primary shrink-0" />
                                <span class="text-slate-500 dark:text-slate-400">Categoria:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ gondola.planogram?.category?.name || '—' }}</span>
                            </div>
                            <!-- Responsável -->
                            <div class="flex items-center gap-2 text-sm">
                                <UserIcon class="w-4 h-4 text-primary shrink-0" />
                                <span class="text-slate-500 dark:text-slate-400">Responsável:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ responsavel || '—' }}</span>
                            </div>
                            <!-- Posição do fluxo -->
                            <div class="flex items-center gap-2 text-sm col-span-2">
                                <ArrowRightIcon class="w-4 h-4 text-primary shrink-0" />
                                <span class="text-slate-500 dark:text-slate-400">Posição do fluxo:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ flowLabel }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ÁREA DAS SEÇÕES (gondola) -->
                <div class="flex-1 px-6 pb-6 bg-slate-50 dark:bg-slate-800/50 overflow-x-auto">
                    <div
                        class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 px-6 pb-6 w-max min-w-full"
                        :style="{ paddingTop: `${Math.ceil(localScale * 50)}px` }"
                    >
                        <div class="flex flex-row items-end gap-0 w-max">
                            <PdfSection
                                v-for="(section, index) in sections"
                                :key="section.id"
                                :section="section"
                                :scale-factor="localScale"
                                :alignment="gondola.alignment ?? 'justify'"
                                :index="index"
                                layout-direction="row"
                                :extra-height="0"
                                :data-section-id="section.id"
                            />
                        </div>
                    </div>
                </div>

                <!-- RODAPÉ: observações -->
                <div class="px-6 py-4 flex items-start gap-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center shrink-0">
                        <ClipboardListIcon class="w-5 h-5 text-primary-foreground" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Observações:</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ observacoes }}</p>
                    </div>
                </div>

                <!-- Barra inferior decorativa -->
                <div class="h-10 bg-slate-900 dark:bg-slate-950 relative overflow-hidden">
                    <div class="absolute bottom-0 right-0 w-20 h-20 bg-primary rounded-tl-full"></div>
                </div>
            </div>
        </div>

        <!-- MODO COLUMN: uma página por módulo (estilo PdfModulePage) -->
        <div v-else class="pt-6 pb-12">
            <div class="flex flex-col gap-10 w-full">
                <PdfModulePage
                    v-for="(section, index) in sections"
                    :key="section.id"
                    :section="section"
                    :gondola="(gondola as any)"
                    :scale-factor="localScale"
                    :alignment="gondola.alignment ?? 'justify'"
                    :index="index"
                    :total="sections.length"
                    :responsavel="responsavel"
                    :tenant-name="tenantName"
                />
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
