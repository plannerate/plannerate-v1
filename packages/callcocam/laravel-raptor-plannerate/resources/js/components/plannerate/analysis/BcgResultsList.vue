<script setup lang="ts">
import { Download, Search } from 'lucide-vue-next';
import { computed, ref, useSlots, watch } from 'vue';
import BcgSelectionPanel from '@/components/plannerate/analysis/bcg/BcgSelectionPanel.vue';
import type { BcgQuadrant, BcgResult } from '@/components/plannerate/analysis/bcg/types';
import TableHeadAnalysis from '@/components/plannerate/analysis/TableHeadAnalysis.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

const searchQuery = ref('');
const filterByQuadrant = ref<'all' | BcgQuadrant>('all');
const sortConfig = ref<{ key: keyof BcgResult; direction: 'asc' | 'desc' }>({
    key: 'market_share',
    direction: 'desc',
});
const selectedProductId = ref<string | null>(null);

let isSorting = false;

/**
 * Resultados filtrados por busca e quadrante, com ordenação aplicada
 */
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

/**
 * Contagem de produtos por quadrante para os filtros
 */
const quadrantStats = computed(() => ({
    total: props.results.length,
    star: props.results.filter((r) => r.quadrant === 'star').length,
    cash_cow: props.results.filter((r) => r.quadrant === 'cash_cow').length,
    question_mark: props.results.filter((r) => r.quadrant === 'question_mark').length,
    dog: props.results.filter((r) => r.quadrant === 'dog').length,
}));

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
        'market_share',
        'growth_rate',
        'total_value_current',
        'total_value_previous',
        'quadrant',
    ];

    if (!validKeys.includes(key as keyof BcgResult)) {
        isSorting = false;

        return;
    }

    const typedKey = key as keyof BcgResult;
    const currentKey = String(sortConfig.value.key);

    sortConfig.value = {
        key: typedKey,
        direction: currentKey === key
            ? sortConfig.value.direction === 'asc' ? 'desc' : 'asc'
            : 'desc',
    };

    setTimeout(() => {
        isSorting = false;
    }, 100);
};

const formatPercent = (value: number) => `${value.toFixed(2)}%`;

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value ?? 0);

/**
 * Cor de fundo da linha baseado no quadrante
 */
const getRowClass = (quadrant: BcgQuadrant, isSelected: boolean): string => {
    const base = isSelected ? 'ring-1 ring-primary/40 ' : '';

    const quadrantClasses: Record<BcgQuadrant, string> = {
        star: 'bg-yellow-50/70 dark:bg-yellow-950/25',
        cash_cow: 'bg-green-50/70 dark:bg-green-950/25',
        question_mark: 'bg-blue-50/70 dark:bg-blue-950/25',
        dog: 'bg-red-50/70 dark:bg-red-950/25',
    };

    return base + (quadrantClasses[quadrant] ?? '');
};

/**
 * Badge do quadrante BCG com cor correspondente
 */
const getQuadrantBadgeClass = (quadrant: BcgQuadrant): string => {
    const classes: Record<BcgQuadrant, string> = {
        star: 'border-yellow-300 bg-yellow-100 text-yellow-800 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-200',
        cash_cow: 'border-green-300 bg-green-100 text-green-800 dark:border-green-700 dark:bg-green-900/40 dark:text-green-200',
        question_mark: 'border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
        dog: 'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-900/40 dark:text-red-200',
    };

    return classes[quadrant] ?? '';
};

const quadrantLabel = (quadrant: BcgQuadrant): string => {
    const labels: Record<BcgQuadrant, string> = {
        star: t('plannerate.analysis.bcg_results.star'),
        cash_cow: t('plannerate.analysis.bcg_results.cash_cow'),
        question_mark: t('plannerate.analysis.bcg_results.question_mark'),
        dog: t('plannerate.analysis.bcg_results.dog'),
    };

    return labels[quadrant] ?? quadrant;
};
</script>

