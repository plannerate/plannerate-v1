<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ChevronsUpDown, Grid2X2, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { BcgQuadrant, BcgResult, BcgSummary } from './bcg/types';

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
    results?: BcgResult[];
    summary?: BcgSummary | null;
    category?: { id: string; name: string } | null;
    category_id?: string;
    eans?: string[];
    filters?: Filters;
}

interface Props {
    initialData?: InitialData | null;
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    initialData: null,
    errors: () => ({}),
});

// ─── Defaults (3 meses) ───────────────────────────────────────────────────────

const nowDate = new Date();
const firstDayThreeMonthsAgo = new Date(nowDate.getFullYear(), nowDate.getMonth() - 3, 1);
const lastDayThisMonth = new Date(nowDate.getFullYear(), nowDate.getMonth() + 1, 0);
const fmt = (d: Date) => d.toISOString().slice(0, 10);

// ─── Form state ───────────────────────────────────────────────────────────────

const form = ref({
    category_id: props.initialData?.category_id ?? '',
    eans: (props.initialData?.eans ?? []).join('\n'),
    table_type: props.initialData?.filters?.table_type ?? 'monthly_summaries',
    month_from: props.initialData?.filters?.month_from ?? fmt(firstDayThreeMonthsAgo),
    month_to: props.initialData?.filters?.month_to ?? fmt(lastDayThisMonth),
    date_from: props.initialData?.filters?.date_from ?? fmt(firstDayThreeMonthsAgo),
    date_to: props.initialData?.filters?.date_to ?? fmt(lastDayThisMonth),
});

// ─── Resultados ───────────────────────────────────────────────────────────────

const results = computed(() => props.initialData?.results ?? []);
const hasResults = computed(() => results.value.length > 0);
const summary = computed(() => props.initialData?.summary ?? null);

// ─── Filtro + ordenação ───────────────────────────────────────────────────────

const search = ref('');
const sortBy = ref<keyof BcgResult>('quadrant');
const sortDir = ref<'asc' | 'desc'>('asc');
const activeQuadrant = ref<BcgQuadrant | null>(null);

const filteredResults = computed(() => {
    const q = search.value.toLowerCase();
    const rows = results.value.filter((r) => {
        const matchesSearch = q
            ? r.product_name.toLowerCase().includes(q) || r.ean.toLowerCase().includes(q)
            : true;
        const matchesQuadrant = activeQuadrant.value ? r.quadrant === activeQuadrant.value : true;
        return matchesSearch && matchesQuadrant;
    });

    rows.sort((a, b) => {
        const av = a[sortBy.value] as string | number;
        const bv = b[sortBy.value] as string | number;
        if (av === bv) return 0;
        const cmp = av < bv ? -1 : 1;
        return sortDir.value === 'asc' ? cmp : -cmp;
    });

    return rows;
});

const toggleSort = (col: keyof BcgResult) => {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = col;
        sortDir.value = 'asc';
    }
};

const SortIcon = (col: keyof BcgResult) => {
    if (sortBy.value !== col) return ChevronsUpDown;
    return sortDir.value === 'asc' ? ChevronUp : ChevronDown;
};

const toggleQuadrantFilter = (q: BcgQuadrant) => {
    activeQuadrant.value = activeQuadrant.value === q ? null : q;
};

// ─── Submit ───────────────────────────────────────────────────────────────────

