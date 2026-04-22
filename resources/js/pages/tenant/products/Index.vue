<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import Heading from '@/components/Heading.vue';
import ListFiltersBar from '@/components/ListFiltersBar.vue';
import ListPagination from '@/components/ListPagination.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ProductRow = {
    id: string;
    name: string | null;
    slug: string | null;
    ean: string | null;
    status: 'draft' | 'published' | 'synced' | 'error';
    category: string | null;
};

defineProps<{
    products: Paginator<ProductRow>;
    filters: {
        search: string;
        status: string;
        category_id: string;
    };
    filter_options: {
        categories: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const productsIndexPath = ProductController.index.url().replace(/^\/\/[^/]+/, '');

setLayoutProps({
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.products.navigation'), href: productsIndexPath },
    ],
});
</script>

<template>
    <Head :title="t('app.tenant.products.title')" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading :title="t('app.tenant.products.title')" :description="t('app.tenant.products.description')" />
            <NewActionButton :href="ProductController.create.url()">
                {{ t('app.tenant.products.actions.new') }}
            </NewActionButton>
        </div>

        <ListFiltersBar
            :action="productsIndexPath"
            :clear-href="productsIndexPath"
            search-name="search"
            :search-value="filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
        >
            <select name="status" :value="filters.status" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                <option value="">{{ t('app.tenant.common.all') }}</option>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="synced">Synced</option>
                <option value="error">Error</option>
            </select>

            <select name="category_id" :value="filters.category_id" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                <option value="">{{ t('app.tenant.common.all') }}</option>
                <option v-for="category in filter_options.categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                </option>
            </select>
        </ListFiltersBar>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">Slug</th>
                        <th class="px-4 py-3 font-medium">EAN</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.category') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.status') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="products.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="6">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr v-for="product in products.data" :key="product.id" class="border-t border-sidebar-border/60 dark:border-sidebar-border">
                        <td class="px-4 py-3 font-medium">{{ product.name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ product.slug ?? '-' }}</td>
                        <td class="px-4 py-3">{{ product.ean ?? '-' }}</td>
                        <td class="px-4 py-3">{{ product.category ?? '-' }}</td>
                        <td class="px-4 py-3">{{ product.status }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="ProductController.edit.url(product.id)">
                                        {{ t('app.tenant.common.edit') }}
                                    </Link>
                                </Button>
                                <Button variant="destructive" size="sm" as-child>
                                    <Link :href="ProductController.destroy.url(product.id)" method="delete" as="button">
                                        {{ t('app.tenant.common.delete') }}
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ListPagination :meta="products" label="produto" />
    </div>
</template>
