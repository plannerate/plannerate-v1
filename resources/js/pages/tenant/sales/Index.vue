<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SaleController from '@/actions/App/Http/Controllers/Tenant/SaleController';
import MonthRangeFilter from '@/components/filters/MonthRangeFilter.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import PlanLimitAlert from '@/components/PlanLimitAlert.vue';
import { ColumnActions, ColumnLabel } from '@/components/table/columns';
import ColumnHeader from '@/components/table/columns/ColumnHeader.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type SaleRow = {
    id: string;
    store: string | null;
    ean: string | null;
    codigo_erp: string | null;
    sale_date: string | null;
    promotion: string | null;
    total_sale_quantity: string | null;
    total_sale_value: string | null;
};

const props = defineProps<{
    sales?: Paginator<SaleRow>;
    filters: {
        search: string;
        store_id: string;
        sale_date_from: string;
        sale_date_to: string;
        trashed: 'without' | 'only' | 'with';
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
    can: {
        create: boolean;
        limit_reached: boolean;
        limit_message: string | null;
        upgrade_url: string | null;
    };
}>();

const { t } = useT();
const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);
const salesIndexPath = SaleController.index
    .url()
    .replace(/^\/\/[^/]+/, '');
const salesCreatePath = SaleController.create
    .url()
    .replace(/^\/\/[^/]+/, '');
const loadingSalesMeta: Omit<Paginator<SaleRow>, 'data'> = {
    links: [],
    from: null,
    to: null,
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 10,
};
const salesMeta = computed(() => props.sales ?? loadingSalesMeta);
const salesData = computed(() => props.sales?.data ?? []);

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

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const parsedDate = new Date(`${value}T00:00:00`);

    if (Number.isNaN(parsedDate.getTime())) {
        return value;
    }

    return dateFormatter.format(parsedDate);
}

function formatCurrency(value: string | null): string {
    if (!value) {
        return '-';
    }

    const parsedValue = Number(value);

    return Number.isFinite(parsedValue)
        ? moneyFormatter.format(parsedValue)
        : value;
}

function formatQuantity(value: string | null): string {
    if (!value) {
        return '-';
    }

    const parsedValue = Number(value);

    return Number.isFinite(parsedValue)
        ? quantityFormatter.format(parsedValue)
        : value;
}

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.sales.title'),
    title: t('app.tenant.sales.title'),
    description: t('app.tenant.sales.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        { title: t('app.tenant.sales.navigation'), href: salesIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton v-if="can.create" :href="salesCreatePath">
                    {{ t('app.tenant.sales.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <PlanLimitAlert v-if="can.limit_reached" :message="can.limit_message!" :upgrade-url="can.upgrade_url" />

        <ListPage
            ref="listPageRef"
            :meta="salesMeta"
            label="venda"
            :action="salesIndexPath"
            :clear-href="salesIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
            :trashed-value="props.filters.trashed"
        >
            <template #filters>
                <MonthRangeFilter
                    :label="t('app.tenant.sales.fields.sale_date')"
                    start-name="sale_date_from"
                    end-name="sale_date_to"
                    :start-value="props.filters.sale_date_from"
                    :end-value="props.filters.sale_date_to"
                    placeholder="Selecionar mês/ano"
                    @complete="listPageRef?.submitForm()"
                />
                <select
                    name="store_id"
                    :value="props.filters.store_id"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option
                        v-for="store in props.filter_options.stores"
                        :key="store.id"
                        :value="store.id"
                    >
                        {{ store.name }}
                    </option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead
                    class="sticky top-0 z-20 bg-background/95 text-left text-muted-foreground backdrop-blur supports-[backdrop-filter]:bg-background/80"
                >
                    <tr>
                        <ColumnHeader field="codigo_erp">
                            {{ t('app.tenant.sales.fields.codigo_erp') }}
                        </ColumnHeader>
                        <ColumnHeader field="ean">
                            {{ t('app.tenant.sales.fields.ean') }}
                        </ColumnHeader>
                        <ColumnHeader field="store">
                            {{ t('app.tenant.sales.fields.store') }}
                        </ColumnHeader>
                        <ColumnHeader field="sale_date">
                            {{ t('app.tenant.sales.fields.sale_date') }}
                        </ColumnHeader>
                        <ColumnHeader field="total_sale_quantity">
                            {{ t('app.tenant.sales.fields.total_sale_quantity') }}
                        </ColumnHeader>
                        <ColumnHeader field="total_sale_value">
                            {{ t('app.tenant.sales.fields.total_sale_value') }}
                        </ColumnHeader>
                        <th class="px-4 py-3  font-medium">
                            {{ t('app.tenant.common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="!props.sales">
                        <TableLoadingSkeleton :columns="7" :rows="6" />
                    </template>
                    <tr v-else-if="salesData.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="7">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="sale in salesData"
                        :key="sale.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <div class="space-y-1">
                                <ColumnLabel :label="sale.codigo_erp ?? '-'" />
                                <span
                                    v-if="sale.promotion"
                                    class="inline-flex max-w-full truncate rounded-full border border-emerald-200 bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:border-emerald-800/70 dark:bg-emerald-900/30 dark:text-emerald-300"
                                    :title="sale.promotion"
                                >
                                    Promo: {{ sale.promotion }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-muted-foreground">
                            {{ sale.ean ?? '-' }}
                        </td>
                        <td class="px-4 py-3">{{ sale.store ?? '-' }}</td>
                        <td class="px-4 py-3">
                            {{ formatDate(sale.sale_date) }}
                        </td>
                        <td class="px-4 py-3">
                            {{ formatQuantity(sale.total_sale_quantity) }}
                        </td>
                        <td class="px-4 py-3 font-medium text-foreground">
                            {{ formatCurrency(sale.total_sale_value) }}
                        </td>
                        <td class="px-4 py-3 ">
                            <ColumnActions
                                :edit-href="
                                    SaleController.edit
                                        .url({
                                            sale: sale.id,
                                        })
                                        .replace(/^\/\/[^/]+/, '')
                                "
                                :delete-href="
                                    SaleController.destroy
                                        .url({
                                            sale: sale.id,
                                        })
                                        .replace(/^\/\/[^/]+/, '')
                                "
                                :delete-label="sale.codigo_erp ?? undefined"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
