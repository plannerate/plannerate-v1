<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useAbcClassification } from '@/composables/plannerate/analysis/useAbcClassification';
import { usePdfGenerator } from '@/composables/plannerate/export/usePdfGenerator';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { useTargetStockAnalysis } from '@/composables/plannerate/analysis/useTargetStockAnalysis';
import { useT } from '@/composables/useT';
import type {
    AbcAnalysis,
    Gondola,
    Section,
    StockAnalysis,
} from '@/types/planogram';
import PdfFlowIndicator from './partials/PdfFlowIndicator.vue';
import PdfGondolaCanvas from './partials/PdfGondolaCanvas.vue';
import PdfGondolaHeader from './partials/PdfGondolaHeader.vue';
import PdfPageFooter from './partials/PdfPageFooter.vue';
import PdfPreviewToolbar from './partials/PdfPreviewToolbar.vue';
import PdfModulePage from './PdfModulePage.vue';
import PdfModuleSelector from './PdfModuleSelector.vue';

interface GondolaPdf {
    id: string;
    name?: string;
    scale_factor?: number;
    alignment?: Gondola['alignment'];
    location?: string;
    side?: string;
    flow?: string;
    planogram?: {
        id?: string;
        name?: string;
        type?: string;
        start_date?: string;
        description?: string;
        category?: { name?: string } | null;
    } | null;
}

interface Props {
    gondola: GondolaPdf;
    sections: Section[];
    analysis?: {
        abc?: AbcAnalysis;
        stock?: StockAnalysis;
        [key: string]: any;
    };
    responsavel?: string;
}

const props = defineProps<Props>();
const { t } = useT();
const editor = usePlanogramEditor();
const pdfGenerator = usePdfGenerator();
const abcClassification = useAbcClassification();
const targetStockAnalysis = useTargetStockAnalysis();
const page = usePage<{ tenant?: { name?: string } }>();

type LayoutDirection = 'column' | 'row';

const isDownloading = ref(false);
const layoutDirection = ref<LayoutDirection>('row');
const showModuleSelector = ref(false);

const LAYOUT_DIRECTION_STORAGE_KEY = 'plannerate:pdf-preview:layout-direction';
const SCALE_STEP = 0.5;
const SCALE_MIN = 0.5;
const SCALE_MAX = 5;
const PDF_EXPORT_SCALE = 4;
const PDF_EXPORT_QUALITY = 1;

const localScale = ref(props.gondola.scale_factor ?? 1);

const tenantName = computed(() => page.props.tenant?.name ?? '');
const flowLabel = computed(() =>
    props.gondola.flow === 'right_to_left'
        ? t('plannerate.print.preview.right_to_left')
        : t('plannerate.print.preview.left_to_right'),
);
const observacoes = computed(
    () =>
        props.gondola.planogram?.description ||
        t('plannerate.print.preview.default_observations'),
);

function removeSavedLayoutDirection(): void {
    try {
        window.localStorage.removeItem(LAYOUT_DIRECTION_STORAGE_KEY);
    } catch {
        //
    }
}

function loadSavedLayoutDirection(): LayoutDirection | null {
    try {
        const savedLayoutDirection = window.localStorage.getItem(
            LAYOUT_DIRECTION_STORAGE_KEY,
        );

        if (
            savedLayoutDirection === 'row' ||
            savedLayoutDirection === 'column'
        ) {
            return savedLayoutDirection;
        }

        if (savedLayoutDirection !== null) {
            removeSavedLayoutDirection();
        }
    } catch {
        //
    }

    return null;
}

function saveLayoutDirection(value: LayoutDirection): void {
    try {
        window.localStorage.setItem(LAYOUT_DIRECTION_STORAGE_KEY, value);
    } catch {
        removeSavedLayoutDirection();
    }
}

watch(
    () => props.gondola.scale_factor,
    (v) => {
        if (v) {
            localScale.value = v;
        }
    },
);

onMounted(() => {
    const savedLayoutDirection = loadSavedLayoutDirection();

    if (savedLayoutDirection) {
        layoutDirection.value = savedLayoutDirection;
    }
});

