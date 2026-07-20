<script setup lang="ts">
import { AlertTriangle, ChevronDown, ChevronUp, Download, Maximize2, Search } from 'lucide-vue-next';
import { computed, ref, useSlots, watch } from 'vue';
import BcgScatterChart from '@/components/plannerate/analysis/bcg/BcgScatterChart.vue';
import BcgSelectionPanel from '@/components/plannerate/analysis/bcg/BcgSelectionPanel.vue';
import { useBcgLabels } from '@/components/plannerate/analysis/bcg/labels';
import type { BcgQuadrant, BcgResult } from '@/components/plannerate/analysis/bcg/types';
import TableHeadAnalysis from '@/components/plannerate/analysis/TableHeadAnalysis.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAnalysisExport } from '@/composables/plannerate/analysis/useAnalysisExport';
import { useT } from '@/composables/useT';

interface Props {
    results: BcgResult[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    results: () => [],
    loading: false,
});

const slots = useSlots();
const hasTopSlot = computed(() => Boolean(slots.top));
const { t } = useT();
const { exportBcgToCsv } = useAnalysisExport();
const {
    axisLabel,
    quadrantLabel,
    quadrantDescription,
    quadrantActions,
    actionsTitle,
    quadrantIcon,
    quadrantBadgeClass,
    rowClass,
    spaceActionLabel,
    spaceActionIcon,
    spaceActionClass,
} = useBcgLabels();

const QUADRANTS: BcgQuadrant[] = ['alto_alto', 'forte_x', 'forte_y', 'baixo_baixo'];

/** A matriz é o rosto da análise: abre visível, e a tabela abaixo é a "table view" dela. */
const showChart = ref(true);

/** Matriz em tela cheia — no painel ela divide espaço com a tabela e fica apertada. */
const showExpanded = ref(false);

const searchQuery = ref('');
const filterByQuadrant = ref<'all' | BcgQuadrant>('all');
const sortConfig = ref<{ key: keyof BcgResult; direction: 'asc' | 'desc' }>({
    key: 'x_value',
    direction: 'desc',
});
const selectedProductId = ref<string | null>(null);

let isSorting = false;

/**
 * Eixos usados no cálculo. Vêm do resultado (e não dos parâmetros do formulário)
 * porque a tabela precisa rotular as colunas conforme o que foi REALMENTE calculado —
 * não conforme o que está selecionado no modal agora.
 */
const xAxis = computed(() => props.results[0]?.x_axis ?? 'quantidade');
const yAxis = computed(() => props.results[0]?.y_axis ?? 'margem');

/**
 * Resultado agregado (exibido por categoria/nível): não há EAN por linha, então a
 * coluna EAN é escondida e a coluna Produto assume a primeira posição fixa.
 */
const isAggregated = computed(() => {
    const displayBy = props.results[0]?.display_by;

    return Boolean(displayBy && displayBy !== 'produto');
});

const filteredResults = computed(() => {
    let filtered = [...props.results];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(
            (item) =>
                item.product_name?.toLowerCase().includes(query) ||
                item.ean?.toLowerCase().includes(query) ||
                item.category_name?.toLowerCase().includes(query),
        );
    }

    if (filterByQuadrant.value !== 'all') {
        filtered = filtered.filter((item) => item.quadrant === filterByQuadrant.value);
    }

    filtered.sort((a, b) => {
        const aVal = a[sortConfig.value.key];
        const bVal = b[sortConfig.value.key];

        if (typeof aVal === 'number' && typeof bVal === 'number') {
            return sortConfig.value.direction === 'asc' ? aVal - bVal : bVal - aVal;
        }

        if (typeof aVal === 'string' && typeof bVal === 'string') {
            return sortConfig.value.direction === 'asc'
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        }

        return 0;
    });

    return filtered;
});

const quadrantStats = computed(() => ({
    total: props.results.length,
    alto_alto: props.results.filter((r) => r.quadrant === 'alto_alto').length,
    forte_x: props.results.filter((r) => r.quadrant === 'forte_x').length,
    forte_y: props.results.filter((r) => r.quadrant === 'forte_y').length,
    baixo_baixo: props.results.filter((r) => r.quadrant === 'baixo_baixo').length,
}));

/** Produtos cujo espaço na gôndola está desalinhado do valor entregue — o que vira ação. */
const misallocatedCount = computed(
    () => props.results.filter((r) => r.acao_espaco === 'aumentar' || r.acao_espaco === 'reduzir').length,
);

