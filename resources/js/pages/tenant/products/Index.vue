<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronDown, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import {
    ColumnActions,
    ColumnImage,
    ColumnLabel,
} from '@/components/table/columns';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';
import ColumnHeader from '@/components/table/columns/ColumnHeader.vue';
import ColumnStatusBadge from '@/components/table/columns/ColumnStatusBadge.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

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
const productsIndexPath = ProductController.index
    .url(props.subdomain)
    .replace(/^\/\/[^/]+/, '');
const categoryId = ref<string | null>(props.filters.category_id ?? null);
const categoryPopoverOpen = ref(false);

const categoryLabel = computed(() => {
    if (!categoryId.value) {
        return t('app.tenant.products.fields.category');
    }

    return t('app.tenant.products.fields.category') + ' ✓';
});

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
            <div class="flex items-center justify-end gap-2">
                <NewActionButton
                    :href="ProductController.create.url(props.subdomain)"
                >
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
                <select
                    name="status"
                    v-model="filters.status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="synced">Synced</option>
                    <option value="error">Error</option>
                </select>
                <input type="hidden" name="category_id" :value="categoryId ?? ''" />

                <Popover v-model:open="categoryPopoverOpen">
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted"
                            :class="categoryId ? 'border-primary/60 text-primary' : ''"
                        >
                            <SlidersHorizontal class="size-3.5 shrink-0" />
                            <span>{{ categoryLabel }}</span>
                            <button
                                v-if="categoryId"
                                type="button"
                                class="ml-1 rounded-sm opacity-60 hover:opacity-100"
                                @click.stop="categoryId = null"
                            >
                                <X class="size-3" />
                            </button>
                            <ChevronDown v-else class="size-3.5 shrink-0 opacity-50" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-170 p-4" align="start">
                        <p class="mb-3 text-sm font-medium">{{ t('app.tenant.products.form.sections.category') }}</p>
                        <CategoryCascadeSelect
                            v-model="categoryId"
                        />
                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-sm hover:bg-muted"
                                @click="categoryId = null; categoryPopoverOpen = false"
                            >
                                {{ t('app.tenant.common.clear_filters') }}
                            </button>
                            <button
                                type="submit"
                                class="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90"
                                @click="categoryPopoverOpen = false"
                            >
                                {{ t('app.tenant.common.filter') }}
                            </button>
                        </div>
                    </PopoverContent>
                </Popover>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="w-16 px-4 py-3 font-medium">
                            {{ t('app.tenant.products.fields.image') }}
                        </th>
                        <ColumnHeader field="name">{{
                            t('app.tenant.products.fields.name')
                        }}</ColumnHeader>
                        <ColumnHeader field="ean">{{
                            t('app.tenant.products.fields.ean')
                        }}</ColumnHeader>
                        <ColumnHeader field="category">{{
                            t('app.tenant.products.fields.category')
                        }}</ColumnHeader>
                        <ColumnHeader field="status">{{
                            t('app.tenant.products.fields.status')
                        }}</ColumnHeader>
                        <th class="text-right">
                            {{ t('app.tenant.common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.products.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="6">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="product in props.products.data"
                        :key="product.id"
                        class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <ColumnImage
                                :src="product.image_url"
                                :alt="product.name ?? 'Produto'"
                            />
                        </td>
                        <td class="px-4 py-3">
                            <ColumnLabel
                                :label="product.name ?? '-'"
                                :description="product.slug"
                            />
                        </td>
                        <td class="px-4 py-3">{{ product.ean ?? '-' }}</td>
                        <td class="px-4 py-3">{{ product.category ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="product.status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <ColumnActions
                                :edit-href="
                                    ProductController.edit.url({
                                        subdomain: props.subdomain,
                                        product: product.id,
                                    })
                                "
                                :delete-href="
                                    ProductController.destroy.url({
                                        subdomain: props.subdomain,
                                        product: product.id,
                                    })
                                "
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