watch(layoutDirection, (value) => {
    saveLayoutDirection(value);
});

function increaseScale() {
    localScale.value = Math.min(
        SCALE_MAX,
        Math.round((localScale.value + SCALE_STEP) * 10) / 10,
    );
}

function decreaseScale() {
    localScale.value = Math.max(
        SCALE_MIN,
        Math.round((localScale.value - SCALE_STEP) * 10) / 10,
    );
}

/**
 * Aguarda o próximo frame de pintura após uma alteração de layout,
 * garantindo que scrollWidth/clientWidth reflitam o novo `localScale`.
 */
function nextFrame(): Promise<void> {
    return new Promise((resolve) =>
        window.requestAnimationFrame(() => resolve()),
    );
}

/**
 * Reduz o zoom (`localScale`) do modo em linha até que o conteúdo da gôndola
 * caiba sem barra de rolagem horizontal. Sem isso, a parte que ultrapassa o
 * container (escondida atrás do scroll) é cortada na captura do PDF.
 *
 * Retorna a escala original para ser restaurada após a geração, ou `null`
 * quando nenhum ajuste foi necessário.
 */
async function fitRowScaleForExport(): Promise<number | null> {
    const scroller = document.querySelector<HTMLElement>(
        '[data-pdf-page] [data-pdf-scroll]',
    );

    if (!scroller) {
        return null;
    }

    const originalScale = localScale.value;
    let adjusted = false;
    let guard = 0;

    // Itera porque padding e conteúdo escalam juntos; uma única razão
    // costuma resolver, mas mantemos uma folga e um limite de segurança.
    // Considera overflow horizontal e vertical (o que for mais restritivo),
    // pois ambos seriam cortados na captura do container.
    while (localScale.value > SCALE_MIN && guard < 12) {
        const overflowsX = scroller.scrollWidth > scroller.clientWidth + 1;
        const overflowsY = scroller.scrollHeight > scroller.clientHeight + 1;

        if (!overflowsX && !overflowsY) {
            break;
        }

        const ratioX = overflowsX
            ? scroller.clientWidth / scroller.scrollWidth
            : 1;
        const ratioY = overflowsY
            ? scroller.clientHeight / scroller.scrollHeight
            : 1;
        const ratio = Math.min(ratioX, ratioY);

        const next = Math.max(
            SCALE_MIN,
            Math.floor(localScale.value * ratio * 100) / 100,
        );

        if (next >= localScale.value) {
            break;
        }

        localScale.value = next;
        adjusted = true;
        guard += 1;
        await nextTick();
        await nextFrame();
    }

    return adjusted ? originalScale : null;
}