const selectedResult = computed<BcgResult | null>(() => {
    if (!selectedProductId.value) {
        return filteredResults.value[0] ?? null;
    }

    return (
        filteredResults.value.find((item) => item.product_id === selectedProductId.value) ??
        filteredResults.value[0] ??
        null
    );
});

watch(
    filteredResults,
    (items) => {
        if (!items.length) {
            selectedProductId.value = null;

            return;
        }

        const exists = selectedProductId.value
            ? items.some((item) => item.product_id === selectedProductId.value)
            : false;

        if (!exists) {
            selectedProductId.value = items[0].product_id;
        }
    },
    { immediate: true },
);

const handleSort = (key: string) => {
    if (isSorting || !key) {
return;
}

    isSorting = true;

    const validKeys: (keyof BcgResult)[] = [
        'ean',
        'product_name',
        'category_name',
        'quadrant',
        'x_value',
        'y_value',
        'facings',
        'share_gondola',
        'acao_espaco',
    ];

    if (!validKeys.includes(key as keyof BcgResult)) {
        isSorting = false;

        return;
    }

    sortConfig.value = {
        key: key as keyof BcgResult,
        direction: String(sortConfig.value.key) === key
            ? sortConfig.value.direction === 'asc' ? 'desc' : 'asc'
            : 'desc',
    };

    setTimeout(() => {
 isSorting = false; 
}, 100);
};

const formatPercent = (value: number) => `${(value ?? 0).toFixed(2)}%`;

const formatNumber = (value: number) =>
    new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(value ?? 0);
</script>

