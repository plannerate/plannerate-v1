<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    CalendarDays,
    ChevronLeft,
    Hash,
    Package,
    Percent,
    Receipt,
    ShoppingCart,
    Store,
    Tag,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import MonthRangeFilter from '@/components/filters/MonthRangeFilter.vue';
import ListPage from '@/components/ListPage.vue';
import ColumnHeader from '@/components/table/columns/ColumnHeader.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

/** Tipagem de um registro de venda paginado */
type SaleRow = {
    id: string;
    store: string | null;
    sale_date: string | null;
    promotion: string | null;
    total_sale_quantity: string | null;
    total_sale_value: string | null;
    acquisition_cost: string | null;
    sale_price: string | null;
    total_profit_margin: string | null;
    margem_contribuicao: string | null;
    extra_data: Record<string, unknown> | null;
};

/** Totalizadores calculados pelo backend */
type SalesTotals = {
    total_records: number;
    total_quantity: string;
    total_value: string;
    total_acquisition_cost: string;
    total_profit_margin: string;
    total_margem_contribuicao: string;
    avg_sale_price: string;
    promo_records: number;
    promo_quantity: string;
    promo_value: string;
    regular_quantity: string;
    regular_value: string;
};

const props = defineProps<{
    product: {
        id: string;
        name: string | null;
        ean: string | null;
        codigo_erp: string | null;
        image_url: string | null;
    };
    sales: Paginator<SaleRow>;
    totals: SalesTotals;
    filters: {
        sale_date_from: string;
        sale_date_to: string;
        promotion: string;
        store_id: string;
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();

/** Referência à ListPage para submeter filtros */
const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);

/** URL base da página de vendas deste produto */
const salesPath = computed(() =>
    ProductController.sales.url({ product: props.product.id }).replace(/^\/\/[^/]+/, ''),
);

/** URL limpa (sem filtros) */
const clearPath = computed(() => salesPath.value);

// ── Formatadores ──────────────────────────────────────────────────────────────

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const moneyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const quantityFormatter = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
});

/**
 * Formata uma string de data ISO para o formato pt-BR.
 * Retorna '-' para valores nulos ou inválidos.
 */
function formatDate(value: string | null): string {
    if (!value) return '-';
    const d = new Date(`${value}T00:00:00`);
    return Number.isNaN(d.getTime()) ? value : dateFormatter.format(d);
}

/**
 * Formata um valor numérico como moeda BRL.
 * Retorna '-' para valores nulos ou não numéricos.
 */
function formatCurrency(value: string | null | undefined): string {
    if (value == null || value === '') return '-';
    const n = Number(value);
    return Number.isFinite(n) ? moneyFormatter.format(n) : value;
}

/**
 * Formata uma quantidade com 3 casas decimais (pt-BR).
 * Retorna '-' para valores nulos ou não numéricos.
 */
function formatQuantity(value: string | null | undefined): string {
    if (value == null || value === '') return '-';
    const n = Number(value);
    return Number.isFinite(n) ? quantityFormatter.format(n) : value;
}

/**
 * Calcula a porcentagem de promoção em relação ao total de registros.
 * Retorna '0,0%' quando não há registros.
 */
const promoPercent = computed<string>(() => {
    if (!props.totals.total_records) return '0,0%';
    const pct = (props.totals.promo_records / props.totals.total_records) * 100;
    return pct.toFixed(1).replace('.', ',') + '%';
});

// ── Meta da página ────────────────────────────────────────────────────────────

const productsIndexPath = ProductController.index
    .url()
    .replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.products.sales.title'),
    title: props.product.name ?? props.product.codigo_erp ?? t('app.tenant.products.sales.title'),
    description: t('app.tenant.products.sales.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.tenant.products.navigation'),
            href: productsIndexPath,
        },
        {
            title: t('app.tenant.products.sales.navigation'),
            href: salesPath.value,
        },
    ],
});

// ── Cards do mini-dashboard ───────────────────────────────────────────────────

/** Define cada card da grade de indicadores */
type DashCard = {
    label: string;
    value: string;
    icon: typeof Package;
    color: string;
    bg: string;
};

