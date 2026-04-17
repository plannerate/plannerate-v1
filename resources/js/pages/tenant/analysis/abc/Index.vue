<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { AbcResult } from '@/components/plannerate/analysis/abc/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, ChevronDown, ChevronUp, ChevronsUpDown, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────

interface Filters {
    table_type: string;
    client_id?: string;
    store_id?: string;
    date_from?: string;
    date_to?: string;
    month_from?: string;
    month_to?: string;
}

interface InitialData {
    results: AbcResult[];
    category?: { id: string; name: string } | null;
    category_id?: string;
    eans?: string[];
    filters: Filters;
    weights: { peso_qtde: number; peso_valor: number; peso_margem: number };
    cuts: { corte_a: number; corte_b: number };
}

interface Props {
    initialData: InitialData;
}

const props = defineProps<Props>();

// ─── Defaults (3 meses) ───────────────────────────────────────────────────────

const nowDate = new Date();
const firstDayThreeMonthsAgo = new Date(nowDate.getFullYear(), nowDate.getMonth() - 3, 1);
const lastDayThisMonth = new Date(nowDate.getFullYear(), nowDate.getMonth() + 1, 0);

const fmt = (d: Date) => d.toISOString().slice(0, 10);

// ─── Form state ───────────────────────────────────────────────────────────────

const form = ref({
    category_id: props.initialData.category_id ?? '',
    eans: (props.initialData.eans ?? []).join('\n'),
    table_type: props.initialData.filters?.table_type ?? 'monthly_summaries',
    month_from: props.initialData.filters?.month_from ?? fmt(firstDayThreeMonthsAgo),
    month_to: props.initialData.filters?.month_to ?? fmt(lastDayThisMonth),
    date_from: props.initialData.filters?.date_from ?? fmt(firstDayThreeMonthsAgo),
    date_to: props.initialData.filters?.date_to ?? fmt(lastDayThisMonth),
    peso_qtde: props.initialData.weights?.peso_qtde ?? 0.3,
    peso_valor: props.initialData.weights?.peso_valor ?? 0.3,
    peso_margem: props.initialData.weights?.peso_margem ?? 0.4,
    corte_a: props.initialData.cuts?.corte_a ?? 0.8,
    corte_b: props.initialData.cuts?.corte_b ?? 0.85,
});

// ─── Resultados ───────────────────────────────────────────────────────────────

const results = computed(() => props.initialData.results ?? []);
const hasResults = computed(() => results.value.length > 0);

// ─── Filtro + ordenação ───────────────────────────────────────────────────────

const search = ref('');
const sortBy = ref<keyof AbcResult>('ranking');
const sortDir = ref<'asc' | 'desc'>('asc');

const filteredResults = computed(() => {
    const q = search.value.toLowerCase();
    const rows = q
        ? results.value.filter(
              (r) =>
                  r.product_name.toLowerCase().includes(q) ||
                  r.ean.toLowerCase().includes(q) ||
                  r.classificacao.toLowerCase().includes(q),
          )
        : [...results.value];

    rows.sort((a, b) => {
        const av = a[sortBy.value] as string | number;
        const bv = b[sortBy.value] as string | number;
        if (av === bv) return 0;
        const cmp = av < bv ? -1 : 1;
        return sortDir.value === 'asc' ? cmp : -cmp;
    });
    return rows;
});

const toggleSort = (col: keyof AbcResult) => {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = col;
        sortDir.value = 'asc';
    }
};

const SortIcon = (col: keyof AbcResult) => {
    if (sortBy.value !== col) return ChevronsUpDown;
    return sortDir.value === 'asc' ? ChevronUp : ChevronDown;
};

// ─── Submit ───────────────────────────────────────────────────────────────────

