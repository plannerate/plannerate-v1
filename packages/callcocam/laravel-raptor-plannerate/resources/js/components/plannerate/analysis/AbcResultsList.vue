<script setup lang="ts">
import AbcSelectionPanel from '@/components/plannerate/analysis/abc/AbcSelectionPanel.vue';
import type { AbcResult } from '@/components/plannerate/analysis/abc/types';
import TableHeadAnalysis from '@/components/plannerate/analysis/TableHeadAnalysis.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAnalysisFilters } from '@/composables/plannerate/analysis/useAnalysisFilters';
import { usePlanogramEditor } from '@/composables/plannerate/v3/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/v3/usePlanogramSelection';
import { Search } from 'lucide-vue-next';
import { computed, ref, useSlots, watch } from 'vue';

interface Props {
    results: AbcResult[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    results: () => [],
    loading: false,
});

const slots = useSlots();
const hasTopSlot = computed(() => Boolean(slots.top));
const selectedProductId = ref<string | null>(null);
const removedProductIds = ref<Set<string>>(new Set());
const editor = usePlanogramEditor();
const selection = usePlanogramSelection();
const filterByClass = ref<'all' | 'A' | 'B' | 'C' | 'retirarMix'>('all');

// Usa o composable compartilhado (sem o filterByClass que será customizado)
const {
    searchQuery,
    sortConfig,
    classStats,
    filteredResults: baseFilteredResults,
    handleSort: handleSortBase,
    getClassBadgeVariant,
    getClassRowClass,
} = useAnalysisFilters<AbcResult>(() => props.results, {
    searchFields: ['product_name', 'ean', 'category_name'],
    defaultSortKey: 'media_ponderada',
    defaultSortDirection: 'desc',
});

// Resultados filtrados com suporte customizado para 'retirarMix'
const filteredResults = computed(() => {
    let filtered = baseFilteredResults.value;

    // Aplica filtro customizado de 'retirarMix'
    if (filterByClass.value === 'retirarMix') {
        filtered = filtered.filter((item) => item.retirar_do_mix === true);
    } else if (filterByClass.value !== 'all') {
        // Reaplica o filtro de classe ABC já que usamos baseFilteredResults sem filtro de classe
        filtered = filtered.filter((item) => item.classificacao === filterByClass.value);
    }

    return filtered;
});

// Stats customizado para incluir retirarMix
const stats = computed(() => {
    const retirarMix = props.results.filter((r) => r.retirar_do_mix).length;
    return {
        ...classStats.value,
        retirarMix,
        percentA:
            classStats.value.total > 0
                ? (
                      (classStats.value.classA / classStats.value.total) *
                      100
                  ).toFixed(1)
                : '0',
        percentB:
            classStats.value.total > 0
                ? (
                      (classStats.value.classB / classStats.value.total) *
                      100
                  ).toFixed(1)
                : '0',
        percentC:
            classStats.value.total > 0
                ? (
                      (classStats.value.classC / classStats.value.total) *
                      100
                  ).toFixed(1)
                : '0',
    };
});

// Wrapper para handleSort com validação de chaves
const handleSort = (key: string) => {
    const validKeys: (keyof AbcResult)[] = [
        'ean',
        'category_name',
        'product_name',
        'media_ponderada',
        'percentual_individual',
        'percentual_acumulado',
        'classificacao',
        'ranking',
        'retirar_do_mix',
        'status',
        
    ];
    handleSortBase(key, validKeys);
};

const formatPercent = (value: number) => {
    return `${value.toFixed(2)}%`;
};

const displayedResults = computed(() => {
    return filteredResults.value.filter(
        (item) => !removedProductIds.value.has(item.product_id),
    );
});

const selectedResult = computed<AbcResult | null>(() => {
    if (!selectedProductId.value) {
        return displayedResults.value[0] ?? null;
    }

    return (
        displayedResults.value.find(
            (item) => item.product_id === selectedProductId.value,
        ) ??
        displayedResults.value[0] ??
        null
    );
});