const dashCards = computed<DashCard[]>(() => [
    {
        label: t('app.tenant.products.sales.dashboard.total_quantity'),
        value: formatQuantity(props.totals.total_quantity),
        icon: Package,
        color: 'text-blue-600 dark:text-blue-400',
        bg: 'bg-blue-50 dark:bg-blue-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.total_value'),
        value: formatCurrency(props.totals.total_value),
        icon: BadgeDollarSign,
        color: 'text-emerald-600 dark:text-emerald-400',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.avg_sale_price'),
        value: formatCurrency(props.totals.avg_sale_price),
        icon: Tag,
        color: 'text-violet-600 dark:text-violet-400',
        bg: 'bg-violet-50 dark:bg-violet-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.total_records'),
        value: props.totals.total_records.toLocaleString('pt-BR'),
        icon: Receipt,
        color: 'text-slate-600 dark:text-slate-400',
        bg: 'bg-slate-50 dark:bg-slate-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.promo_quantity'),
        value: formatQuantity(props.totals.promo_quantity),
        icon: Percent,
        color: 'text-orange-600 dark:text-orange-400',
        bg: 'bg-orange-50 dark:bg-orange-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.promo_value'),
        value: formatCurrency(props.totals.promo_value),
        icon: ShoppingCart,
        color: 'text-orange-600 dark:text-orange-400',
        bg: 'bg-orange-50 dark:bg-orange-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.regular_quantity'),
        value: formatQuantity(props.totals.regular_quantity),
        icon: Package,
        color: 'text-teal-600 dark:text-teal-400',
        bg: 'bg-teal-50 dark:bg-teal-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.regular_value'),
        value: formatCurrency(props.totals.regular_value),
        icon: BadgeDollarSign,
        color: 'text-teal-600 dark:text-teal-400',
        bg: 'bg-teal-50 dark:bg-teal-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.total_profit_margin'),
        value: formatCurrency(props.totals.total_profit_margin),
        icon: TrendingUp,
        color: 'text-indigo-600 dark:text-indigo-400',
        bg: 'bg-indigo-50 dark:bg-indigo-950/40',
    },
    {
        label: t('app.tenant.products.sales.dashboard.total_margem_contribuicao'),
        value: formatCurrency(props.totals.total_margem_contribuicao),
        icon: TrendingUp,
        color: 'text-pink-600 dark:text-pink-400',
        bg: 'bg-pink-50 dark:bg-pink-950/40',
    },
]);

