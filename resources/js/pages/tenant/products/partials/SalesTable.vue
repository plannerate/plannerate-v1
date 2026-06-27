<script setup lang="ts">
import {
    BadgeDollarSign,
    CalendarDays,
    Package,
    Store,
} from 'lucide-vue-next';
import { ref } from 'vue';
import MonthRangeFilter from '@/components/filters/MonthRangeFilter.vue';
import ListPage from '@/components/ListPage.vue';
import ColumnHeader from '@/components/table/columns/ColumnHeader.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { useT } from '@/composables/useT';
import type { Paginator } from '@/types';
import { formatCurrency, formatDate, formatQuantity } from './formatters';
import type { SalesFilterOptions, SalesFilters, SalesTotals, SaleRow } from './types';

defineProps<{
    sales: Paginator<SaleRow>;
    totals: SalesTotals;
    filters: SalesFilters;
    filterOptions: SalesFilterOptions;
    action: string;
    clearHref: string;
}>();

const { t } = useT();

/** Referência à ListPage para submeter filtros */
const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);

/** Retorna classe CSS de badge para campo promoção */
function promotionBadgeClass(promo: string | null): string {
    return promo === 'S'
        ? 'inline-flex rounded-full border border-orange-200 bg-orange-100 px-2 py-0.5 text-[11px] font-medium text-orange-800 dark:border-orange-800/70 dark:bg-orange-900/30 dark:text-orange-300'
        : 'inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400';
}
</script>

<template>
    <!-- ── Tabela paginada com filtros ─────────────────────────────────── -->
    <ListPage
        ref="listPageRef"
        :meta="sales"
        label="venda"
        :action="action"
        :clear-href="clearHref"
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
                v-if="filterOptions.stores.length > 0"
                name="store_id"
                :value="filters.store_id"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                @change="listPageRef?.submitForm()"
            >
                <option value="">{{ t('app.tenant.common.all') }} {{ t('app.tenant.sales.fields.store').toLowerCase() }}</option>
                <option v-for="store in filterOptions.stores" :key="store.id" :value="store.id">
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
</template>
