<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import TargetStockSelectionPanel from '@/components/plannerate/analysis/target-stock/TargetStockSelectionPanel.vue';
import type { TargetStockResult } from '@/components/plannerate/analysis/target-stock/types';
import { Search } from 'lucide-vue-next';
import TableHeadAnalysis from '@/components/plannerate/analysis/TableHeadAnalysis.vue';
import { useAnalysisFilters } from '@/composables/plannerate/analysis/useAnalysisFilters';
import { usePlanogramEditor } from '@/composables/plannerate/v3/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/v3/usePlanogramSelection';
import { useTargetStockAnalysis } from '@/composables/plannerate/v3/useTargetStockAnalysis';
import { computed, ref, useSlots, watch } from 'vue';

interface Props {
    results: TargetStockResult[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    results: () => [],
    loading: false,
});

const editor = usePlanogramEditor();
const selection = usePlanogramSelection();
const { calculateSegmentCapacity, getStockStatus, calculateToleranceMargin, DEFAULT_TOLERANCE } = useTargetStockAnalysis();

const selectedProductId = ref<string | null>(null);
const slots = useSlots();
const hasTopSlot = computed(() => Boolean(slots.top));

// Usa o composable compartilhado
const {
    searchQuery,
    filterByClass,
    sortConfig,
    classStats,
    filteredResults,
    handleSort: handleSortBase,
    getClassRowClass: getClassRowClassBase,
} = useAnalysisFilters<TargetStockResult>(
    () => props.results,
    {
        searchFields: ['product_name', 'ean'],
        defaultSortKey: 'estoque_alvo',
        defaultSortDirection: 'desc',
    }
);

// Wrapper para handleSort com validação de chaves
const handleSort = (key: string) => {
    const validKeys: (keyof TargetStockResult)[] = [
        'ean', 'product_name', 'demanda_media', 'desvio_padrao', 
        'cobertura_dias', 'nivel_servico', 'z_score', 
        'estoque_seguranca', 'estoque_minimo', 'estoque_alvo', 
        'estoque_atual', 'classificacao', 'permite_frentes',
    ];
    handleSortBase(key, validKeys);
};

// Wrapper customizado para incluir alerta de variabilidade
const getClassRowClass = (classificacao: 'A' | 'B' | 'C', alertaVariabilidade: boolean) => {
    return getClassRowClassBase(classificacao, alertaVariabilidade);
};

const formatNumber = (value: number, decimals: number = 2) => {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
};

