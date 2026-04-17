<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ChevronDown, ChevronUp, ChevronsUpDown, PackageSearch, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────

interface TargetStockResult {
    product_id: string;
    product_name: string;
    ean: string;
    classificacao: 'A' | 'B' | 'C';
    demanda_media: number;
    desvio_padrao: number;
    variabilidade: number;
    cobertura_dias: number;
    nivel_servico: number;
    z_score: number;
    estoque_seguranca: number;
    estoque_minimo: number;
    estoque_alvo: number;
    estoque_atual: number;
    permite_frentes: string;
    alerta_variabilidade: boolean;
}

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
    results: TargetStockResult[];
    abcSummary?: { total: number; class_a: number; class_b: number; class_c: number } | null;
    category?: { id: string; name: string } | null;
    category_id?: string;
    eans?: string[];
    filters: Filters;
    weights: { peso_qtde: number; peso_valor: number; peso_margem: number };
    cuts: { corte_a: number; corte_b: number };
    parameters: {
        period_type: string;
        nivel_servico_a: number;
        nivel_servico_b: number;
        nivel_servico_c: number;
        cobertura_dias_a: number;
        cobertura_dias_b: number;
        cobertura_dias_c: number;
    };
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
    period_type: props.initialData.parameters?.period_type ?? 'daily',
    nivel_servico_a: props.initialData.parameters?.nivel_servico_a ?? 0.7,
    nivel_servico_b: props.initialData.parameters?.nivel_servico_b ?? 0.8,
    nivel_servico_c: props.initialData.parameters?.nivel_servico_c ?? 0.9,
    cobertura_dias_a: props.initialData.parameters?.cobertura_dias_a ?? 2,
    cobertura_dias_b: props.initialData.parameters?.cobertura_dias_b ?? 5,
    cobertura_dias_c: props.initialData.parameters?.cobertura_dias_c ?? 7,
});

// ─── Resultados ───────────────────────────────────────────────────────────────

const results = computed(() => props.initialData.results ?? []);
const hasResults = computed(() => results.value.length > 0);
const abcSummary = computed(() => props.initialData.abcSummary ?? null);

// ─── Filtro + ordenação ───────────────────────────────────────────────────────

const search = ref('');
const sortBy = ref<keyof TargetStockResult>('classificacao');
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
        const av = a[sortBy.value];
        const bv = b[sortBy.value];
        if (av === bv) return 0;
        const cmp = av < bv ? -1 : 1;
        return sortDir.value === 'asc' ? cmp : -cmp;
    });
    return rows;
});

const toggleSort = (col: keyof TargetStockResult) => {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = col;
        sortDir.value = 'asc';
    }
};

const SortIcon = (col: keyof TargetStockResult) => {
    if (sortBy.value !== col) return ChevronsUpDown;
    return sortDir.value === 'asc' ? ChevronUp : ChevronDown;
};

// ─── Submit ───────────────────────────────────────────────────────────────────

