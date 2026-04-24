<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AbcFilters from '@/components/plannerate/AbcFilters.vue';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    BarChart3,
    Calculator,
    Search,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { AbcResult } from '@/components/plannerate/analysis/abc/types';

interface InitialData {
    results?: AbcResult[];
    category?: {
        id: string;
        name: string;
    } | null;
    category_id?: string;
    eans?: string[];
    filters?: {
        table_type: string;
        client_id?: string;
        store_id?: string;
        date_from?: string;
        date_to?: string;
        month_from?: string;
        month_to?: string;
    };
    weights?: {
        peso_qtde: number;
        peso_valor: number;
        peso_margem: number;
    };
    cuts?: {
        corte_a: number;
        corte_b: number;
    };
}

interface Props {
    initialData?: InitialData | null;
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    initialData: null,
    errors: () => ({}),
});

// Form state
const form = ref({
    category_id: props.initialData?.category_id || props.initialData?.category?.id || '',
    eans: props.initialData?.eans || [],
    eanInput: '',
    table_type: (props.initialData?.filters?.table_type || 'sales') as 'sales' | 'monthly_summaries',
    date_from: props.initialData?.filters?.date_from || '',
    date_to: props.initialData?.filters?.date_to || '',
    month_from: props.initialData?.filters?.month_from || '',
    month_to: props.initialData?.filters?.month_to || '',
    peso_qtde: props.initialData?.weights?.peso_qtde || 0.3,
    peso_valor: props.initialData?.weights?.peso_valor || 0.3,
    peso_margem: props.initialData?.weights?.peso_margem || 0.4,
    corte_a: props.initialData?.cuts?.corte_a || 0.8,
    corte_b: props.initialData?.cuts?.corte_b || 0.85,
});

// Computed para os filtros (usado no componente AbcFilters)
const filtersForm = computed({
    get: () => ({
        category_id: form.value.category_id,
        eans: form.value.eans,
        eanInput: form.value.eanInput,
        table_type: form.value.table_type,
        date_from: form.value.date_from,
        date_to: form.value.date_to,
        month_from: form.value.month_from,
        month_to: form.value.month_to,
    }),
    set: (value) => {
        form.value.category_id = value.category_id;
        form.value.eans = value.eans;
        form.value.eanInput = value.eanInput;
        form.value.table_type = value.table_type;
        form.value.date_from = value.date_from;
        form.value.date_to = value.date_to;
        form.value.month_from = value.month_from;
        form.value.month_to = value.month_to;
    },
});

// Search/filter state
const searchQuery = ref('');
const sortBy = ref<keyof AbcResult>('media_ponderada');
const sortOrder = ref<'asc' | 'desc'>('desc');
const filterByClass = ref<'all' | 'A' | 'B' | 'C' | any>('all');

// Results
const results = computed(() => props.initialData?.results || []);

// Filtered and sorted results
const filteredResults = computed(() => {
    let filtered = [...results.value];

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(
            (item) =>
                item.product_name.toLowerCase().includes(query) ||
                item.ean?.toLowerCase().includes(query) ||
                item.category_name.toLowerCase().includes(query),
        );
    }

    // Class filter
    if (filterByClass.value !== 'all') {
        filtered = filtered.filter(
            (item) => item.classificacao === filterByClass.value,
        );
    }

    // Sort
    filtered.sort((a, b) => {
        const aVal = a[sortBy.value];
        const bVal = b[sortBy.value];

        if (typeof aVal === 'number' && typeof bVal === 'number') {
            return sortOrder.value === 'asc' ? aVal - bVal : bVal - aVal;
        }

        if (typeof aVal === 'string' && typeof bVal === 'string') {
            return sortOrder.value === 'asc'
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        }

        return 0;
    });

    return filtered;
});

// Stats
const stats = computed(() => {
    const total = results.value.length;
    const classA = results.value.filter((r) => r.classificacao === 'A').length;
    const classB = results.value.filter((r) => r.classificacao === 'B').length;
    const classC = results.value.filter((r) => r.classificacao === 'C').length;
    const retirarMix = results.value.filter((r) => r.retirar_do_mix).length;

    return {
        total,
        classA,
        classB,
        classC,
        retirarMix,
        percentA: total > 0 ? ((classA / total) * 100).toFixed(1) : '0',
        percentB: total > 0 ? ((classB / total) * 100).toFixed(1) : '0',
        percentC: total > 0 ? ((classC / total) * 100).toFixed(1) : '0',
    };
});

// Methods
const handleSort = (column: keyof AbcResult) => {
    if (sortBy.value === column) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortOrder.value = 'desc';
    }
};

const submitAnalysis = () => {
    router.post(
        '/abc-analysis/analyze',
        {
            ...form.value,
            eans: form.value.eans.length > 0 ? form.value.eans : undefined,
            category_id: form.value.category_id || undefined,
            date_from: form.value.date_from || undefined,
            date_to: form.value.date_to || undefined,
            month_from: form.value.month_from || undefined,
            month_to: form.value.month_to || undefined,
        },
        {
            preserveState: false,
            preserveScroll: true,
            onError: (errors) => {
                console.error('Erros de validação:', errors);
            },
        },
    );
};

const getClassBadgeVariant = (classificacao: 'A' | 'B' | 'C') => {
    switch (classificacao) {
        case 'A':
            return 'default';
        case 'B':
            return 'secondary';
        case 'C':
            return 'outline';
        default:
            return 'outline';
    }
};

