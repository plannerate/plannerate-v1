<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ImageDown, ShoppingCart, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import PlanLimitAlert from '@/components/PlanLimitAlert.vue';
import {
    ColumnActions,
    ColumnImage,
    ColumnLabel,
} from '@/components/table/columns';
import ColumnHeader from '@/components/table/columns/ColumnHeader.vue';
import ColumnStatusBadge from '@/components/table/columns/ColumnStatusBadge.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ProductRow = {
    id: string;
    name: string | null;
    image_url: string | null;
    ean: string | null;
    codigo_erp: string | null;
    status: 'draft' | 'published' | 'synced' | 'error';
    category: string | null;
    stores: string[];
    dimensions: {
        width: string | number | null;
        height: string | number | null;
        depth: string | number | null;
        weight: string | number | null;
        unit: string | null;
    };
    current_stock: string | number | null;
    last_purchase_date: string | null;
    created_at: string;
    sync_at: string | null;
};

const props = defineProps<{
    products?: Paginator<ProductRow>;
    filters: {
        search: string;
        status: string;
        category_id: string;
        grouping: string;
        trashed: 'without' | 'only' | 'with';
    };
    filter_options: {
        categories: Array<{ id: string; name: string }>;
        groupings: string[];
    };
    can: {
        create: boolean;
        limit_reached: boolean;
        limit_message: string | null;
        upgrade_url: string | null;
    };
}>();

const { t } = useT();
const { meta: productsMeta, rows: productsRows, loading: productsLoading } = useDeferredPaginator(() => props.products, 10);
const productsIndexPath = ProductController.index
    .url()
    .replace(/^\/\/[^/]+/, '');
const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);
const categoryId = ref<string | null>(props.filters.category_id ?? null);
const categoryPopoverOpen = ref(false);

watch(categoryId, (value, prev) => {
    if (value !== prev) {
        categoryPopoverOpen.value = false;
        nextTick(() => listPageRef.value?.submitForm());
    }
});

const categoryLabel = computed(() => {
    if (!categoryId.value) {
        return t('app.tenant.products.fields.category');
    }

    return t('app.tenant.products.fields.category') + ' ✓';
});

const formatProductDimensions = (product: ProductRow): string | null => {
    const { width, height, depth, unit } = product.dimensions;
    const dimensions = [width, height, depth]
        .filter((value): value is string | number => value !== null && String(value).trim() !== '')
        .map((value) => String(value).trim());

    if (dimensions.length === 0) {
        return null;
    }

    const unitSuffix = unit && unit.trim() !== '' ? ` ${unit.trim()}` : '';

    return `${t('app.tenant.products.form.labels.dimensions')} ${dimensions.join('x')}${unitSuffix}`;
};

const formatStockAndLastPurchase = (product: ProductRow): string | null => {
    const currentStock = product.current_stock !== null && String(product.current_stock).trim() !== ''
        ? `${t('app.tenant.products.form.labels.stock')} ${product.current_stock}`
        : null;
    const lastPurchase = product.last_purchase_date
        ? `${t('app.tenant.products.form.labels.last_purchase')} ${new Date(product.last_purchase_date).toLocaleDateString('pt-BR')}`
        : null;

    return [currentStock, lastPurchase].filter(Boolean).join(' | ') || null;
};

const formatSyncDate = (product: ProductRow): string | null => {
    return product.sync_at ? `${t('app.tenant.products.form.labels.sync_date')} ${new Date(product.sync_at).toLocaleDateString('pt-BR')}` : null;
};

const isUpdatingImages = ref(false);

const pageEans = computed(() =>
    productsRows.value
        .map((p) => p.ean)
        .filter((ean): ean is string => ean !== null && ean.trim() !== ''),
);

