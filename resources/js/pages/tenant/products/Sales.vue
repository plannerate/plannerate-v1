<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import ProductIdentityHeader from './partials/ProductIdentityHeader.vue';
import SalesSummaryCards from './partials/SalesSummaryCards.vue';
import SalesTable from './partials/SalesTable.vue';
import type { ProductSalesPageProps } from './partials/types';

const props = defineProps<ProductSalesPageProps>();

const { t } = useT();

/** URL base da página de vendas deste produto (sem host) */
const salesPath = computed(() =>
    ProductController.sales.url({ product: props.product.id }).replace(/^\/\/[^/]+/, ''),
);

/** URL limpa (sem filtros) */
const clearPath = computed(() => salesPath.value);

/** Caminho da listagem de produtos */
const productsIndexPath = ProductController.index.url().replace(/^\/\/[^/]+/, '');

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

       <div class="px-5">
         <ProductIdentityHeader :product="product" :totals="totals" />
       </div>

        <SalesSummaryCards :totals="totals" />

        <SalesTable
            :sales="sales"
            :totals="totals"
            :filters="filters"
            :filter-options="filter_options"
            :action="salesPath"
            :clear-href="clearPath"
        />
    </AppLayout>
</template>
