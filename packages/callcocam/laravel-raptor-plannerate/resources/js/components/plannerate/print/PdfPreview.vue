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
    /** Modo Execução em Loja: enxuga a toolbar (sem duplicar o cabeçalho). */
    executionMode?: boolean;
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

// Escala inicial do PDF. Mantém fallback 1 (não o 3 do editor): como `scale` é
// um multiplicador uniforme, a PROPORÇÃO é idêntica em qualquer valor — partir
// de um valor maior só aumentaria o overflow e o risco de corte na captura.
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
 * Prepara o modo em linha para a captura do PDF deixando a página e o container
 * de rolagem dimensionarem-se ao CONTEÚDO, em vez de ficarem presos à altura do
 * viewport com rolagem (que o html2canvas recorta — gerando metade vazia ou um
 * "filete" vertical da gôndola larga).
 *
 * Usa apenas sizing intrínseco (`flex: none` + `align-self: flex-start` +
 * `height/overflow` naturais) — NUNCA largura fixa em px, que colapsa o layout.
 * Assim a fila de módulos ocupa a largura real e o html2canvas captura a gôndola
 * inteira; o `generateSinglePagePdf` depois reduz a imagem para caber no A4.
 *
 * Retorna uma função que restaura os estilos originais, ou `null` se os
 * elementos não forem encontrados.
 */
function relaxRowLayoutForCapture(): (() => void) | null {
    const page = document.querySelector<HTMLElement>('[data-pdf-page]');
    const scroller = page?.querySelector<HTMLElement>('[data-pdf-scroll]');

    if (!page || !scroller) {
        return null;
    }

    const previousPage = {
        flex: page.style.flex,
        alignSelf: page.style.alignSelf,
        height: page.style.height,
        maxWidth: page.style.maxWidth,
    };
    const previousScroller = {
        flex: scroller.style.flex,
        overflow: scroller.style.overflow,
        height: scroller.style.height,
    };

    // Scroller: para de esticar (flex-1) e de rolar — encolhe ao conteúdo.
    scroller.style.flex = 'none';
    scroller.style.overflow = 'visible';
    scroller.style.height = 'auto';

    // Página: para de esticar à altura do viewport e encolhe à largura/altura
    // do conteúdo (a fila de módulos define a largura).
    page.style.flex = 'none';
    page.style.alignSelf = 'flex-start';
    page.style.height = 'auto';
    page.style.maxWidth = 'none';

    return () => {
        Object.assign(page.style, previousPage);
        Object.assign(scroller.style, previousScroller);
    };
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
    let restoreRowLayout: (() => void) | null = null;

    try {
        isExportingRef.value = true;
        abcClassification.setVisibility(false);
        targetStockAnalysis.setVisibility(false);
        await nextTick();

        const layoutMode =
            layoutDirection.value === 'row' ? 'single' : 'multiple';

        // No modo em linha, deixa a gôndola dimensionar ao conteúdo (sem rolagem
        // nem altura travada) para a captura não cortar nada.
        if (layoutMode === 'single') {
            restoreRowLayout = relaxRowLayoutForCapture();
            await nextTick();
            await nextFrame();
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
        // Restaura o layout original alterado para a captura.
        if (restoreRowLayout) {
            restoreRowLayout();
        }

        abcClassification.setVisibility(previousAbcVisibility);
        targetStockAnalysis.setVisibility(previousTargetStockVisibility);

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

// Usa a MESMA fonte do flowLabel e do orderedSections (props.gondola.flow).
// Antes lia editor.currentGondola, que na tela de print pode não estar
// populado → caía em 'left_to_right' e o indicador de fluxo não invertia.
const flowDirection = computed(
    () => props.gondola.flow || 'left_to_right',
);
const isLeftToRight = computed(() => flowDirection.value === 'left_to_right');

/**
 * Módulos ordenados na MESMA ordem visual do editor (usePlanogramEditor →
 * sectionsOrdered): ordena por `ordering` e, quando o fluxo é da direita para a
 * esquerda, inverte o array. Sem isso, o print/PDF exibia os módulos em ordem
 * espelhada em relação ao editor.
 */
const orderedSections = computed<Section[]>(() => {
    const flow = props.gondola.flow || 'left_to_right';
    const filtered = props.sections.filter((s) => !s.deleted_at);
    const ordered = [...filtered].sort(
        (a, b) => (a.ordering || 0) - (b.ordering || 0),
    );

    return flow === 'right_to_left' ? ordered.reverse() : ordered;
});
</script>

<template>
    <div
        class="force-light flex min-h-screen flex-col bg-slate-100 text-slate-900 transition-colors"
    >
        <!-- Toolbar fixo -->
        <PdfPreviewToolbar
            :gondola="gondola as any"
            :sections-count="orderedSections.length"
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
            :compact="executionMode"
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
                    :sections-count="orderedSections.length"
                />
                <PdfFlowIndicator :is-left-to-right="isLeftToRight" />
                <PdfGondolaCanvas
                    :sections="orderedSections"
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
                    v-for="(section, index) in orderedSections"
                    :key="section.id"
                    :section="section"
                    :gondola="gondola as any"
                    :scale-factor="localScale"
                    :alignment="gondola.alignment ?? 'justify'"
                    :index="index"
                    :total="orderedSections.length"
                    :responsavel="responsavel"
                    :tenant-name="tenantName"
                />
            </div>
        </div>

        <!-- Modal de seleção de módulos -->
        <PdfModuleSelector
            v-model:open="showModuleSelector"
            :sections="orderedSections"
            @generate="handleGenerateFromSelector"
        />
    </div>
</template>