<template>
    <div v-if="results.length > 0" class="space-y-2">
        <!-- Filtros e Busca -->
        <Card>
            <CardContent class="pb-1 pt-1">
                <div v-if="hasTopSlot" class="mb-2 border-b border-border pb-2">
                    <slot name="top" />
                </div>

                <div class="flex flex-col gap-2 md:flex-row">
                    <div class="flex-1">
                        <div class="relative">
                            <Search class="absolute left-2 top-2 size-3.5 text-muted-foreground" />
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
                            type="button"
                            size="sm"
                            :variant="filterByQuadrant === 'star' ? 'default' : 'outline'"
                            class="h-7 px-2.5 text-[11px]"
                            @click="filterByQuadrant = 'star'"
                        >
                            ⭐ {{ t('plannerate.analysis.bcg_results.star') }} ({{ quadrantStats.star }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="filterByQuadrant === 'cash_cow' ? 'default' : 'outline'"
                            class="h-7 px-2.5 text-[11px]"
                            @click="filterByQuadrant = 'cash_cow'"
                        >
                            🐄 {{ t('plannerate.analysis.bcg_results.cash_cow') }} ({{ quadrantStats.cash_cow }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="filterByQuadrant === 'question_mark' ? 'default' : 'outline'"
                            class="h-7 px-2.5 text-[11px]"
                            @click="filterByQuadrant = 'question_mark'"
                        >
                            ❓ {{ t('plannerate.analysis.bcg_results.question_mark') }} ({{ quadrantStats.question_mark }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="filterByQuadrant === 'dog' ? 'default' : 'outline'"
                            class="h-7 px-2.5 text-[11px]"
                            @click="filterByQuadrant = 'dog'"
                        >
                            🐕 {{ t('plannerate.analysis.bcg_results.dog') }} ({{ quadrantStats.dog }})
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

                <div class="mt-1 text-[11px] text-muted-foreground">
                    {{ filteredResults.length }} {{ t('plannerate.analysis.bcg_results.products_found') }}
                </div>
            </CardContent>
        </Card>

        <!-- Tabela + Painel de Seleção -->
        <div class="grid items-start gap-1.5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <Card>
                <CardContent class="pt-0">
                    <div class="max-h-[58vh] overflow-auto xl:max-h-[64vh]">
                        <Table>
                            <TableHeader class="sticky top-0 z-10 bg-white dark:bg-gray-900">
                                <TableRow class="bg-gray-100 text-xs dark:bg-gray-900">
                                    <TableHeadAnalysis
                                        :label="t('plannerate.analysis.bcg_results.ean')"
                                        sort-key="ean"
                                        :sort-config="sortConfig"
                                        class="sticky-col-1 min-w-30"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        :label="t('plannerate.analysis.bcg_results.product')"
                                        sort-key="product_name"
                                        :sort-config="sortConfig"
                                        class="sticky-col-2 min-w-52"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        :label="t('plannerate.analysis.bcg_results.quadrant')"
                                        sort-key="quadrant"
                                        :sort-config="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        :label="t('plannerate.analysis.bcg_results.market_share')"
                                        sort-key="market_share"
                                        :sort-config="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        :label="t('plannerate.analysis.bcg_results.growth_rate')"
                                        sort-key="growth_rate"
                                        :sort-config="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        :label="t('plannerate.analysis.bcg_results.value_current')"
                                        sort-key="total_value_current"
                                        :sort-config="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        :label="t('plannerate.analysis.bcg_results.value_previous')"
                                        sort-key="total_value_previous"
                                        :sort-config="sortConfig"
                                        @sort="handleSort"
                                    />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="item in filteredResults"
                                    :key="item.product_id"
                                    :class="[
                                        getRowClass(item.quadrant, selectedProductId === item.product_id),
                                        'cursor-pointer text-xs',
                                    ]"
                                    @click="selectedProductId = item.product_id"
                                >
                                    <TableCell class="sticky-col-1 py-2 font-mono text-[11px]">
                                        {{ item.ean }}
                                    </TableCell>
                                    <TableCell class="sticky-col-2 max-w-[300px] min-w-[200px] py-2">
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-medium">{{ item.product_name }}</span>
                                            <span class="mt-0.5 text-[10px] text-muted-foreground">
                                                {{ item.category_name || t('plannerate.analysis.selection.no_category') }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <Badge
                                            variant="outline"
                                            :class="['text-[10px] font-semibold', getQuadrantBadgeClass(item.quadrant)]"
                                        >
                                            {{ quadrantLabel(item.quadrant) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="py-2 text-[11px] font-medium">
                                        {{ formatPercent(item.market_share) }}
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <span
                                            :class="[
                                                'text-[11px] font-medium',
                                                item.growth_rate >= 0
                                                    ? 'text-green-600 dark:text-green-400'
                                                    : 'text-red-600 dark:text-red-400',
                                            ]"
                                        >
                                            {{ formatPercent(item.growth_rate) }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="py-2 text-[11px]">
                                        {{ formatCurrency(item.total_value_current) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-[11px]">
                                        {{ formatCurrency(item.total_value_previous) }}
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
