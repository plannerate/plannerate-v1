<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClusterController from '@/actions/App/Http/Controllers/Tenant/ClusterController';
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { useT } from '@/composables/useT';
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
    clusters: Paginator<ClusterRow>;
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
</script>

<template>
    <Head :title="t('app.tenant.clusters.title')" />

    <ListPage
        :title="t('app.tenant.clusters.title')"
        :description="t('app.tenant.clusters.description')"
        :meta="props.clusters"
        label="cluster"
        :action="clustersIndexPath"
        :clear-href="clustersIndexPath"
        :search-value="props.filters.search"
        :search-placeholder="t('app.tenant.common.search')"
        :filter-label="t('app.tenant.common.filter')"
        :clear-label="t('app.tenant.common.clear_filters')"
    >
        <template #action>
            <NewActionButton :href="ClusterController.create.url(props.subdomain)">
                {{ t('app.tenant.clusters.actions.new') }}
            </NewActionButton>
        </template>

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
                    <th class="px-4 py-3 font-medium">Slug</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.specification_1') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.clusters.fields.status') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="props.clusters.data.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="6">
                        {{ t('app.tenant.common.empty') }}
                    </td>
                </tr>
                <tr v-for="cluster in props.clusters.data" :key="cluster.id" class="border-t border-sidebar-border/60 dark:border-sidebar-border">
                    <td class="px-4 py-3 font-medium">{{ cluster.name }}</td>
                    <td class="px-4 py-3">{{ cluster.store ?? '-' }}</td>
                    <td class="px-4 py-3">{{ cluster.slug ?? '-' }}</td>
                    <td class="px-4 py-3">{{ cluster.specification_1 ?? '-' }}</td>
                    <td class="px-4 py-3">{{ cluster.status }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <EditButton :href="ClusterController.edit.url({ subdomain: props.subdomain, cluster: cluster.id })" />
                            <DeleteButton
                                :href="ClusterController.destroy.url({ subdomain: props.subdomain, cluster: cluster.id })"
                                :label="cluster.name"
                                require-confirm-word
                            />
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </ListPage>
</template>