const submit = () => {
    const eanList = form.value.eans
        .split(/[\n,]+/)
        .map((e) => e.trim())
        .filter(Boolean);

    const params: Record<string, string | string[]> = {
        table_type: form.value.table_type,
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

    router.get('/analysis/bcg', params as any, { preserveState: false });
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

const fmtPct = (n: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(
        n / 100,
    );

const fmtCurrency = (n: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

const quadrantLabel: Record<BcgQuadrant, string> = {
    star: 'Estrela',
    cash_cow: 'Vaca Leiteira',
    question_mark: 'Interrogação',
    dog: 'Abacaxi',
};

const quadrantConfig: Record<BcgQuadrant, { icon: string; color: string; badgeClass: string; borderClass: string }> = {
    star: {
        icon: '⭐',
        color: 'text-yellow-600 dark:text-yellow-400',
        badgeClass: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        borderClass: 'border-yellow-200 dark:border-yellow-900',
    },
    cash_cow: {
        icon: '🐄',
        color: 'text-green-600 dark:text-green-400',
        badgeClass: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        borderClass: 'border-green-200 dark:border-green-900',
    },
    question_mark: {
        icon: '❓',
        color: 'text-blue-600 dark:text-blue-400',
        badgeClass: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        borderClass: 'border-blue-200 dark:border-blue-900',
    },
    dog: {
        icon: '🐕',
        color: 'text-red-600 dark:text-red-400',
        badgeClass: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        borderClass: 'border-red-200 dark:border-red-900',
    },
};

const quadrants: BcgQuadrant[] = ['star', 'cash_cow', 'question_mark', 'dog'];
</script>

<template>
    <div class="space-y-6 p-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <Grid2X2 class="text-primary size-7" />
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Matriz BCG</h1>
                <p class="text-muted-foreground text-sm">Classificação de produtos por crescimento e participação de mercado</p>
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

                    <Button type="submit" class="w-full md:w-auto">Analisar</Button>
                </form>
            </CardContent>
        </Card>

        <!-- Quadrantes BCG -->
        <div v-if="hasResults" class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card
                v-for="q in quadrants"
                :key="q"
                class="cursor-pointer transition-shadow hover:shadow-md"
                :class="[quadrantConfig[q].borderClass, activeQuadrant === q ? 'ring-primary ring-2' : '']"
                @click="toggleQuadrantFilter(q)"
            >
                <CardContent class="pt-4">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">{{ quadrantConfig[q].icon }}</span>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase">{{ quadrantLabel[q] }}</p>
                            <p class="text-2xl font-bold" :class="quadrantConfig[q].color">
                                {{ summary?.[q] ?? results.filter((r) => r.quadrant === q).length }}
                            </p>
                        </div>
                    </div>
                    <p v-if="activeQuadrant === q" class="text-muted-foreground mt-1 text-xs">Clique para limpar filtro</p>
                </CardContent>
            </Card>
        </div>

        <!-- Tabela de resultados -->
        <Card v-if="hasResults">
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="text-base">
                    {{ filteredResults.length }} produto{{ filteredResults.length !== 1 ? 's' : '' }}
                    <span v-if="initialData?.category"> — {{ initialData.category.name }}</span>
                    <span v-if="activeQuadrant" class="text-muted-foreground text-sm font-normal">
                        ({{ quadrantLabel[activeQuadrant] }})
                    </span>
                </CardTitle>
                <div class="relative w-64">
                    <Search class="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                    <Input v-model="search" placeholder="Buscar produto ou EAN..." class="pl-8" />
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="cursor-pointer select-none" @click="toggleSort('product_name')">
                                <span class="flex items-center gap-1">
                                    Produto
                                    <component :is="SortIcon('product_name')" class="size-3" />
                                </span>
                            </TableHead>
                            <TableHead class="cursor-pointer select-none" @click="toggleSort('ean')">
                                <span class="flex items-center gap-1">
                                    EAN
                                    <component :is="SortIcon('ean')" class="size-3" />
                                </span>
                            </TableHead>
                            <TableHead class="cursor-pointer select-none" @click="toggleSort('quadrant')">
                                <span class="flex items-center gap-1">
                                    Quadrante
                                    <component :is="SortIcon('quadrant')" class="size-3" />
                                </span>
                            </TableHead>
                            <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('market_share')">
                                <span class="flex items-center justify-end gap-1">
                                    Share
                                    <component :is="SortIcon('market_share')" class="size-3" />
                                </span>
                            </TableHead>
                            <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('growth_rate')">
                                <span class="flex items-center justify-end gap-1">
                                    Crescimento
                                    <component :is="SortIcon('growth_rate')" class="size-3" />
                                </span>
                            </TableHead>
                            <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('total_value_current')">
                                <span class="flex items-center justify-end gap-1">
                                    Valor Atual
                                    <component :is="SortIcon('total_value_current')" class="size-3" />
                                </span>
                            </TableHead>
                            <TableHead class="text-right">Valor Anterior</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in filteredResults" :key="row.product_id">
                            <TableCell class="max-w-[200px] truncate font-medium" :title="row.product_name">
                                {{ row.product_name }}
                            </TableCell>
                            <TableCell class="text-muted-foreground font-mono text-xs">{{ row.ean }}</TableCell>
                            <TableCell>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="quadrantConfig[row.quadrant].badgeClass"
                                >
                                    {{ quadrantConfig[row.quadrant].icon }}
                                    {{ quadrantLabel[row.quadrant] }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{ fmtPct(row.market_share) }}</TableCell>
                            <TableCell class="text-right tabular-nums">
                                <span :class="row.growth_rate >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ row.growth_rate >= 0 ? '+' : '' }}{{ fmtPct(row.growth_rate) }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{ fmtCurrency(row.total_value_current) }}</TableCell>
                            <TableCell class="text-muted-foreground text-right tabular-nums">
                                {{ fmtCurrency(row.total_value_previous) }}
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="filteredResults.length === 0">
                            <TableCell colspan="7" class="text-muted-foreground py-8 text-center">
                                Nenhum produto encontrado
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Empty state -->
        <Card v-else>
            <CardContent class="flex flex-col items-center justify-center py-16">
                <Grid2X2 class="text-muted-foreground/30 mb-4 size-16" />
                <p class="text-muted-foreground text-sm">Nenhum resultado disponível. Ajuste os filtros e clique em Analisar.</p>
            </CardContent>
        </Card>
    </div>
</template>
