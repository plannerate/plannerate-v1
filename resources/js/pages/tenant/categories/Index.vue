<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';
import ImportFileButton from '@/components/imports/ImportFileButton.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { ColumnActions, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type CategoryRow = {
    id: string;
    name: string;
    full_path: string | null;
    level_name: string | null;
    slug: string | null;
    status: 'draft' | 'published' | 'importer';
    codigo: number | null;
    is_placeholder: boolean;
};

const props = defineProps<{
    subdomain: string;
    categories?: Paginator<CategoryRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const { meta: categoriesMeta, rows: categoriesRows, loading: categoriesLoading } = useDeferredPaginator(() => props.categories, 10);
const categoriesIndexPath = CategoryController.index
    .url(props.subdomain)
    .replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.categories.title'),
    title: t('app.tenant.categories.title'),
    description: t('app.tenant.categories.description'),
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
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="pill-sm" as-child>
                    <a :href="CategoryController.exportTemplate.url(props.subdomain)">
                        {{ t('app.tenant.categories.actions.download_template') }}
                    </a>
                </Button>
                <Button variant="outline" size="pill-sm" as-child>
                    <a :href="CategoryController.exportData.url(props.subdomain)">
                        {{ t('app.tenant.categories.actions.export_data') }}
                    </a>
                </Button>
                <ImportFileButton
                    :action="CategoryController.importMethod.url(props.subdomain)"
                    :button-label="t('app.tenant.categories.actions.import')"
                    :title="t('app.tenant.categories.import.title')"
                    :description="t('app.tenant.categories.import.description')"
                    :file-label="t('app.tenant.categories.import.file_label')"
                    :submit-label="t('app.tenant.categories.import.submit')"
                    :submitting-label="t('app.tenant.categories.import.submitting')"
                    :cancel-label="t('app.tenant.categories.import.cancel')"
                />
                <NewActionButton :href="CategoryController.create.url(props.subdomain)">
                    {{ t('app.tenant.categories.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :title="pageMeta.title"
            :description="pageMeta.description"
            :meta="categoriesMeta"
            label="categoria"
            :action="categoriesIndexPath"
            :clear-href="categoriesIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
        >
            <template #filters>
                <select
                    name="status"
                    :value="props.filters.status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">{{ t('app.tenant.categories.status_options.draft') }}</option>
                    <option value="published">{{ t('app.tenant.categories.status_options.published') }}</option>
                    <option value="importer">{{ t('app.tenant.categories.status_options.importer') }}</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.full_path') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.categories.fields.level_name') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="categoriesLoading">
                        <TableLoadingSkeleton :columns="5" :rows="6" />
                    </template>
                    <tr v-else-if="categoriesRows.length === 0">
                        <td class="px-4 py-8 text-center text-muted-foreground" colspan="5">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="category in categoriesRows"
                        :key="category.id"
                        class="border-t border-sidebar-border/60 transition-colors hover:bg-muted/20 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <ColumnLabel :label="category.name" :description="category.slug" />
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ category.full_path }}</td>
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="category.status" />
                        </td>
                        <td class="px-4 py-3">
                            <Badge v-if="category.level_name" variant="outline">
                                {{ category.level_name }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <ColumnActions
                                :edit-href="CategoryController.edit.url({ subdomain: props.subdomain, category: category.id })"
                                :delete-href="CategoryController.destroy.url({ subdomain: props.subdomain, category: category.id })"
                                :delete-label="category.name"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
