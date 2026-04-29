<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClusterController from '@/actions/App/Http/Controllers/Tenant/ClusterController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ClusterRow = {
    id: string;
    store_id: string;
    store: string | null;
    name: string;
    slug: string | null;
    specification_1: string | null;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    clusters?: Paginator<ClusterRow>;
    filters: {
        search: string;
        status: string;
        store_id: string;
    };
    filter_options: {
        stores: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const clustersIndexPath = ClusterController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const { meta: clustersMeta, rows: clustersRows, loading: clustersLoading } = useDeferredPaginator(() => props.clusters, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.clusters.title'),
    title: t('app.tenant.clusters.title'),
    description: t('app.tenant.clusters.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.clusters.navigation'), href: clustersIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="ClusterController.create.url(props.subdomain)">
                    {{ t('app.tenant.clusters.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="clustersMeta"
            label="cluster"
            :action="clustersIndexPath"
            :clear-href="clustersIndexPath"
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
                </select>

                <select name="store_id" :value="props.filters.store_id" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option v-for="store in props.filter_options.stores" :key="store.id" :value="store.id">
                        {{ store.name }}
                    </option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.store') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.specification_1') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.status') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="clustersLoading">
                        <TableLoadingSkeleton :columns="5" :rows="6" />
                    </template>
                    <tr v-else-if="clustersRows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="5">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="cluster in clustersRows"
                        :key="cluster.id"
                        class="border-t border-sidebar-border/60 transition-colors hover:bg-muted/20 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <ColumnLabel :label="cluster.name" :description="cluster.slug" />
                        </td>
                        <td class="px-4 py-3">{{ cluster.store ?? '-' }}</td>
                        <td class="px-4 py-3">{{ cluster.specification_1 ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="cluster.status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <ColumnActions
                                :edit-href="ClusterController.edit.url({ subdomain: props.subdomain, cluster: cluster.id })"
                                :delete-href="ClusterController.destroy.url({ subdomain: props.subdomain, cluster: cluster.id })"
                                :delete-label="cluster.name"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
