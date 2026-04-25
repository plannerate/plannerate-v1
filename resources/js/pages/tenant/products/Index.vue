<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnImage, ColumnLabel } from '@/components/table/columns';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ProductRow = {
    id: string;
    name: string | null;
    image_url: string | null;
    slug: string | null;
    ean: string | null;
    status: 'draft' | 'published' | 'synced' | 'error';
    category: string | null;
};

const props = defineProps<{
    subdomain: string;
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
const productsIndexPath = ProductController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.products.title'),
    title: t('app.tenant.products.title'),
    description: t('app.tenant.products.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.products.navigation'), href: productsIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="ProductController.create.url(props.subdomain)">
                    {{ t('app.tenant.products.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="props.products"
            label="produto"
            :action="productsIndexPath"
            :clear-href="productsIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
        >
            <template #filters>
                <select name="status" :value="props.filters.status" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="synced">Synced</option>
                    <option value="error">Error</option>
                </select>

                <select name="category_id" :value="props.filters.category_id" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option v-for="category in props.filter_options.categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.form.sections.image') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">EAN</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.category') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.status') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.products.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="6">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr v-for="product in props.products.data" :key="product.id" class="border-t border-sidebar-border/60 dark:border-sidebar-border">
                        <td class="px-4 py-3">
                            <ColumnImage :src="product.image_url" :alt="product.name ?? 'Produto'" />
                        </td>
                        <td class="px-4 py-3">
                            <ColumnLabel :label="product.name ?? '-'" :description="product.slug" />
                        </td>
                        <td class="px-4 py-3">{{ product.ean ?? '-' }}</td>
                        <td class="px-4 py-3">{{ product.category ?? '-' }}</td>
                        <td class="px-4 py-3">{{ product.status }}</td>
                        <td class="px-4 py-3 text-right">
                            <ColumnActions
                                :edit-href="ProductController.edit.url({ subdomain: props.subdomain, product: product.id })"
                                :delete-href="ProductController.destroy.url({ subdomain: props.subdomain, product: product.id })"
                                :delete-label="product.name ?? undefined"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