function updateImages(): void {
    if (pageEans.value.length === 0 || isUpdatingImages.value) {
        return;
    }

    isUpdatingImages.value = true;
    router.visit(
        ProductController.updateImages.url(),
        {
            method: 'post',
            data: { eans: pageEans.value },
            onFinish: () => {
 isUpdatingImages.value = false; 
},
        },
    );
}

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.products.title'),
    title: t('app.tenant.products.title'),
    description: t('app.tenant.products.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        { title: t('app.tenant.products.navigation'), href: productsIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">

        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="button" :disabled="isUpdatingImages || pageEans.length === 0 || productsLoading"
                    class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                    @click="updateImages">
                    <ImageDown class="size-3.5 shrink-0" :class="{ 'animate-pulse': isUpdatingImages }" />
                    {{ isUpdatingImages ? 'Enviando...' : 'Atualizar imagens' }}
                </button>
                <NewActionButton v-if="can.create" :href="ProductController.create.url()">
                    {{ t('app.tenant.products.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <PlanLimitAlert v-if="can.limit_reached" :message="can.limit_message!" :upgrade-url="can.upgrade_url" />

        <ListPage ref="listPageRef" :meta="productsMeta" label="produto" :action="productsIndexPath" :clear-href="productsIndexPath"
            :search-value="props.filters.search" :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')" :clear-label="t('app.tenant.common.clear_filters')"
            :trashed-value="props.filters.trashed">
            <template #filters>
                <select name="status" :value="props.filters.status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">{{ t('app.tenant.products.status_options.draft') }}</option>
                    <option value="published">{{ t('app.tenant.products.status_options.published') }}</option>
                    <option value="synced">{{ t('app.tenant.products.status_options.synced') }}</option>
                    <option value="error">{{ t('app.tenant.products.status_options.error') }}</option>
                </select>
                <select
                    v-if="filter_options.groupings.length > 0"
                    name="grouping"
                    :value="props.filters.grouping"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">Agrupamento</option>
                    <option v-for="g in filter_options.groupings" :key="g" :value="g">{{ g }}</option>
                </select>
                <input type="hidden" name="category_id" :value="categoryId ?? ''" />

                <Popover v-model:open="categoryPopoverOpen">
                    <PopoverTrigger as-child>
                        <button type="button"
                            class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted"
                            :class="categoryId ? 'border-primary/60 text-primary' : ''">
                            <SlidersHorizontal class="size-3.5 shrink-0" />
                            <span>{{ categoryLabel }}</span>
                            <button v-if="categoryId" type="button" class="ml-1 rounded-sm opacity-60 hover:opacity-100"
                                @click.stop="categoryId = null">
                                <X class="size-3" />
                            </button>
                            <ChevronDown v-else class="size-3.5 shrink-0 opacity-50" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[90vw] max-w-170 p-4" align="start">
                        <p class="mb-3 text-sm font-medium">{{ t('app.tenant.products.form.sections.category') }}</p>
                        <CategoryCascadeSelect v-model="categoryId" />
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="rounded-md px-3 py-1.5 text-sm hover:bg-muted"
                                @click="categoryId = null; categoryPopoverOpen = false">
                                {{ t('app.tenant.common.clear_filters') }}
                            </button>
                            <button type="submit"
                                class="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90"
                                @click="categoryPopoverOpen = false">
                                {{ t('app.tenant.common.filter') }}
                            </button>
                        </div>
                    </PopoverContent>
                </Popover>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="w-16 whitespace-nowrap px-4 py-3 text-left font-medium">
                            {{ t('app.tenant.products.fields.image') }}
                        </th>
                        <ColumnHeader field="codigo_erp">{{
                            t('app.tenant.products.fields.codigo_erp')
                        }}</ColumnHeader>
                        <ColumnHeader field="ean">{{
                            t('app.tenant.products.fields.ean')
                            }}</ColumnHeader>
                        <ColumnHeader field="name">{{
                            t('app.tenant.products.fields.name')
                            }}</ColumnHeader>
                        <ColumnHeader field="status">{{
                            t('app.tenant.products.fields.status')
                            }}</ColumnHeader>
                        <ColumnHeader field="created_at">{{
                            t('app.tenant.products.fields.created_at')
                            }}</ColumnHeader>
                        <th class="whitespace-nowrap px-4 py-3 text-left font-medium">
                            {{ t('app.tenant.common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="productsLoading">
                        <TableLoadingSkeleton :columns="7" :rows="6" />
                    </template>
                    <tr v-else-if="productsRows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="8">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr v-for="product in productsRows" :key="product.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border">
                        <td class="px-4 py-3">
                            <ColumnImage :src="product.image_url" :alt="product.name ?? 'Produto'" />
                        </td>
                        <td class="px-4 py-3">
                            <ColumnLabel :label="product.codigo_erp ?? '-'" />
                        </td>
                        <td class="px-4 py-3">
                            {{ product.ean ?? '-' }}

                        </td>
                        <td class="px-4 py-3">
                            <ColumnLabel :label="product.name ?? '-'"
                                :description="[ formatStockAndLastPurchase(product), formatProductDimensions(product)].filter(Boolean).join(' • ') || null" />
                        </td>
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="product.status" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-1">
                                <span> {{ new Date(product.created_at).toLocaleDateString('pt-BR') }}</span>
                                <span class="text-muted-foreground">{{ formatSyncDate(product) }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <ColumnActions
                                :edit-href="ProductController.edit.url({ product: product.id })"
                                :delete-href="ProductController.destroy.url({ product: product.id })"
                                :delete-label="product.name ?? undefined"
                                :require-confirm-word="true"
                            >
                                <!-- Link para o mini-dashboard de vendas do produto -->
                                <Link
                                    :href="ProductController.sales.url({ product: product.id })"
                                    class="inline-flex items-center justify-center rounded-md border border-border bg-background p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    :title="t('app.tenant.products.sales.navigation')"
                                >
                                    <ShoppingCart class="size-3.5" />
                                </Link>
                            </ColumnActions>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
