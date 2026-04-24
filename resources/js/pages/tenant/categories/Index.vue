<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';
import ListPage from '@/components/ListPage.vue';
import { Badge } from '@/components/ui/badge';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import NewActionButton from '@/components/NewActionButton.vue';


type CategoryRow = {
    id: string;
    name: string;
    slug: string | null;
    status: 'draft' | 'published' | 'importer';
    codigo: number | null;
    is_placeholder: boolean;
};

const props = defineProps<{
    subdomain: string;
    categories: Paginator<CategoryRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const categoriesIndexPath = CategoryController.index
    .url(props.subdomain)
    .replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.categories.title'),
    title: t('app.tenant.categories.title'),
    description: t('app.tenant.categories.description'),
    createRoute: CategoryController.create.url(props.subdomain),
    createLabel: t('app.tenant.categories.actions.new'),
    headerActions: [
        { label: 'Importar', href: '#', variant: 'outline' },
        { label: 'Exportar', href: '#', variant: 'outline' },
    ],
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.tenant.categories.navigation'),
            href: categoriesIndexPath,
        },
    ],
});

const statusVariant = (status: CategoryRow['status']) => {
    if (status === 'published') return 'default';
    if (status === 'importer') return 'secondary';
    return 'outline';
};
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">

        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                <Button variant="outline" size="sm" type="button" class="px-3">
                    Importar
                </Button>
                <Button variant="outline" size="sm" type="button" class="px-3">
                    Exportar
                </Button>
                <NewActionButton :href="CategoryController.create.url(props.subdomain)">
                    {{ t('app.tenant.categories.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage :title="pageMeta.title" :description="pageMeta.description" :meta="props.categories" label="categoria"
            :action="categoriesIndexPath" :clear-href="categoriesIndexPath" :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')" :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')">
            <template #filters>
                <select name="status" :value="props.filters.status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">{{ t('app.tenant.categories.status_options.draft') }}</option>
                    <option value="published">{{ t('app.tenant.categories.status_options.published') }}</option>
                    <option value="importer">{{ t('app.tenant.categories.status_options.importer') }}</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.categories.fields.name') }}
                        </th>
                        <th class="px-4 py-3 font-medium">Slug</th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.categories.fields.codigo') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.categories.fields.status') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('app.tenant.categories.fields.is_placeholder') }}
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            {{ t('app.tenant.common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.categories.data.length === 0">
                        <td class="px-4 py-8 text-center text-muted-foreground" colspan="6">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr v-for="category in props.categories.data" :key="category.id"
                        class="border-t border-sidebar-border/60 transition-colors hover:bg-muted/20 dark:border-sidebar-border">
                        <td class="px-4 py-3 font-medium">{{ category.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ category.slug ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ category.codigo ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariant(category.status)" class="capitalize">
                                {{ category.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <Badge v-if="category.is_placeholder" variant="secondary">
                                {{ t('app.tenant.common.yes') }}
                            </Badge>
                            <span v-else class="text-muted-foreground">{{ t('app.tenant.common.no') }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <EditButton :href="CategoryController.edit.url({
                                    subdomain: props.subdomain,
                                    category: category.id,
                                })" />
                                <DeleteButton :href="CategoryController.destroy.url({
                                    subdomain: props.subdomain,
                                    category: category.id,
                                })" :label="category.name ?? undefined" require-confirm-word />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
