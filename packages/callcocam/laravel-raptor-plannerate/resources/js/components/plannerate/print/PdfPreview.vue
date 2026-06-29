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

        // No modo em linha, o PDF é montado capturando cada módulo isolado
        // (`generateSinglePagePdf` faz o tiling), então NÃO é preciso reduzir o
        // zoom para caber: cada módulo é capturado no seu tamanho natural e
        // reposicionado em mm na página. Por isso não há mais ajuste de escala.
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

        // Marca sucesso só quando houve download (pdf.save já disparou o
        // arquivo antes do reload abaixo — nada se perde).
        generatedWithDownload = autoDownload;
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

        await nextTick();
        isExportingRef.value = false;

        // O PDF é montado capturando cada faixa/módulo em sequência com o
        // html2canvas. Esse processo deixa a página num estado levemente
        // diferente a cada execução (scroll/reflow internos da captura), então
        // gerações seguidas SEM recarregar saem inconsistentes (cabeçalho em
        // coluna, módulos faltando/deslocados). Como a página recém-carregada
        // sempre gera o resultado correto, recarregamos após um download
        // bem-sucedido — o arquivo já foi salvo, nada se perde.
        if (generatedWithDownload) {
            window.setTimeout(() => window.location.reload(), 700);
        }
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
            <!--
                Página de visualização. Para o PDF, o gerador captura cada
                faixa isoladamente (cabeçalho, indicador de fluxo, cada módulo
                `data-module-section` e rodapé) e remonta em mm na página A4 —
                evita a captura única e larga que o html2canvas renderiza mal.
            -->
            <div data-pdf-page class="flex flex-1 flex-col bg-white shadow-sm">
                <PdfGondolaHeader
                    data-pdf-header
                    :gondola="gondola as any"
                    :tenant-name="tenantName"
                    :responsavel="responsavel"
                    :flow-label="flowLabel"
                    :sections-count="orderedSections.length"
                />
                <PdfFlowIndicator data-pdf-flow :is-left-to-right="isLeftToRight" />
                <PdfGondolaCanvas
                    :sections="orderedSections"
                    :local-scale="localScale"
                    :alignment="gondola.alignment ?? 'justify'"
                />
                <PdfPageFooter data-pdf-footer :observacoes="observacoes" />
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
