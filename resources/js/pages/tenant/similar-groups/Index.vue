<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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

type SimilarGroupRow = {
    id: string;
    grouper_code: string;
    name: string;
    product_codes: string[];
    products_count: number;
    status: 'draft' | 'published';
    created_at: string;
};

const props = defineProps<{
    similarGroups?: Paginator<SimilarGroupRow>;
    filters: {
        search: string;
        status: string;
        trashed: 'without' | 'only' | 'with';
    };
    can: {
        create: boolean;
        limit_reached: boolean;
        limit_message: string | null;
        upgrade_url: string | null;
    };
}>();

const { t } = useT();
const indexPath = `/similar-groups`;
const createPath = `/similar-groups/create`;
const editPath = (id: string) => `/similar-groups/${id}/edit`;
const deletePath = (id: string) => `/similar-groups/${id}`;

const { meta, rows, loading } = useDeferredPaginator(() => props.similarGroups, 10);

const pageMeta = useCrudPageMeta({
    headTitle: 'Grupo de Similares',
    title: 'Grupo de Similares',
    description: 'Gerencie os grupos de produtos similares.',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: 'Grupo de Similares', href: indexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton v-if="can.create" :href="createPath">
                    Novo Grupo
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="meta"
            label="grupo"
            :action="indexPath"
            :clear-href="indexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
            :trashed-value="props.filters.trashed"
        >
            <template #filters>
                <select
                    name="status"
                    :value="props.filters.status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">Rascunho</option>
                    <option value="published">Publicado</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Cód. Agrupador</th>
                        <th class="px-4 py-3 font-medium">Nome do Grupo</th>
                        <th class="px-4 py-3 font-medium">Cód. Produtos</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="loading">
                        <TableLoadingSkeleton :columns="5" :rows="6" />
                    </template>
                    <tr v-else-if="rows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="5">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="group in rows"
                        :key="group.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3 font-mono text-sm">{{ group.grouper_code }}</td>
                        <td class="px-4 py-3">
                            <ColumnLabel :label="group.name" />
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="group.products_count === 0 && group.product_codes.length === 0" class="text-muted-foreground">—</span>
                            <span v-else class="text-muted-foreground">
                                {{ group.product_codes.slice(0, 3).join(', ') }}{{ group.product_codes.length > 3 ? ` +${group.product_codes.length - 3}` : '' }}
                                <span v-if="group.products_count > 0" class="ml-1 text-xs">({{ group.products_count }})</span>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="group.status" />
                        </td>
                        <td class="px-4 py-3">
                            <ColumnActions
                                :edit-href="editPath(group.id)"
                                :delete-href="deletePath(group.id)"
                                :delete-label="group.name"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
