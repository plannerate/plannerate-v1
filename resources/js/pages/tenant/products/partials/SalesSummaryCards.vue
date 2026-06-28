<script setup lang="ts">
import {
    BadgeCheck,
    BadgeDollarSign,
    Coins,
    Package,
    Percent,
    Receipt,
    Tag,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';
import { formatCurrency, formatPercent, formatQuantity } from './formatters';
import type { SalesTotals } from './types';

const props = defineProps<{
    totals: SalesTotals;
}>();

const { t } = useT();

/**
 * Métricas exibidas nos cards. Todas as fórmulas (preço médio, custo médio, lucro
 * bruto, percentuais) são calculadas no backend (SalesSummary) — aqui apenas lemos
 * os valores prontos e os repassamos aos formatadores.
 */
const metrics = computed(() => ({
    totalRecords: props.totals.total_records,
    quantity: Number(props.totals.total_quantity) || 0,
    revenue: Number(props.totals.total_value) || 0,
    cost: Number(props.totals.total_acquisition_cost) || 0,
    netMargin: Number(props.totals.total_margem_contribuicao) || 0,
    avgPrice: props.totals.avg_price,
    avgCost: props.totals.avg_cost,
    avgMargin: props.totals.avg_margin,
    grossProfitUnit: props.totals.gross_profit_unit,
    grossProfitTotal: props.totals.gross_profit_total,
    grossMarginPct: props.totals.gross_margin_pct,
    netMarginPct: props.totals.net_margin_pct,
}));

/** Define cada card da grade de indicadores */
type DashCard = {
    label: string;
    value: string;
    icon: typeof Package;
    color: string;
    bg: string;
};

/**
 * Cards do mini-dashboard, espelhando os mesmos indicadores do card lateral:
 * Resumo de Vendas → Custo e Lucro Bruto → Margem Líquida.
 */
const dashCards = computed<DashCard[]>(() => [
    // ── Resumo de Vendas ──
    {
        label: t('app.tenant.products.sales.dashboard.total_records'),
        value: metrics.value.totalRecords.toLocaleString('pt-BR'),
        icon: Receipt,
        color: 'text-slate-600 dark:text-slate-400',
        bg: 'bg-slate-50 dark:bg-slate-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.total_quantity'),
        value: formatQuantity(metrics.value.quantity),
        icon: Package,
        color: 'text-blue-600 dark:text-blue-400',
        bg: 'bg-blue-50 dark:bg-blue-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.total_value'),
        value: formatCurrency(metrics.value.revenue),
        icon: BadgeDollarSign,
        color: 'text-emerald-600 dark:text-emerald-400',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.avg_sale_price'),
        value: formatCurrency(metrics.value.avgPrice),
        icon: Tag,
        color: 'text-violet-600 dark:text-violet-400',
        bg: 'bg-violet-50 dark:bg-violet-950/40',
    },
    // ── Custo e Lucro Bruto ──
    {
        label: t('app.tenant.products.sales.dashboard.avg_cost_unit'),
        value: formatCurrency(metrics.value.avgCost),
        icon: Coins,
        color: 'text-amber-600 dark:text-amber-400',
        bg: 'bg-amber-50 dark:bg-amber-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.total_cost'),
        value: formatCurrency(metrics.value.cost),
        icon: Wallet,
        color: 'text-amber-600 dark:text-amber-400',
        bg: 'bg-amber-50 dark:bg-amber-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.gross_profit_unit'),
        value: formatCurrency(metrics.value.grossProfitUnit),
        icon: TrendingUp,
        color: 'text-green-600 dark:text-green-400',
        bg: 'bg-green-50 dark:bg-green-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.gross_profit_total'),
        value: formatCurrency(metrics.value.grossProfitTotal),
        icon: TrendingUp,
        color: 'text-green-600 dark:text-green-400',
        bg: 'bg-green-50 dark:bg-green-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.gross_margin'),
        value: formatPercent(metrics.value.grossMarginPct),
        icon: Percent,
        color: 'text-purple-600 dark:text-purple-400',
        bg: 'bg-purple-50 dark:bg-purple-950/40',
    },
    // ── Margem Líquida ──
    {
        label: t('app.tenant.products.sales.dashboard.net_margin_unit'),
        value: formatCurrency(metrics.value.avgMargin),
        icon: BadgeCheck,
        color: 'text-teal-600 dark:text-teal-400',
        bg: 'bg-teal-50 dark:bg-teal-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.net_margin_total'),
        value: formatCurrency(metrics.value.netMargin),
        icon: BadgeCheck,
        color: 'text-teal-600 dark:text-teal-400',
        bg: 'bg-teal-50 dark:bg-teal-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.net_margin_percentage'),
        value: formatPercent(metrics.value.netMarginPct),
        icon: Percent,
        color: 'text-teal-600 dark:text-teal-400',
        bg: 'bg-teal-50 dark:bg-teal-950/40',
    },
]);
</script>

<template>
    <!-- ── Mini-dashboard: grade de 12 colunas, cada card ocupa 2 colunas ── -->
    <div class="mb-6 grid grid-cols-12 gap-3 px-5">
        <div
            v-for="card in dashCards"
            :key="card.label"
            class="col-span-6 flex items-start gap-3 rounded-xl border border-border bg-card p-4 sm:col-span-4 lg:col-span-2"
        >
            <div :class="['flex size-9 shrink-0 items-center justify-center rounded-lg', card.bg]">
                <component :is="card.icon" :class="['size-4.5', card.color]" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-xs text-muted-foreground">{{ card.label }}</p>
                <p class="mt-0.5 truncate text-sm font-semibold text-foreground">{{ card.value }}</p>
            </div>
        </div>
    </div>
</template>