async function generatePDF(
    autoDownload = false,
    selectedSectionIds?: string[],
) {
    const isExportingRef = autoDownload
        ? isDownloading
        : pdfGenerator.isGenerating;
    const previousAbcVisibility = abcClassification.isVisible.value;
    const previousTargetStockVisibility = targetStockAnalysis.isVisible.value;
    let scaleToRestore: number | null = null;

    try {
        isExportingRef.value = true;
        abcClassification.setVisibility(false);
        targetStockAnalysis.setVisibility(false);
        await nextTick();

        const layoutMode =
            layoutDirection.value === 'row' ? 'single' : 'multiple';

        // No modo em linha, garante que toda a gôndola caiba sem rolagem
        // horizontal antes da captura, evitando corte no PDF.
        if (layoutMode === 'single') {
            scaleToRestore = await fitRowScaleForExport();
        }
        const orientation =
            layoutDirection.value === 'row' ? 'landscape' : 'portrait';
        const filename = `gondola_${props.gondola.name}_${new Date().toISOString().split('T')[0]}.pdf`;

        // Seletor de elementos correto por modo
        const pageSelector =
            layoutMode === 'multiple'
                ? '[data-pdf-module-page]'
                : '[data-module-section]';

        let specificElements: HTMLElement[] | undefined = undefined;

        if (selectedSectionIds && selectedSectionIds.length > 0) {
            const allModules =
                document.querySelectorAll<HTMLElement>(pageSelector);
            specificElements = Array.from(allModules).filter((element) => {
                const sectionId = element.getAttribute('data-section-id');

                return sectionId && selectedSectionIds.includes(sectionId);
            });
        }

        await pdfGenerator.generatePdf(
            {
                mode: layoutMode,
                selector: pageSelector,
                containerSelector:
                    layoutMode === 'single' ? '[data-pdf-page]' : undefined,
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
            specificElements,
        );
    } catch (error) {
        alert(
            t('plannerate.print.preview.error_prefix') +
                (error instanceof Error
                    ? error.message
                    : t('plannerate.header.auto_generate.unknown_error')),
        );
    } finally {
        abcClassification.setVisibility(previousAbcVisibility);
        targetStockAnalysis.setVisibility(previousTargetStockVisibility);

        // Restaura o zoom original alterado para o ajuste de captura.
        if (scaleToRestore !== null) {
            localScale.value = scaleToRestore;
        }

        await nextTick();
        isExportingRef.value = false;
    }
}

function handleDownloadPdf() {
    if (layoutDirection.value === 'row') {
        generatePDF(true);
    } else {
        showModuleSelector.value = true;
    }
}

async function handleGenerateFromSelector(data: {
    sectionIds: string[];
    autoDownload: boolean;
}) {
    showModuleSelector.value = false;
    await generatePDF(data.autoDownload, data.sectionIds);
}

function toggleLayout() {
    layoutDirection.value =
        layoutDirection.value === 'column' ? 'row' : 'column';
}

const flowDirection = computed(
    () => editor.currentGondola.value?.flow || 'left_to_right',
);
const isLeftToRight = computed(() => flowDirection.value === 'left_to_right');
</script>

<template>
    <div
        class="force-light flex min-h-screen flex-col bg-slate-100 text-slate-900 transition-colors"
    >
        <!-- Toolbar fixo -->
        <PdfPreviewToolbar
            :gondola="gondola as any"
            :sections-count="sections.length"
            :flow-label="flowLabel"
            :local-scale="localScale"
            :scale-min="SCALE_MIN"
            :scale-max="SCALE_MAX"
            :layout-direction="layoutDirection"
            :is-generating="pdfGenerator.isGenerating.value"
            :is-downloading="isDownloading"
            :tenant-name="tenantName"
            :responsavel="responsavel"
            :analysis="analysis"
            @increase-scale="increaseScale"
            @decrease-scale="decreaseScale"
            @toggle-layout="toggleLayout"
            @download-pdf="handleDownloadPdf"
        >
            <PdfFlowIndicator
                v-if="layoutDirection === 'row'"
                :is-left-to-right="isLeftToRight"
            />
        </PdfPreviewToolbar>

        <!-- MODO ROW: página de visualização completa -->
        <div
            v-if="layoutDirection === 'row'"
            class="mt-28 flex flex-1 flex-col"
        >
            <!-- Página capturada para PDF single-page -->
            <div data-pdf-page class="flex flex-1 flex-col bg-white shadow-sm">
                <PdfGondolaHeader
                    :gondola="gondola as any"
                    :tenant-name="tenantName"
                    :responsavel="responsavel"
                    :flow-label="flowLabel"
                    :sections-count="sections.length"
                />
                <PdfGondolaCanvas
                    :sections="sections"
                    :local-scale="localScale"
                    :alignment="gondola.alignment ?? 'justify'"
                />
                <PdfPageFooter :observacoes="observacoes" />
            </div>
        </div>

        <!-- MODO COLUMN: uma página por módulo (estilo PdfModulePage) -->
        <div v-else class="mt-14 pt-6 pb-12">
            <div class="mx-auto flex w-full flex-col gap-10 px-4 lg:max-w-7xl">
                <PdfModulePage
                    v-for="(section, index) in sections"
                    :key="section.id"
                    :section="section"
                    :gondola="gondola as any"
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