const getClassRowClass = (classificacao: 'A' | 'B' | 'C') => {
    switch (classificacao) {
        case 'A':
            return 'bg-blue-100 dark:bg-blue-900/50';
        case 'B':
            return 'bg-yellow-100 dark:bg-yellow-900/50';
        case 'C':
            return '';
        default:
            return '';
    }
};
 

const formatPercent = (value: number) => {
    return `${value.toFixed(2)}%`;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight">Análise ABC</h1>
            <p class="text-muted-foreground">
                Classificação de produtos por importância usando média ponderada
            </p>
        </div>

        <!-- Form Card -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Calculator class="size-5" />
                    Configuração da Análise
                </CardTitle>
                <CardDescription>
                    Selecione uma categoria ou informe os EANs dos produtos
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submitAnalysis" class="space-y-6">
                    <!-- Componente de Filtros -->
                    <AbcFilters
                        v-model="filtersForm"
                        :errors="errors"
                    />

                    <!-- Pesos -->
                    <div class="grid grid-cols-5 gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Peso Quantidade</label>
                            <Input v-model.number="form.peso_qtde" type="number" step="0.1" min="0" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Peso Valor</label>
                            <Input v-model.number="form.peso_valor" type="number" step="0.1" min="0" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Peso Margem</label>
                            <Input v-model.number="form.peso_margem" type="number" step="0.1" min="0" />
                        </div>

                        <!-- Limites de Classificação -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Limite Classe A (%)</label>
                            <Input v-model.number="form.corte_a" type="number" step="0.01" min="0" max="1" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Limite Classe B (%)</label>
                            <Input v-model.number="form.corte_b" type="number" step="0.01" min="0" max="1" />
                        </div>
                    </div>

                    <Button type="submit" class="w-full">
                        <BarChart3 class="mr-2 size-4" />
                        Executar Análise
                    </Button>
                </form>
            </CardContent>
        </Card>

        <!-- Results -->
        <div v-if="results.length > 0" class="space-y-4">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold">{{ stats.total }}</div>
                        <p class="text-xs text-muted-foreground">Total de Produtos</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-green-600">
                            {{ stats.classA }} ({{ stats.percentA }}%)
                        </div>
                        <p class="text-xs text-muted-foreground">Classe A</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-yellow-600">
                            {{ stats.classB }} ({{ stats.percentB }}%)
                        </div>
                        <p class="text-xs text-muted-foreground">Classe B</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-gray-600">
                            {{ stats.classC }} ({{ stats.percentC }}%)
                        </div>
                        <p class="text-xs text-muted-foreground">Classe C</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-red-600">
                            {{ stats.retirarMix }}
                        </div>
                        <p class="text-xs text-muted-foreground">Retirar do Mix</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <Search class="absolute left-2 top-2.5 size-4 text-muted-foreground" />
                                <Input v-model="searchQuery" placeholder="Buscar por produto, EAN ou categoria..."
                                    class="pl-8" />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-for="filter in ['all', 'A', 'B', 'C']"
                                :key="filter"
                                type="button"
                                :variant="filterByClass === filter ? 'default' : 'outline'"
                                @click="filterByClass = filter"
                            >
                                {{ filter === 'all' ? 'Todas' : `Classe ${filter}` }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Results Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Resultados da Análise</CardTitle>
                    <CardDescription>
                        {{ filteredResults.length }} produto(s) encontrado(s)
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="cursor-pointer" @click="handleSort('ean')">
                                        EAN
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('category_name')">
                                        Categoria
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('product_name')">
                                        Nome
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('media_ponderada')">
                                        Média Ponderada
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('percentual_individual')">
                                        % Individual
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('percentual_acumulado')">
                                        % Acumulada
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('classificacao')">
                                        Classe ABC
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('ranking')">
                                        Ranking
                                    </TableHead>
                                    <TableHead>Retirar?</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Detalhe do Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow 
                                    v-for="item in filteredResults" 
                                    :key="item.product_id"
                                    :class="getClassRowClass(item.classificacao)"
                                >
                                    <TableCell class="font-mono text-xs">
                                        {{ item.ean }}
                                    </TableCell>
                                    <TableCell class="max-w-xs w-full flex-wrap">
                                        {{ item.category_name || 'Sem categoria' }}
                                    </TableCell>
                                    <TableCell>{{ item.product_name }}</TableCell>
                                    <TableCell class="font-medium">
                                        {{ item.media_ponderada.toFixed(2) }}
                                    </TableCell>
                                    <TableCell>
                                        {{ formatPercent(item.percentual_individual) }}
                                    </TableCell>
                                    <TableCell>
                                        {{ formatPercent(item.percentual_acumulado) }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getClassBadgeVariant(item.classificacao)" class="font-semibold">
                                            {{ item.classificacao }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        {{ item.ranking }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge v-if="item.retirar_do_mix" variant="destructive">
                                            Sim
                                        </Badge>
                                        <span v-else class="text-muted-foreground">Não</span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="item.status.status === 'Ativo' ? 'default' : 'outline'">
                                            {{ item.status.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ item.status.motivo }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <Card v-else>
            <CardContent class="pt-6">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <AlertCircle class="mb-4 size-12 text-muted-foreground" />
                    <h3 class="mb-2 text-lg font-semibold">Nenhum resultado</h3>
                    <p class="text-sm text-muted-foreground">
                        Configure os parâmetros acima e execute a análise para ver os
                        resultados.
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