const selectedResult = computed<TargetStockResult | null>(() => {
    if (!selectedProductId.value) {
        return filteredResults.value[0] ?? null;
    }

    return (
        filteredResults.value.find(
            (item) => item.product_id === selectedProductId.value,
        ) ?? filteredResults.value[0] ?? null
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

const activePlacement = computed(() => matchingPlacements.value[0] ?? null);

const segmentQuantity = computed(() => activePlacement.value?.segment?.quantity ?? 0);
const layerQuantity = computed(() => activePlacement.value?.segment?.layer?.quantity ?? 0);
const productDepth = computed(() => activePlacement.value?.segment?.layer?.product?.depth ?? 0);
const shelfDepth = computed(() => activePlacement.value?.shelf?.depth ?? 0);

const itemsInDepth = computed(() => {
    if (!productDepth.value || !shelfDepth.value || productDepth.value <= 0) {
        return 0;
    }

    return Math.floor(shelfDepth.value / productDepth.value);
});

const segmentCapacity = computed(() => {
    return calculateSegmentCapacity(
        segmentQuantity.value,
        layerQuantity.value,
        productDepth.value,
        shelfDepth.value,
    );
});

const toleranceMargin = computed(() => {
    if (!selectedResult.value) {
        return 0;
    }

    return calculateToleranceMargin(
        selectedResult.value.estoque_alvo,
        DEFAULT_TOLERANCE,
    );
});

const toleranceMin = computed(() => {
    if (!selectedResult.value) {
        return 0;
    }

    return Math.round(selectedResult.value.estoque_alvo - toleranceMargin.value);
});

const toleranceMax = computed(() => {
    if (!selectedResult.value) {
        return 0;
    }

    return Math.round(selectedResult.value.estoque_alvo + toleranceMargin.value);
});

const stockStatus = computed<'increase' | 'decrease' | 'ok' | 'unknown'>(() => {
    if (!selectedResult.value || !activePlacement.value) {
        return 'unknown';
    }

    return getStockStatus(
        segmentCapacity.value,
        selectedResult.value.estoque_alvo,
        DEFAULT_TOLERANCE,
    );
});

const productImageUrl = computed(() => {
    return activePlacement.value?.segment?.layer?.product?.image_url ?? null;
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

function handleSelectProduct(item: TargetStockResult): void {
    selectedProductId.value = item.product_id;

    const placement = matchingPlacements.value[0];
    if (!placement) {
        return;
    }

    selection.selectItem('segment', placement.segment.id, placement.segment, {
        shelf: placement.shelf,
    });
}

function adjustFronts(delta: number): void {
    for (const placement of matchingPlacements.value) {
        const layerId = placement.segment?.layer?.id;
        const currentQuantity = Number(placement.segment?.layer?.quantity ?? 1);

        if (!layerId) {
            continue;
        }

        const nextQuantity = Math.max(1, currentQuantity + delta);
        editor.updateLayer(layerId, { quantity: nextQuantity });
    }
}

function handleIncreaseFronts(): void {
    adjustFronts(1);
}

function handleDecreaseFronts(): void {
    adjustFronts(-1);
}
</script>

<template>
    <div v-if="results.length > 0" class="space-y-1.5">
        <!-- Filters -->
        <Card>
            <CardContent class="pt-2.5 pb-2.5">
                <div v-if="hasTopSlot" class="mb-2 border-b border-border pb-2">
                    <slot name="top" />
                </div>

                <div class="flex flex-col gap-1.5 md:flex-row md:items-center md:justify-between">
                    <div class="w-full md:max-w-[16rem]">
                        <div class="relative">
                            <Search class="absolute left-2 top-2 size-3.5 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar por produto ou EAN..."
                                class="h-8 pl-7 text-xs"
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <Button
                            type="button"
                            size="sm"
                            :variant="filterByClass === 'all' ? 'default' : 'outline'"
                            @click="filterByClass = 'all'"
                            class="h-7 px-2.5 text-[11px]"
                        >
                            Todas ({{ classStats.total }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="filterByClass === 'A' ? 'default' : 'outline'"
                            @click="filterByClass = 'A'"
                            class="h-7 px-2.5 text-[11px]"
                        >
                            A ({{ classStats.classA }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="filterByClass === 'B' ? 'default' : 'outline'"
                            @click="filterByClass = 'B'"
                            class="h-7 px-2.5 text-[11px]"
                        >
                            B ({{ classStats.classB }})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="filterByClass === 'C' ? 'default' : 'outline'"
                            @click="filterByClass = 'C'"
                            class="h-7 px-2.5 text-[11px]"
                        >
                            C ({{ classStats.classC }})
                        </Button>
                    </div>
                </div>

                <div class="mt-1 text-[11px] text-muted-foreground">
                    {{ filteredResults.length }} produto(s) encontrado(s)
                </div>
            </CardContent>
        </Card>

        <!-- Results Table -->
        <div class="grid items-start gap-1.5 xl:grid-cols-[minmax(0,1fr)_21rem]">
            <Card>
                <CardContent class="pt-0">
                    <div class="max-h-[58vh] overflow-auto xl:max-h-[64vh]">
                        <Table>
                            <TableHeader class="sticky top-0 z-10 bg-white dark:bg-gray-900">
                                <TableRow class="bg-gray-100 text-xs dark:bg-gray-900">
                                <TableHeadAnalysis
                                    label="EAN"
                                    sort-key="ean"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                    class="sticky-col-1 min-w-[7.5rem]"
                                />
                                <TableHeadAnalysis
                                    label="Descrição Produto"
                                    sort-key="product_name"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                    class="sticky-col-2 min-w-[13rem]"
                                />
                                <TableHeadAnalysis
                                    label="Demanda média"
                                    sort-key="demanda_media"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Desvio Padrão"
                                    sort-key="desvio_padrao"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Cobertura de estoque em dias (Reposição)"
                                    sort-key="cobertura_dias"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Nível de Serviço"
                                    sort-key="nivel_servico"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Constante Z-ns"
                                    sort-key="z_score"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Estoque de Segurança"
                                    sort-key="estoque_seguranca"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Estoque mínimo prateleira"
                                    sort-key="estoque_minimo"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Estoque alvo prateleira"
                                    sort-key="estoque_alvo"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Permite frentes múltiplas"
                                    sort-key="permite_frentes"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                <TableHeadAnalysis
                                    label="Estoque Atual"
                                    sort-key="estoque_atual"
                                    :sortConfig="sortConfig"
                                    @sort="handleSort"
                                />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="item in filteredResults"
                                    :key="item.product_id"
                                    :class="[
                                        getClassRowClass(item.classificacao, item.alerta_variabilidade),
                                        selectedProductId === item.product_id
                                            ? 'ring-1 ring-primary/40 bg-primary/5 dark:bg-primary/10'
                                            : '',
                                        'cursor-pointer text-xs',
                                    ]"
                                    @click="handleSelectProduct(item)"
                                >
                                    <TableCell class="sticky-col-1 py-2 font-mono text-[11px]">
                                        {{ item.ean }}
                                    </TableCell>
                                    <TableCell class="sticky-col-2 py-2 text-[11px]">{{ item.product_name }}</TableCell>
                                    <TableCell class="py-2 text-right text-[11px]">
                                        {{ formatNumber(item.demanda_media) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-right text-[11px]">
                                        {{ formatNumber(item.desvio_padrao) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-right text-[11px]">
                                        {{ item.cobertura_dias }}
                                    </TableCell>
                                    <TableCell class="py-2 text-right text-[11px]">
                                        {{ formatNumber(item.nivel_servico, 1) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-right text-[11px]">
                                        {{ formatNumber(item.z_score, 3) }}
                                    </TableCell>
                                    <TableCell class="py-2 text-right text-[11px]">
                                        {{ item.estoque_seguranca }}
                                    </TableCell>
                                    <TableCell class="py-2 text-right text-[11px]">
                                        {{ item.estoque_minimo }}
                                    </TableCell>
                                    <TableCell class="py-2 text-right text-[11px] font-medium">
                                        {{ item.estoque_alvo }}
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <Badge
                                            :variant="item.permite_frentes === 'Sim' ? 'default' : 'outline'"
                                            class="text-[10px]"
                                        >
                                            {{ item.permite_frentes }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="py-2 text-right text-[11px]">
                                        {{ item.estoque_atual }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <div>
                <TargetStockSelectionPanel
                    :selected="selectedResult"
                    :has-placement="Boolean(activePlacement)"
                    :matched-segments-count="matchingPlacements.length"
                    :current-fronts="activePlacement?.segment?.layer?.quantity ?? null"
                    :segment-quantity="segmentQuantity"
                    :layer-quantity="layerQuantity"
                    :items-in-depth="itemsInDepth"
                    :segment-capacity="segmentCapacity"
                    :product-image-url="productImageUrl"
                    :stock-status="stockStatus"
                    :tolerance-min="toleranceMin"
                    :tolerance-max="toleranceMax"
                    @increase-fronts="handleIncreaseFronts"
                    @decrease-fronts="handleDecreaseFronts"
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