/** Retorna classe CSS de badge para campo promoção */
function promotionBadgeClass(promo: string | null): string {
    return promo === 'S'
        ? 'inline-flex rounded-full border border-orange-200 bg-orange-100 px-2 py-0.5 text-[11px] font-medium text-orange-800 dark:border-orange-800/70 dark:bg-orange-900/30 dark:text-orange-300'
        : 'inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400';
}
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <!-- Botão voltar para lista de produtos -->
        <template #header-actions>
            <Link
                :href="productsIndexPath"
                class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted"
            >
                <ChevronLeft class="size-3.5 shrink-0" />
                {{ t('app.tenant.products.navigation') }}
            </Link>
        </template>

        <!-- ── Identificação do produto ─────────────────────────────────────── -->
        <div class="mb-6 flex items-center gap-4 rounded-xl border border-border bg-card p-4">
            <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name ?? ''"
                class="size-16 shrink-0 rounded-lg object-contain ring-1 ring-border"
            />
            <div
                v-else
                class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground ring-1 ring-border"
            >
                <Package class="size-7" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-lg font-semibold text-foreground">
                    {{ product.name ?? product.codigo_erp ?? '-' }}
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                    <span v-if="product.codigo_erp" class="inline-flex items-center gap-1">
                        <Hash class="size-3.5" /> {{ product.codigo_erp }}
                    </span>
                    <span v-if="product.ean" class="inline-flex items-center gap-1">
                        <Tag class="size-3.5" /> EAN {{ product.ean }}
                    </span>
                    <span
                        v-if="totals.total_records > 0"
                        class="inline-flex items-center gap-1 text-orange-600 dark:text-orange-400"
                    >
                        <Percent class="size-3.5" /> {{ promoPercent }}
                        {{ t('app.tenant.products.sales.dashboard.promo_records').toLowerCase() }}
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Mini-dashboard: grade de cards ─────────────────────────────── -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div
                v-for="card in dashCards"
                :key="card.label"
                class="flex items-start gap-3 rounded-xl border border-border bg-card p-4"
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

        <!-- ── Tabela paginada com filtros ─────────────────────────────────── -->
        <ListPage
            ref="listPageRef"
            :meta="sales"
            label="venda"
            :action="salesPath"
            :clear-href="clearPath"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
            :show-trashed-filter="false"
        >
            <template #filters>
                <!-- Filtro de período -->
                <MonthRangeFilter
                    :label="t('app.tenant.sales.fields.sale_date')"
                    start-name="sale_date_from"
                    end-name="sale_date_to"
                    :start-value="filters.sale_date_from"
                    :end-value="filters.sale_date_to"
                    placeholder="Selecionar mês/ano"
                    @complete="listPageRef?.submitForm()"
                />

                <!-- Filtro de promoção -->
                <select
                    name="promotion"
                    :value="filters.promotion"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    @change="listPageRef?.submitForm()"
                >
                    <option value="">{{ t('app.tenant.products.sales.filters.promotion_all') }}</option>
                    <option value="S">{{ t('app.tenant.products.sales.filters.promotion_yes') }}</option>
                    <option value="N">{{ t('app.tenant.products.sales.filters.promotion_no') }}</option>
                </select>

                <!-- Filtro de loja -->
                <select
                    v-if="filter_options.stores.length > 0"
                    name="store_id"
                    :value="filters.store_id"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    @change="listPageRef?.submitForm()"
                >
                    <option value="">{{ t('app.tenant.common.all') }} {{ t('app.tenant.sales.fields.store').toLowerCase() }}</option>
                    <option v-for="store in filter_options.stores" :key="store.id" :value="store.id">
                        {{ store.name }}
                    </option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead
                    class="sticky top-0 z-20 bg-background/95 text-left text-muted-foreground backdrop-blur supports-[backdrop-filter]:bg-background/80"
                >
                    <tr>
                        <ColumnHeader field="sale_date">
                            <span class="inline-flex items-center gap-1.5">
                                <CalendarDays class="size-3.5" />
                                {{ t('app.tenant.sales.fields.sale_date') }}
                            </span>
                        </ColumnHeader>
                        <ColumnHeader field="store">
                            <span class="inline-flex items-center gap-1.5">
                                <Store class="size-3.5" />
                                {{ t('app.tenant.sales.fields.store') }}
                            </span>
                        </ColumnHeader>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.sales.fields.promotion') }}
                        </th>
                        <ColumnHeader field="total_sale_quantity">
                            <span class="inline-flex items-center gap-1.5">
                                <Package class="size-3.5" />
                                {{ t('app.tenant.sales.fields.total_sale_quantity') }}
                            </span>
                        </ColumnHeader>
                        <ColumnHeader field="total_sale_value">
                            <span class="inline-flex items-center gap-1.5">
                                <BadgeDollarSign class="size-3.5" />
                                {{ t('app.tenant.sales.fields.total_sale_value') }}
                            </span>
                        </ColumnHeader>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.sales.fields.acquisition_cost') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.sales.fields.sale_price') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.sales.fields.total_profit_margin') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.sales.fields.margem_contribuicao') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="!sales">
                        <TableLoadingSkeleton :columns="9" :rows="8" />
                    </template>
                    <tr v-else-if="sales.data.length === 0">
                        <td class="px-4 py-10 text-center text-muted-foreground" colspan="9">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="sale in sales.data"
                        :key="sale.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <!-- Data -->
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatDate(sale.sale_date) }}
                        </td>
                        <!-- Loja -->
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ sale.store ?? '-' }}
                        </td>
                        <!-- Promoção -->
                        <td class="px-4 py-3">
                            <span :class="promotionBadgeClass(sale.promotion)">
                                {{ sale.promotion === 'S' ? 'Promoção' : 'Regular' }}
                            </span>
                        </td>
                        <!-- Quantidade -->
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatQuantity(sale.total_sale_quantity) }}
                        </td>
                        <!-- Valor total -->
                        <td class="px-4 py-3 font-medium tabular-nums text-foreground">
                            {{ formatCurrency(sale.total_sale_value) }}
                        </td>
                        <!-- Custo de aquisição -->
                        <td class="px-4 py-3 tabular-nums text-muted-foreground">
                            {{ formatCurrency(sale.acquisition_cost) }}
                        </td>
                        <!-- Preço de venda -->
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatCurrency(sale.sale_price) }}
                        </td>
                        <!-- Margem de lucro -->
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatCurrency(sale.total_profit_margin) }}
                        </td>
                        <!-- Margem de contribuição -->
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatCurrency(sale.margem_contribuicao) }}
                        </td>
                    </tr>

                    <!-- Linha de totais fixada na base da tabela -->
                    <tr
                        v-if="sales.data.length > 0"
                        class="border-t-2 border-border bg-muted/60 font-semibold dark:bg-muted/20"
                    >
                        <td class="px-4 py-3 text-xs uppercase tracking-wide text-muted-foreground" colspan="3">
                            Totais (filtro atual)
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatQuantity(totals.total_quantity) }}
                        </td>
                        <td class="px-4 py-3 tabular-nums text-foreground">
                            {{ formatCurrency(totals.total_value) }}
                        </td>
                        <td class="px-4 py-3 tabular-nums text-muted-foreground">
                            {{ formatCurrency(totals.total_acquisition_cost) }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">—</td>
                        <td class="px-4 py-3 tabular-nums text-muted-foreground">
                            {{ formatCurrency(totals.total_profit_margin) }}
                        </td>
                        <td class="px-4 py-3 tabular-nums text-muted-foreground">
                            {{ formatCurrency(totals.total_margem_contribuicao) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