<template>
    <!-- Um único Provider para a tabela inteira: um por linha seria desperdício -->
    <TooltipProvider v-if="results.length > 0" :delay-duration="200">
    <div class="space-y-2">
        <!-- Filtros e Busca -->
        <Card>
            <CardContent class="pt-1 pb-1">
                <div v-if="hasTopSlot" class="mb-2 border-b border-border pb-2">
                    <slot name="top" />
                </div>

                <div class="flex flex-col gap-2 md:flex-row">
                    <div class="flex-1">
                        <div class="relative">
                            <Search class="absolute top-2 left-2 size-3.5 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                :placeholder="t('plannerate.analysis.bcg_results.search_placeholder')"
                                class="h-8 pl-7 text-xs"
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <Button
                            type="button"
                            size="sm"
                            :variant="filterByQuadrant === 'all' ? 'default' : 'outline'"
                            class="h-7 px-2.5 text-[11px]"
                            @click="filterByQuadrant = 'all'"
                        >
                            {{ t('plannerate.analysis.bcg_results.all') }} ({{ quadrantStats.total }})
                        </Button>
                        <Button
                            v-for="quadrant in QUADRANTS"
                            :key="quadrant"
                            type="button"
                            size="sm"
                            :variant="filterByQuadrant === quadrant ? 'default' : 'outline'"
                            class="h-7 px-2.5 text-[11px]"
                            @click="filterByQuadrant = quadrant"
                        >
                            {{ quadrantIcon(quadrant) }} {{ quadrantLabel(quadrant, xAxis, yAxis) }} ({{ quadrantStats[quadrant] }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :title="t('plannerate.analysis.bcg_results.export_report_tooltip')"
                            class="h-7 px-2.5 text-[11px]"
                            @click="exportBcgToCsv(filteredResults)"
                        >
                            <Download class="mr-1 size-3" />
                            {{ t('plannerate.analysis.bcg_results.export_report') }}
                        </Button>
                    </div>
                </div>

                <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                    <span>{{ filteredResults.length }} {{ t('plannerate.analysis.bcg_results.products_found') }}</span>
                    <span v-if="misallocatedCount > 0" class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-1.5 py-0.5 font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                        <AlertTriangle class="size-3" />
                        {{ misallocatedCount }} {{ t('plannerate.analysis.bcg_results.action_column').toLowerCase() }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <!-- Matriz BCG. Recebe os resultados FILTRADOS: o filtro acima escopa tudo -->
        <Card>
            <CardContent class="pt-2 pb-2">
                <div class="mb-1 flex items-center justify-between">
                    <p class="text-xs font-semibold text-foreground">{{ t('plannerate.analysis.bcg_chart.title') }}</p>
                    <div class="flex items-center gap-1">
                        <Button type="button" size="sm" variant="ghost" class="h-6 px-2 text-[10px]" @click="showExpanded = true">
                            <Maximize2 class="mr-1 size-3" />
                            {{ t('plannerate.analysis.bcg_chart.expand') }}
                        </Button>
                        <Button type="button" size="sm" variant="ghost" class="h-6 px-2 text-[10px]" @click="showChart = !showChart">
                            <component :is="showChart ? ChevronUp : ChevronDown" class="mr-1 size-3" />
                            {{ showChart ? t('plannerate.analysis.bcg_chart.hide') : t('plannerate.analysis.bcg_chart.show') }}
                        </Button>
                    </div>
                </div>
                <BcgScatterChart v-if="showChart" :results="filteredResults" />
            </CardContent>
        </Card>

        <!--
            Matriz em tela cheia. Embutida no painel ela divide espaço com a tabela e
            fica baixa demais para separar bolhas próximas; aqui ela respira.
        -->
        <Dialog v-model:open="showExpanded">
            <DialogContent class="z-[900] flex max-h-[92vh] w-full max-w-[92vw] flex-col overflow-hidden">
                <DialogHeader class="pb-1">
                    <DialogTitle class="text-base">{{ t('plannerate.analysis.bcg_chart.title') }}</DialogTitle>
                    <DialogDescription class="text-xs">
                        {{ t('plannerate.analysis.bcg_chart.aria') }}
                    </DialogDescription>
                </DialogHeader>
                <div class="min-h-0 flex-1 overflow-auto">
                    <BcgScatterChart v-if="showExpanded" :results="filteredResults" :height="620" />
                </div>
            </DialogContent>
        </Dialog>

        <!-- Tabela + Painel de Seleção -->
        <div class="grid items-start gap-1.5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <Card>
                <CardContent class="pt-0">
                    <div class="max-h-[58vh] overflow-auto xl:max-h-[64vh]">
                        <Table>
                            <TableHeader class="sticky top-0 z-10 bg-white dark:bg-gray-900">
                                <TableRow class="bg-gray-100 text-xs dark:bg-gray-900">
                                    <TableHeadAnalysis v-if="!isAggregated" :label="t('plannerate.analysis.bcg_results.ean')" sort-key="ean" :sort-config="sortConfig" class="sticky-col-1 min-w-30" @sort="handleSort" />
                                    <TableHeadAnalysis :label="t('plannerate.analysis.bcg_results.product')" sort-key="product_name" :sort-config="sortConfig" :class="[isAggregated ? 'sticky-col-1' : 'sticky-col-2', 'min-w-52']" @sort="handleSort" />
                                    <TableHeadAnalysis :label="t('plannerate.analysis.bcg_results.quadrant')" sort-key="quadrant" :sort-config="sortConfig" @sort="handleSort" />
                                    <!-- Cabeçalhos nomeiam a MÉTRICA escolhida, não "Eixo X"/"Eixo Y" -->
                                    <TableHeadAnalysis :label="axisLabel(xAxis)" sort-key="x_value" :sort-config="sortConfig" @sort="handleSort" />
                                    <TableHeadAnalysis :label="axisLabel(yAxis)" sort-key="y_value" :sort-config="sortConfig" @sort="handleSort" />
                                    <TableHeadAnalysis :label="t('plannerate.analysis.bcg_results.facings')" sort-key="facings" :sort-config="sortConfig" @sort="handleSort" />
                                    <TableHeadAnalysis :label="t('plannerate.analysis.bcg_results.share_gondola')" sort-key="share_gondola" :sort-config="sortConfig" @sort="handleSort" />
                                    <TableHeadAnalysis :label="t('plannerate.analysis.bcg_results.action_column')" sort-key="acao_espaco" :sort-config="sortConfig" @sort="handleSort" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="item in filteredResults"
                                    :key="item.product_id"
                                    :class="[rowClass(item.quadrant, selectedProductId === item.product_id), 'cursor-pointer text-xs']"
                                    @click="selectedProductId = item.product_id"
                                >
                                    <TableCell v-if="!isAggregated" class="sticky-col-1 py-2 font-mono text-[11px]">{{ item.ean }}</TableCell>
                                    <TableCell :class="[isAggregated ? 'sticky-col-1' : 'sticky-col-2', 'max-w-[300px] min-w-[200px] py-2']">
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-medium">{{ item.product_name }}</span>
                                            <span class="mt-0.5 text-[10px] text-muted-foreground">
                                                {{ item.category_name || t('plannerate.analysis.selection.no_category') }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <div class="flex items-center gap-1">
                                            <!-- Tag do quadrante: hover abre as ações recomendadas -->
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Badge variant="outline" :class="['cursor-help text-[10px] font-semibold', quadrantBadgeClass(item.quadrant)]">
                                                        {{ quadrantLabel(item.quadrant, item.x_axis, item.y_axis) }}
                                                    </Badge>
                                                </TooltipTrigger>
                                                <TooltipContent
                                                    side="right"
                                                    align="start"
                                                    :side-offset="8"
                                                    :collision-padding="16"
                                                    class="z-[9999] max-h-[68vh] w-[min(20rem,calc(100vw-1rem))] overflow-hidden border border-border bg-background p-0 shadow-2xl"
                                                >
                                                    <div class="max-h-[68vh] space-y-2 overflow-y-auto p-3">
                                                        <div :class="['rounded-lg border p-2 text-center', quadrantBadgeClass(item.quadrant)]">
                                                            <p class="text-xs font-bold">
                                                                <span aria-hidden="true">{{ quadrantIcon(item.quadrant) }}</span>
                                                                {{ quadrantLabel(item.quadrant, item.x_axis, item.y_axis) }}
                                                            </p>
                                                        </div>
                                                        <p class="text-[11px] leading-snug text-muted-foreground">
                                                            {{ quadrantDescription(item.quadrant) }}
                                                        </p>
                                                        <div v-if="quadrantActions(item.quadrant, item.x_axis, item.y_axis).length">
                                                            <p class="mb-1 text-[11px] font-semibold text-foreground">
                                                                {{ actionsTitle() }}
                                                            </p>
                                                            <ul class="space-y-0.5">
                                                                <li
                                                                    v-for="action in quadrantActions(item.quadrant, item.x_axis, item.y_axis)"
                                                                    :key="action"
                                                                    class="flex gap-1.5 text-[11px] leading-snug text-muted-foreground"
                                                                >
                                                                    <span aria-hidden="true" class="text-foreground">•</span>
                                                                    <span>{{ action }}</span>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                            <!-- Em cima da linha de corte: pode trocar de quadrante por ruído -->
                                            <span
                                                v-if="item.is_borderline"
                                                class="cursor-help text-[10px] text-muted-foreground"
                                                :title="t('plannerate.analysis.bcg_results.borderline_tooltip')"
                                            >≈</span>
                                            <span
                                                v-if="item.alerta_margem_negativa"
                                                class="cursor-help text-[10px] text-red-600 dark:text-red-400"
                                                :title="t('plannerate.analysis.bcg_results.negative_margin_tooltip')"
                                            >⚠</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="py-2 text-[11px] font-medium">{{ formatNumber(item.x_value) }}</TableCell>
                                    <TableCell class="py-2">
                                        <span :class="['text-[11px] font-medium', item.y_value < 0 ? 'text-red-600 dark:text-red-400' : '']">
                                            {{ formatNumber(item.y_value) }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="py-2 text-[11px]">{{ item.facings }}</TableCell>
                                    <TableCell class="py-2 text-[11px]">
                                        <span
                                            v-if="item.sem_dimensao"
                                            class="cursor-help text-muted-foreground"
                                            :title="t('plannerate.analysis.bcg_results.no_dimension_tooltip')"
                                        >—</span>
                                        <span v-else>{{ formatPercent(item.share_gondola) }}</span>
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <Badge variant="outline" :class="['text-[10px] font-semibold', spaceActionClass(item.acao_espaco)]">
                                            <span aria-hidden="true" class="mr-0.5">{{ spaceActionIcon(item.acao_espaco) }}</span>
                                            {{ spaceActionLabel(item.acao_espaco) }}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <div>
                <BcgSelectionPanel :selected="selectedResult" />
            </div>
        </div>
    </div>
    </TooltipProvider>
</template>

<style scoped>
.sticky-col-1 {
    position: sticky;
    left: 0;
    z-index: 2;
    background: inherit;
}

.sticky-col-2 {
    position: sticky;
    left: 7.5rem;
    z-index: 2;
    background: inherit;
}

:deep(thead .sticky-col-1),
:deep(thead .sticky-col-2) {
    z-index: 3;
}
</style>