const matchingPlacements = computed(() => {
    const result = selectedResult.value;
    const gondola = editor.currentGondola.value;

    if (!result || !gondola?.sections) {
        return [];
    }

    const matches: Array<{ section: any; shelf: any; segment: any }> = [];

    for (const section of gondola.sections) {
        if (!section?.shelves) {
            continue;
        }

        for (const shelf of section.shelves) {
            if (!shelf?.segments) {
                continue;
            }

            for (const segment of shelf.segments) {
                if (segment?.layer?.product?.id === result.product_id) {
                    matches.push({ section, shelf, segment });
                }
            }
        }
    }

    return matches;
});

watch(
    displayedResults,
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

function handleSelectProduct(item: AbcResult): void {
    selectedProductId.value = item.product_id;

    const placement = matchingPlacements.value.find(
        (entry) => entry.segment?.layer?.product?.id === item.product_id,
    );

    if (!placement?.segment?.id || !placement?.shelf) {
        return;
    }

    selection.selectItem('segment', placement.segment.id, placement.segment, {
        shelf: placement.shelf,
    });
}

async function handleRemoveFromPlanogram(productId: string): Promise<void> {
    const placement = matchingPlacements.value[0];

    if (!placement?.segment?.id || !placement?.shelf) {
        return;
    }

    selection.selectItem('segment', placement.segment.id, placement.segment, {
        shelf: placement.shelf,
    });

    const deleted = await selection.deleteSelected();

    if (!deleted) {
        return;
    }

    removedProductIds.value = new Set(removedProductIds.value).add(productId);

    if (selectedProductId.value === productId) {
        selectedProductId.value = displayedResults.value[0]?.product_id ?? null;
    }
}
</script>

<template>
    <div v-if="results.length > 0" class="space-y-2">
        <!-- Stats Cards --> 
        <!-- Filters -->
        <Card>
            <CardContent class="pt-1 pb-1">
                <div v-if="hasTopSlot" class="mb-2 border-b border-border pb-2">
                    <slot name="top" />
                </div>

                <div class="flex flex-col gap-2 md:flex-row">
                    <div class="flex-1">
                        <div class="relative">
                            <Search
                                class="absolute top-2 left-2 size-3.5 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar por produto, EAN ou categoria..."
                                class="h-8 pl-7 text-xs"
                            />
                        </div>
                    </div>
                    <div class="flex gap-1.5">
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                filterByClass === 'all' ? 'default' : 'outline'
                            "
                            @click="filterByClass = 'all'"
                            class="h-8 text-xs"
                        >
                            Todas ({{ stats.total }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                filterByClass === 'A' ? 'default' : 'outline'
                            "
                            @click="filterByClass = 'A'"
                            class="h-8 text-xs"
                        >
                            A ({{ stats.classA }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                filterByClass === 'B' ? 'default' : 'outline'
                            "
                            @click="filterByClass = 'B'"
                            class="h-8 text-xs"
                        >
                            B ({{ stats.classB }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                filterByClass === 'C' ? 'default' : 'outline'
                            "
                            @click="filterByClass = 'C'"
                            class="h-8 text-xs"
                        >
                            C ({{ stats.classC }})
                        </Button>
                        <Button 
                            type="button"
                            size="sm"
                            :variant="
                                filterByClass === 'retirarMix'
                                    ? 'destructive'
                                    : 'outline'
                            "
                            @click="filterByClass = 'retirarMix'"
                            class="h-8 text-xs"
                        >
                            Retirar do Mix ({{ stats.retirarMix }})
                        </Button>
                    </div>
                </div>

                <div class="mt-1 text-[11px] text-muted-foreground">
                    {{ displayedResults.length }} produto(s) encontrado(s)
                </div>
            </CardContent>
        </Card>

        <!-- Results Table -->
        <div class="grid items-start gap-1.5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <Card>
                <CardContent class="pt-0">
                    <div class="max-h-[58vh] overflow-auto xl:max-h-[64vh]">
                        <Table>
                            <TableHeader
                                class="sticky top-0 z-10 bg-white dark:bg-gray-900"
                            >
                                <TableRow
                                    class="bg-gray-100 text-xs dark:bg-gray-900"
                                >
                                    <TableHeadAnalysis
                                        label="EAN"
                                        sort-key="ean"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                        class="sticky-col-1 min-w-[7.5rem]"
                                    />
                                    <TableHeadAnalysis
                                        label="Produto"
                                        sort-key="product_name"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                        class="sticky-col-2 min-w-[13rem]"
                                    />
                                    <TableHeadAnalysis
                                        label="Média Ponderada"
                                        sort-key="media_ponderada"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        label="% Individual"
                                        sort-key="percentual_individual"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        label="% Acumulada"
                                        sort-key="percentual_acumulado"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        label="Classe ABC"
                                        sort-key="classificacao"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        label="Ranking"
                                        sort-key="ranking"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        label="Retirar do Mix"
                                        sort-key="retirar_do_mix"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        label="Status"
                                        sort-key="status.status"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                    />
                                    <TableHeadAnalysis
                                        label="Detalhe do Status"
                                        sort-key="status.motivo"
                                        :sortConfig="sortConfig"
                                        @sort="handleSort"
                                    />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="item in displayedResults"
                                    :key="item.product_id"
                                    :class="[
                                        getClassRowClass(item.classificacao),
                                        selectedProductId === item.product_id
                                            ? 'bg-primary/5 ring-1 ring-primary/40 dark:bg-primary/10'
                                            : '',
                                        'cursor-pointer text-xs',
                                    ]"
                                    @click="handleSelectProduct(item)"
                                >
                                    <TableCell
                                        class="sticky-col-1 py-2 font-mono text-[11px]"
                                    >
                                        {{ item.ean }}
                                    </TableCell>
                                    <TableCell
                                        class="sticky-col-2 max-w-[300px] min-w-[200px] py-2"
                                    >
                                        <div class="flex flex-col">
                                            <span
                                                class="text-[11px] font-medium"
                                                >{{ item.product_name }}</span
                                            >
                                            <span
                                                class="mt-0.5 text-[10px] text-muted-foreground"
                                            >
                                                {{
                                                    item.category_name ||
                                                    'Sem categoria'
                                                }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        class="py-2 text-[11px] font-medium"
                                    >
                                        {{ item.media_ponderada.toFixed(2) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-[11px]">
                                        {{
                                            formatPercent(
                                                item.percentual_individual,
                                            )
                                        }}
                                    </TableCell>
                                    <TableCell class="py-2 text-[11px]">
                                        {{
                                            formatPercent(
                                                item.percentual_acumulado,
                                            )
                                        }}
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <Badge
                                            :variant="
                                                getClassBadgeVariant(
                                                    item.classificacao,
                                                )
                                            "
                                            class="text-[10px] font-semibold"
                                        >
                                            {{ item.classificacao }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell
                                        class="py-2 text-[11px] font-medium"
                                    >
                                        {{ item.ranking }}
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <Badge
                                            v-if="item.retirar_do_mix"
                                            variant="destructive"
                                            class="text-[10px]"
                                        >
                                            Sim
                                        </Badge>
                                        <span
                                            v-else
                                            class="text-[11px] text-muted-foreground"
                                            >Não</span
                                        >
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <Badge
                                            v-if="item.status"
                                            :variant="
                                                item.status.status === 'Ativo'
                                                    ? 'default'
                                                    : 'outline'
                                            "
                                            class="text-[10px]"
                                        >
                                            {{ item.status.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell
                                        class="py-2 text-[11px] text-muted-foreground"
                                    >
                                        {{ item.status?.motivo }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <div>
                <AbcSelectionPanel
                    :selected="selectedResult"
                    @remove-from-planogram="handleRemoveFromPlanogram"
                />
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