const submit = () => {
    const eanList = form.value.eans
        .split(/[\n,]+/)
        .map((e) => e.trim())
        .filter(Boolean);

    const params: Record<string, any> = {
        table_type: form.value.table_type,
        peso_qtde: form.value.peso_qtde,
        peso_valor: form.value.peso_valor,
        peso_margem: form.value.peso_margem,
        corte_a: form.value.corte_a,
        corte_b: form.value.corte_b,
    };

    if (form.value.category_id) {
        params.category_id = form.value.category_id;
    } else if (eanList.length > 0) {
        params.eans = eanList;
    }

    if (form.value.table_type === 'monthly_summaries') {
        params.month_from = form.value.month_from;
        params.month_to = form.value.month_to;
    } else {
        params.date_from = form.value.date_from;
        params.date_to = form.value.date_to;
    }

    router.get('/analysis/abc', params as Record<string, any>, { preserveState: false });
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

const classBadgeVariant = (c: 'A' | 'B' | 'C') =>
    c === 'A' ? 'default' : c === 'B' ? 'secondary' : 'outline';

const fmtNum = (n: number, decimals = 2) =>
    new Intl.NumberFormat('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(n);

const fmtCurrency = (n: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

const summary = computed(() => ({
    a: results.value.filter((r) => r.classificacao === 'A').length,
    b: results.value.filter((r) => r.classificacao === 'B').length,
    c: results.value.filter((r) => r.classificacao === 'C').length,
}));
</script>

<template>
    <Head title="Análise ABC" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <BarChart3 class="text-primary size-7" />
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Análise ABC</h1>
                    <p class="text-muted-foreground text-sm">Classificação de produtos por importância relativa</p>
                </div>
            </div>

            <!-- Filtros -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Parâmetros da Análise</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <!-- Seleção: categoria ou EANs -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label>ID da Categoria</Label>
                                <Input v-model="form.category_id" placeholder="UUID da categoria" />
                            </div>
                            <div class="space-y-1.5">
                                <Label>EANs (um por linha)</Label>
                                <textarea
                                    v-model="form.eans"
                                    rows="3"
                                    placeholder="7891000315507&#10;7891000315514"
                                    class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:outline-none"
                                />
                            </div>
                        </div>

                        <!-- Tipo de tabela + período -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="space-y-1.5">
                                <Label>Fonte de dados</Label>
                                <select
                                    v-model="form.table_type"
                                    class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:outline-none"
                                >
                                    <option value="monthly_summaries">Resumo Mensal</option>
                                    <option value="sales">Vendas Diárias</option>
                                </select>
                            </div>

                            <template v-if="form.table_type === 'monthly_summaries'">
                                <div class="space-y-1.5">
                                    <Label>Mês inicial</Label>
                                    <Input v-model="form.month_from" type="month" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label>Mês final</Label>
                                    <Input v-model="form.month_to" type="month" />
                                </div>
                            </template>
                            <template v-else>
                                <div class="space-y-1.5">
                                    <Label>Data inicial</Label>
                                    <Input v-model="form.date_from" type="date" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label>Data final</Label>
                                    <Input v-model="form.date_to" type="date" />
                                </div>
                            </template>
                        </div>

                        <!-- Pesos + cortes (colapsável opcionalmente) -->
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                            <div class="space-y-1.5">
                                <Label>Peso Qtde</Label>
                                <Input v-model.number="form.peso_qtde" type="number" step="0.01" min="0" max="1" />
                            </div>
                            <div class="space-y-1.5">
                                <Label>Peso Valor</Label>
                                <Input v-model.number="form.peso_valor" type="number" step="0.01" min="0" max="1" />
                            </div>
                            <div class="space-y-1.5">
                                <Label>Peso Margem</Label>
                                <Input v-model.number="form.peso_margem" type="number" step="0.01" min="0" max="1" />
                            </div>
                            <div class="space-y-1.5">
                                <Label>Corte A (%)</Label>
                                <Input v-model.number="form.corte_a" type="number" step="0.01" min="0" max="1" />
                            </div>
                            <div class="space-y-1.5">
                                <Label>Corte B (%)</Label>
                                <Input v-model.number="form.corte_b" type="number" step="0.01" min="0" max="1" />
                            </div>
                        </div>

                        <Button type="submit" class="w-full md:w-auto">Analisar</Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Sumário -->
            <div v-if="hasResults" class="grid grid-cols-3 gap-4">
                <Card class="border-green-200 dark:border-green-900">
                    <CardContent class="pt-4">
                        <p class="text-muted-foreground text-xs font-medium uppercase">Classe A</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ summary.a }}</p>
                    </CardContent>
                </Card>
                <Card class="border-yellow-200 dark:border-yellow-900">
                    <CardContent class="pt-4">
                        <p class="text-muted-foreground text-xs font-medium uppercase">Classe B</p>
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ summary.b }}</p>
                    </CardContent>
                </Card>
                <Card class="border-red-200 dark:border-red-900">
                    <CardContent class="pt-4">
                        <p class="text-muted-foreground text-xs font-medium uppercase">Classe C</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ summary.c }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabela de resultados -->
            <Card v-if="hasResults">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">
                        {{ results.length }} produtos
                        <span v-if="initialData.category"> — {{ initialData.category.name }}</span>
                    </CardTitle>
                    <div class="relative w-64">
                        <Search class="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                        <Input v-model="search" placeholder="Buscar produto ou EAN..." class="pl-8" />
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead
                                        v-for="col in [
                                            { key: 'ranking', label: 'Rank' },
                                            { key: 'ean', label: 'EAN' },
                                            { key: 'product_name', label: 'Produto' },
                                            { key: 'classificacao', label: 'Classe' },
                                            { key: 'qtde', label: 'Qtde' },
                                            { key: 'valor', label: 'Valor' },
                                            { key: 'margem', label: 'Margem' },
                                            { key: 'percentual_acumulado', label: '% Acum.' },
                                        ]"
                                        :key="col.key"
                                        class="cursor-pointer select-none whitespace-nowrap"
                                        @click="toggleSort(col.key as keyof AbcResult)"
                                    >
                                        <span class="flex items-center gap-1">
                                            {{ col.label }}
                                            <component :is="SortIcon(col.key as keyof AbcResult)" class="size-3.5" />
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in filteredResults" :key="row.product_id">
                                    <TableCell class="text-muted-foreground text-xs">{{ row.ranking }}</TableCell>
                                    <TableCell class="font-mono text-xs">{{ row.ean }}</TableCell>
                                    <TableCell class="max-w-xs truncate text-sm">{{ row.product_name }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="classBadgeVariant(row.classificacao)">{{ row.classificacao }}</Badge>
                                    </TableCell>
                                    <TableCell class="text-right text-sm">{{ fmtNum(row.qtde, 0) }}</TableCell>
                                    <TableCell class="text-right text-sm">{{ fmtCurrency(row.valor) }}</TableCell>
                                    <TableCell class="text-right text-sm">{{ fmtCurrency(row.margem) }}</TableCell>
                                    <TableCell class="text-right text-sm">{{ fmtNum(row.percentual_acumulado) }}%</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Estado vazio -->
            <Card v-else-if="!hasResults && (initialData.category_id || (initialData.eans ?? []).length > 0)">
                <CardContent class="py-12 text-center">
                    <BarChart3 class="text-muted-foreground mx-auto mb-3 size-12" />
                    <p class="text-muted-foreground">Nenhum resultado encontrado para os filtros selecionados.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