const submit = () => {
    const eanList = form.value.eans
        .split(/[\n,]+/)
        .map((e) => e.trim())
        .filter(Boolean);

    const params: Record<string, unknown> = {
        table_type: form.value.table_type,
        peso_qtde: form.value.peso_qtde,
        peso_valor: form.value.peso_valor,
        peso_margem: form.value.peso_margem,
        corte_a: form.value.corte_a,
        corte_b: form.value.corte_b,
        period_type: form.value.period_type,
        nivel_servico_a: form.value.nivel_servico_a,
        nivel_servico_b: form.value.nivel_servico_b,
        nivel_servico_c: form.value.nivel_servico_c,
        cobertura_dias_a: form.value.cobertura_dias_a,
        cobertura_dias_b: form.value.cobertura_dias_b,
        cobertura_dias_c: form.value.cobertura_dias_c,
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

    router.get('/analysis/target-stock', params as unknown as FormData, { preserveState: false });
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

const classBadgeVariant = (c: 'A' | 'B' | 'C') =>
    c === 'A' ? 'default' : c === 'B' ? 'secondary' : 'outline';

const fmtNum = (n: number, decimals = 2) =>
    new Intl.NumberFormat('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(n);

const fmtPct = (n: number) =>
    new Intl.NumberFormat('pt-BR', { style: 'percent', minimumFractionDigits: 0 }).format(n);
</script>

<template>
    <Head title="Estoque Alvo" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <PackageSearch class="text-primary size-7" />
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Estoque Alvo</h1>
                    <p class="text-muted-foreground text-sm">Cálculo de estoque ideal por classe ABC</p>
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

                        <!-- Pesos ABC + cortes -->
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

                        <!-- Parâmetros de estoque por classe -->
                        <div class="rounded-md border p-4">
                            <p class="text-muted-foreground mb-3 text-sm font-medium">Parâmetros de Estoque por Classe</p>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Tipo de período -->
                                <div class="space-y-1.5 md:col-span-2">
                                    <Label>Tipo de período</Label>
                                    <select
                                        v-model="form.period_type"
                                        class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:outline-none md:w-48"
                                    >
                                        <option value="daily">Diário</option>
                                        <option value="monthly">Mensal</option>
                                    </select>
                                </div>

                                <!-- Nível de serviço -->
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide">Nível de Serviço</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="space-y-1">
                                            <Label class="text-xs">Classe A</Label>
                                            <Input v-model.number="form.nivel_servico_a" type="number" step="0.01" min="0" max="1" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Classe B</Label>
                                            <Input v-model.number="form.nivel_servico_b" type="number" step="0.01" min="0" max="1" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Classe C</Label>
                                            <Input v-model.number="form.nivel_servico_c" type="number" step="0.01" min="0" max="1" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Cobertura dias -->
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide">Cobertura (dias)</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="space-y-1">
                                            <Label class="text-xs">Classe A</Label>
                                            <Input v-model.number="form.cobertura_dias_a" type="number" min="1" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Classe B</Label>
                                            <Input v-model.number="form.cobertura_dias_b" type="number" min="1" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Classe C</Label>
                                            <Input v-model.number="form.cobertura_dias_c" type="number" min="1" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Button type="submit" class="w-full md:w-auto">Calcular Estoque Alvo</Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Sumário ABC -->
            <div v-if="abcSummary" class="grid grid-cols-4 gap-4">
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-muted-foreground text-xs font-medium uppercase">Total</p>
                        <p class="text-2xl font-bold">{{ abcSummary.total }}</p>
                    </CardContent>
                </Card>
                <Card class="border-green-200 dark:border-green-900">
                    <CardContent class="pt-4">
                        <p class="text-muted-foreground text-xs font-medium uppercase">Classe A</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ abcSummary.class_a }}</p>
                    </CardContent>
                </Card>
                <Card class="border-yellow-200 dark:border-yellow-900">
                    <CardContent class="pt-4">
                        <p class="text-muted-foreground text-xs font-medium uppercase">Classe B</p>
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ abcSummary.class_b }}</p>
                    </CardContent>
                </Card>
                <Card class="border-red-200 dark:border-red-900">
                    <CardContent class="pt-4">
                        <p class="text-muted-foreground text-xs font-medium uppercase">Classe C</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ abcSummary.class_c }}</p>
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
                                            { key: 'classificacao', label: 'Classe' },
                                            { key: 'ean', label: 'EAN' },
                                            { key: 'product_name', label: 'Produto' },
                                            { key: 'demanda_media', label: 'Demanda Média' },
                                            { key: 'desvio_padrao', label: 'Desvio Padrão' },
                                            { key: 'variabilidade', label: 'Variab.' },
                                            { key: 'cobertura_dias', label: 'Cobertura' },
                                            { key: 'nivel_servico', label: 'N. Serviço' },
                                            { key: 'estoque_seguranca', label: 'Est. Segurança' },
                                            { key: 'estoque_minimo', label: 'Est. Mínimo' },
                                            { key: 'estoque_alvo', label: 'Est. Alvo' },
                                            { key: 'estoque_atual', label: 'Est. Atual' },
                                        ]"
                                        :key="col.key"
                                        class="cursor-pointer select-none whitespace-nowrap"
                                        @click="toggleSort(col.key as keyof TargetStockResult)"
                                    >
                                        <span class="flex items-center gap-1">
                                            {{ col.label }}
                                            <component :is="SortIcon(col.key as keyof TargetStockResult)" class="size-3.5" />
                                        </span>
                                    </TableHead>
                                    <TableHead class="whitespace-nowrap">Alerta</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in filteredResults" :key="row.product_id">
                                    <TableCell>
                                        <Badge :variant="classBadgeVariant(row.classificacao)">{{ row.classificacao }}</Badge>
                                    </TableCell>
                                    <TableCell class="font-mono text-xs">{{ row.ean }}</TableCell>
                                    <TableCell class="max-w-xs truncate text-sm">{{ row.product_name }}</TableCell>
                                    <TableCell class="text-right text-sm">{{ fmtNum(row.demanda_media) }}</TableCell>
                                    <TableCell class="text-right text-sm">{{ fmtNum(row.desvio_padrao) }}</TableCell>
                                    <TableCell class="text-right text-sm">{{ fmtNum(row.variabilidade) }}%</TableCell>
                                    <TableCell class="text-right text-sm">{{ row.cobertura_dias }}d</TableCell>
                                    <TableCell class="text-right text-sm">{{ fmtPct(row.nivel_servico) }}</TableCell>
                                    <TableCell class="text-right text-sm font-medium">{{ row.estoque_seguranca }}</TableCell>
                                    <TableCell class="text-right text-sm font-medium">{{ row.estoque_minimo }}</TableCell>
                                    <TableCell class="text-right text-sm font-bold">{{ row.estoque_alvo }}</TableCell>
                                    <TableCell class="text-right text-sm">{{ row.estoque_atual }}</TableCell>
                                    <TableCell>
                                        <AlertTriangle
                                            v-if="row.alerta_variabilidade"
                                            class="size-4 text-yellow-500"
                                            title="Alta variabilidade"
                                        />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Estado vazio -->
            <Card v-else-if="!hasResults && (initialData.category_id || (initialData.eans ?? []).length > 0)">
                <CardContent class="py-12 text-center">
                    <PackageSearch class="text-muted-foreground mx-auto mb-3 size-12" />
                    <p class="text-muted-foreground">Nenhum resultado encontrado para os filtros selecionados.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
