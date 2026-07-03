<template>
    <!-- Painel de Análises do Planograma -->
    <Popover v-model:open="panelOpen">
        <PopoverTrigger as-child>
            <Button variant="outline" size="sm" class="gap-1.5 rounded-lg">
                <Gauge class="size-4 text-amber-500" />
                {{ t('plannerate.dropdown.performance.title') }}
                <ChevronDown class="size-3 text-muted-foreground" />
            </Button>
        </PopoverTrigger>

        <PopoverContent
            align="end"
            class="z-9999 max-h-[80vh] w-96 overflow-y-auto p-0"
            @pointer-down-outside="keepOpenOnInsideInteraction"
            @interact-outside="keepOpenOnInsideInteraction"
            @focus-outside="(event: Event) => event.preventDefault()"
        >
            <div class="space-y-4 p-4">
                <!-- Cabeçalho do painel -->
                <div class="flex items-start gap-2.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <PieChart class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold leading-tight text-foreground">
                            {{ t('plannerate.dropdown.performance.panel_title') }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ t('plannerate.dropdown.performance.panel_subtitle') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :title="t('plannerate.dropdown.performance.open_analyses')"
                        @click="openAnalysesModal"
                    >
                        <SlidersHorizontal class="size-4" />
                    </button>
                </div>

                <!-- Card: Análise de Sortimento (ABC) -->
                <Collapsible v-model:open="sortimentoOpen" class="overflow-hidden rounded-xl border bg-card">
                    <div class="relative flex items-start gap-2.5 p-3">
                        <!-- Barra de destaque quando a análise está visível -->
                        <span
                            v-if="performance.abc.isVisible.value"
                            class="absolute inset-y-2 left-0 w-1 rounded-full bg-green-500"
                        />
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-green-100 text-green-600 dark:bg-green-950/50 dark:text-green-400">
                            <BarChart3 class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium leading-tight text-foreground">
                                {{ t('plannerate.dropdown.performance.assortment_analysis') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t('plannerate.dropdown.performance.assortment_subtitle') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <Switch
                                :model-value="performance.abc.isVisible.value"
                                :disabled="!performance.abc.hasData.value"
                                class="data-[state=checked]:bg-green-500"
                                @update:model-value="performance.abc.setVisibility($event)"
                            />
                            <span class="text-xs text-muted-foreground">{{ t('plannerate.dropdown.performance.show') }}</span>
                            <CollapsibleTrigger class="rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground">
                                <ChevronDown class="size-4 transition-transform" :class="sortimentoOpen ? 'rotate-180' : ''" />
                            </CollapsibleTrigger>
                        </div>
                    </div>

                    <CollapsibleContent>
                        <div class="space-y-3 px-3 pb-3">
                            <Separator />

                            <!-- Filtrar por tag completa (Proteger/Potencializar/Monitorar/Retirar) -->
                            <p class="text-xs font-medium text-foreground">
                                {{ t('plannerate.dropdown.performance.filter_by_tag') }}
                            </p>
                            <div class="space-y-1.5">
                                <button
                                    v-for="role in abcRoles"
                                    :key="role.value"
                                    type="button"
                                    :disabled="!performance.abc.hasData.value"
                                    class="flex w-full items-center gap-2 rounded-lg border px-2.5 py-2 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                    :class="
                                        performance.abc.isRecommendationActive(role.value)
                                            ? role.activeClass
                                            : 'border-transparent bg-muted/40 opacity-60'
                                    "
                                    @click="performance.abc.toggleRecommendationFilter(role.value)"
                                >
                                    <span
                                        class="flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold leading-none"
                                        :class="role.badgeClass"
                                    >
                                        {{ role.letter }}
                                    </span>
                                    <span class="flex-1 text-sm font-medium" :class="performance.abc.isRecommendationActive(role.value) ? role.labelClass : 'text-muted-foreground'">
                                        {{ role.label }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">({{ role.count }})</span>
                                    <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                </button>
                            </div>

                            <!-- Orientação da tag: vertical (rotacionada) ou horizontal -->
                            <p class="text-xs font-medium text-foreground">
                                {{ t('plannerate.dropdown.performance.tag_orientation') }}
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="flex items-center justify-center gap-1.5 rounded-md border px-2 py-1.5 text-xs font-medium transition-colors"
                                    :class="
                                        indicatorOrientation === 'vertical'
                                            ? 'border-green-600 bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-400'
                                            : 'text-foreground hover:bg-accent'
                                    "
                                    @click="setOrientation('vertical')"
                                >
                                    <GalleryVertical class="size-3.5" />
                                    {{ t('plannerate.dropdown.indicators.orientation_vertical') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex items-center justify-center gap-1.5 rounded-md border px-2 py-1.5 text-xs font-medium transition-colors"
                                    :class="
                                        indicatorOrientation === 'horizontal'
                                            ? 'border-green-600 bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-400'
                                            : 'text-foreground hover:bg-accent'
                                    "
                                    @click="setOrientation('horizontal')"
                                >
                                    <GalleryHorizontal class="size-3.5" />
                                    {{ t('plannerate.dropdown.indicators.orientation_horizontal') }}
                                </button>
                            </div>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <!-- Card: Estoque Alvo -->
                <Collapsible v-model:open="stockOpen" class="overflow-hidden rounded-xl border bg-card">
                    <div class="relative flex items-start gap-2.5 p-3">
                        <span
                            v-if="performance.targetStock.isVisible.value"
                            class="absolute inset-y-2 left-0 w-1 rounded-full bg-blue-500"
                        />
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                            <Target class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium leading-tight text-foreground">
                                {{ t('plannerate.dropdown.performance.target_stock') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t('plannerate.dropdown.performance.target_stock_subtitle') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <Switch
                                :model-value="performance.targetStock.isVisible.value"
                                :disabled="!performance.targetStock.hasData.value"
                                class="data-[state=checked]:bg-blue-500"
                                @update:model-value="performance.targetStock.setVisibility($event)"
                            />
                            <span class="text-xs text-muted-foreground">{{ t('plannerate.dropdown.performance.show') }}</span>
                            <CollapsibleTrigger class="rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground">
                                <ChevronDown class="size-4 transition-transform" :class="stockOpen ? 'rotate-180' : ''" />
                            </CollapsibleTrigger>
                        </div>
                    </div>

                    <CollapsibleContent>
                        <div class="px-3 pb-3">
                            <Separator class="mb-3" />
                            <p class="text-xs text-muted-foreground">
                                {{ performance.targetStock.stats.value.total }}
                                {{ t('plannerate.dropdown.performance.analyzed_products') }}
                            </p>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <!-- Card: Análise de Papel -->
                <Collapsible v-model:open="paperOpen" class="overflow-hidden rounded-xl border bg-card">
                    <div class="relative flex items-start gap-2.5 p-3">
                        <span
                            v-if="performance.paper.isVisible.value"
                            class="absolute inset-y-2 left-0 w-1 rounded-full bg-purple-500"
                        />
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400">
                            <PieChart class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium leading-tight text-foreground">
                                {{ t('plannerate.dropdown.performance.paper_analysis') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t('plannerate.dropdown.performance.paper_subtitle') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <Switch
                                :model-value="performance.paper.isVisible.value"
                                :disabled="!performance.paper.hasData.value"
                                class="data-[state=checked]:bg-purple-500"
                                @update:model-value="performance.paper.setVisibility($event)"
                            />
                            <span class="text-xs text-muted-foreground">{{ t('plannerate.dropdown.performance.show') }}</span>
                            <CollapsibleTrigger class="rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground">
                                <ChevronDown class="size-4 transition-transform" :class="paperOpen ? 'rotate-180' : ''" />
                            </CollapsibleTrigger>
                        </div>
                    </div>

                    <CollapsibleContent>
                        <div class="px-3 pb-3">
                            <Separator class="mb-3" />
                            <p class="text-xs text-muted-foreground">
                                {{ performance.paper.stats.value.total }}
                                {{ t('plannerate.dropdown.performance.analyzed_products') }}
                            </p>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <!-- Ações gerais -->
                <div>
                    <p class="mb-1.5 text-xs font-semibold text-muted-foreground">
                        {{ t('plannerate.dropdown.performance.general_actions') }}
                    </p>
                    <div class="grid grid-cols-2 divide-x rounded-xl border bg-card">
                        <button
                            type="button"
                            :disabled="!performance.anyVisible.value"
                            class="flex items-start gap-2 p-3 text-left transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                            @click="performance.hideAllIndicators()"
                        >
                            <EyeOff class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium leading-tight text-foreground">
                                    {{ t('plannerate.dropdown.performance.hide_all') }}
                                </span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ t('plannerate.dropdown.performance.hide_all_subtitle') }}
                                </span>
                            </span>
                        </button>
                        <button
                            type="button"
                            :disabled="!performance.hasAnyData.value"
                            class="flex items-start gap-2 p-3 text-left transition-colors hover:bg-destructive/10 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="performance.clearAllAnalysis(gondola.id)"
                        >
                            <Trash2 class="mt-0.5 size-4 shrink-0 text-destructive" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium leading-tight text-destructive">
                                    {{ t('plannerate.dropdown.performance.clear_all_title') }}
                                </span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ t('plannerate.dropdown.performance.clear_all_subtitle') }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Exportações CSV -->
                <div>
                    <p class="mb-1.5 text-xs font-semibold text-muted-foreground">
                        {{ t('plannerate.dropdown.performance.exports') }}
                    </p>
                    <div class="divide-y rounded-xl border bg-card">
                        <button
                            v-for="report in exportReports"
                            :key="report.key"
                            type="button"
                            :disabled="!report.enabled.value"
                            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                            @click="report.handler()"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-md" :class="report.badgeClass">
                                <FileSpreadsheet class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium leading-tight text-foreground">{{ report.title }}</span>
                                <span class="block text-xs text-muted-foreground">{{ report.subtitle }}</span>
                            </span>
                            <Download class="size-4 shrink-0 text-muted-foreground" />
                        </button>
                    </div>
                </div>

                <!-- Rodapé informativo -->
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Info class="size-3.5 shrink-0" />
                    {{ t('plannerate.dropdown.performance.auto_applied') }}
                </div>
            </div>
        </PopoverContent>
    </Popover>

    <!-- Performance Modal -->
    <Performance v-model:open="showPerformanceModal" :gondola-id="gondola.id" :planogram="planogram" :analysis="props.analysis" />
</template>

<script setup lang="ts">
import {
    BarChart3,
    ChevronDown,
    ChevronRight,
    Download,
    EyeOff,
    FileSpreadsheet,
    GalleryHorizontal,
    GalleryVertical,
    Gauge,
    Info,
    PieChart,
    SlidersHorizontal,
    Target,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import Performance from '@/components/plannerate/header/Performance.vue';
import type { AbcResult } from '@/components/plannerate/analysis/abc/types';
import type { PaperResult } from '@/components/plannerate/analysis/paper/types';
import type { TargetStockResult } from '@/components/plannerate/analysis/target-stock/types';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { useAnalysisExport } from '@/composables/plannerate/analysis/useAnalysisExport';
import { usePerformanceIndicators } from '@/composables/plannerate/analysis/usePerformanceIndicators';
import { indicatorOrientation, type IndicatorOrientation } from '@/composables/plannerate/core/useGondolaState';
import { useT } from '@/composables/useT';
import type { AbcAnalysis, Gondola, StockAnalysis } from '@/types/planogram';

const props = defineProps<{
    gondola: Pick<Gondola, 'id' | 'planogram'>;
    analysis?: {
        abc?: AbcAnalysis;
        stock?: StockAnalysis;
        paper?: { results?: PaperResult[] };
        [key: string]: any;
    };
}>();

const showPerformanceModal = ref(false);
const { t } = useT();
const { exportAbcToCsv, exportStockToCsv, exportPaperToCsv } = useAnalysisExport();

const performance = usePerformanceIndicators();

/** Estado de abertura do painel (Popover) — controlado para poder fechá-lo ao abrir a modal. */
const panelOpen = ref(false);

/** Estados de expansão de cada card de análise (Sortimento aberto por padrão). */
const sortimentoOpen = ref(true);
const stockOpen = ref(false);
const paperOpen = ref(false);

/**
 * Abre a modal de Análises de Performance fechando antes o painel, para os dois
 * "layers" (Popover e Dialog) não conflitarem/sobreporem.
 */
function openAnalysesModal(): void {
    panelOpen.value = false;
    showPerformanceModal.value = true;
}

/**
 * Mantém o painel aberto ao interagir com as opções internas (filtros, switches,
 * ações). Alternar um filtro re-renderiza os selos do planograma e o reka-ui,
 * ao perder/mover o foco, dispararia o fechamento automático — aqui cancelamos
 * o fechamento quando o alvo da interação está DENTRO do painel; cliques de fato
 * fora (ou Esc) continuam fechando normalmente.
 */
function keepOpenOnInsideInteraction(event: Event): void {
    const target = (event as CustomEvent).detail?.originalEvent?.target as HTMLElement | null;
    if (target?.closest('[data-slot="popover-content"]')) {
        event.preventDefault();
    }
}

/**
 * Tags de sortimento disponíveis para o filtro, com contagem por tag, cor da
 * letra (classe ABC) e as classes de destaque quando a tag está ativa.
 *
 * Filtra pela TAG COMPLETA (Proteger/Potencializar/Monitorar/Retirar) e não só
 * pela classe ABC — por isso "Monitorar" e "Retirar" (ambos classe C) aparecem
 * como linhas independentes.
 */
const abcRoles = computed(() => {
    const stats = performance.abc.recommendationStats.value;

    return [
        {
            value: 'proteger' as const,
            letter: 'A',
            label: t('plannerate.editor.abc_badge.recommendation.proteger'),
            count: stats.proteger,
            badgeClass: 'bg-green-500 text-white',
            activeClass: 'border-green-300 bg-green-50 dark:border-green-900 dark:bg-green-950/40',
            labelClass: 'text-green-700 dark:text-green-400',
        },
        {
            value: 'potencializar' as const,
            letter: 'B',
            label: t('plannerate.editor.abc_badge.recommendation.potencializar'),
            count: stats.potencializar,
            badgeClass: 'bg-amber-500 text-white',
            activeClass: 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40',
            labelClass: 'text-amber-700 dark:text-amber-400',
        },
        {
            value: 'monitorar' as const,
            letter: 'C',
            label: t('plannerate.editor.abc_badge.recommendation.monitorar'),
            count: stats.monitorar,
            badgeClass: 'bg-red-500 text-white',
            activeClass: 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
            labelClass: 'text-red-600 dark:text-red-400',
        },
        {
            value: 'retirar' as const,
            letter: 'C',
            label: t('plannerate.editor.abc_badge.recommendation.retirar'),
            count: stats.retirar,
            badgeClass: 'bg-red-700 text-white',
            activeClass: 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
            labelClass: 'text-red-700 dark:text-red-400',
        },
    ];
});

/**
 * Relatórios CSV exportáveis, com o handler de exportação, o estado de
 * habilitação (depende de haver dados) e a cor do ícone por análise.
 */
const exportReports = computed(() => [
    {
        key: 'abc',
        title: t('plannerate.dropdown.performance.export_abc'),
        subtitle: t('plannerate.dropdown.performance.export_abc_subtitle'),
        badgeClass: 'bg-green-100 text-green-600 dark:bg-green-950/50 dark:text-green-400',
        enabled: performance.abc.hasData,
        handler: handleExportAbc,
    },
    {
        key: 'stock',
        title: t('plannerate.dropdown.performance.export_stock'),
        subtitle: t('plannerate.dropdown.performance.export_stock_subtitle'),
        badgeClass: 'bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400',
        enabled: performance.targetStock.hasData,
        handler: handleExportStock,
    },
    {
        key: 'paper',
        title: t('plannerate.dropdown.performance.export_paper'),
        subtitle: t('plannerate.dropdown.performance.export_paper_subtitle'),
        badgeClass: 'bg-purple-100 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400',
        enabled: performance.paper.hasData,
        handler: handleExportPaper,
    },
]);

const getStorageKey = (gondolaId: string) => `plannerate:performance:visibility:${gondolaId}`;

interface PerformanceVisibilityPreferences {
    abcVisible: boolean;
    targetStockVisible: boolean;
    paperVisible: boolean;
}

const saveVisibilityPreferences = (): void => {
    if (typeof window === 'undefined') return;

    const payload: PerformanceVisibilityPreferences = {
        abcVisible: performance.abc.isVisible.value,
        targetStockVisible: performance.targetStock.isVisible.value,
        paperVisible: performance.paper.isVisible.value,
    };

    window.localStorage.setItem(getStorageKey(props.gondola.id), JSON.stringify(payload));
};

const loadVisibilityPreferences = (): void => {
    if (typeof window === 'undefined') return;

    const raw = window.localStorage.getItem(getStorageKey(props.gondola.id));
    if (!raw) return;

    try {
        const parsed = JSON.parse(raw) as Partial<PerformanceVisibilityPreferences>;

        if (typeof parsed.abcVisible === 'boolean') performance.abc.setVisibility(parsed.abcVisible);
        if (typeof parsed.targetStockVisible === 'boolean') performance.targetStock.setVisibility(parsed.targetStockVisible);
        if (typeof parsed.paperVisible === 'boolean') performance.paper.setVisibility(parsed.paperVisible);
    } catch {
        window.localStorage.removeItem(getStorageKey(props.gondola.id));
    }
};

// Mudança de gôndola — limpar análises antigas
watch(
    () => props.gondola.id,
    (newId, oldId) => {
        if (newId !== oldId && oldId !== undefined) {
            performance.abc.clearClassifications();
            performance.targetStock.clearTargetStockData();
            performance.paper.clearPaperRoles();
            loadVisibilityPreferences();
        }
    },
);

// Carregar classificações ABC
watch(
    () => props.analysis?.abc,
    (analysis) => {
        performance.abc.clearClassifications();
        if (analysis?.results?.length) {
            performance.abc.setClassifications(analysis.results);
        }
    },
    { immediate: true },
);

// Carregar dados de Target Stock
watch(
    () => props.analysis?.stock,
    (analysis) => {
        performance.targetStock.clearTargetStockData();
        if (analysis?.results?.length) {
            performance.targetStock.setTargetStockDataBatch(analysis.results as any[]);
        }
    },
    { immediate: true },
);

// Carregar papéis estratégicos (Análise de Papel)
watch(
    () => props.analysis?.paper,
    (analysis) => {
        performance.paper.clearPaperRoles();
        if (analysis?.results?.length) {
            performance.paper.setPaperRoles(
                (analysis.results as PaperResult[])
                    .filter((r) => r.ean && r.role)
                    .map((r) => ({ ean: r.ean, role: r.role })),
            );
        }
    },
    { immediate: true },
);

// Persistir preferências de visibilidade
watch(
    [performance.abc.isVisible, performance.targetStock.isVisible, performance.paper.isVisible],
    () => saveVisibilityPreferences(),
);

onMounted(() => loadVisibilityPreferences());

const planogram = computed(() => {
    const pg = props.gondola.planogram;
    if (pg && 'id' in pg && 'name' in pg) return pg as any;
    return null;
});

/** Define a orientação dos selos de performance (persistida via estado global). */
function setOrientation(orientation: IndicatorOrientation): void {
    indicatorOrientation.value = orientation;
}

function handleExportAbc(): void {
    const results = props.analysis?.abc?.results;
    if (!results?.length) return;
    exportAbcToCsv(results as AbcResult[]);
}

function handleExportStock(): void {
    const results = props.analysis?.stock?.results;
    if (!results?.length) return;
    exportStockToCsv(results as TargetStockResult[]);
}

function handleExportPaper(): void {
    const results = props.analysis?.paper?.results;
    if (!results?.length) return;
    exportPaperToCsv(results as PaperResult[]);
}
</script>
