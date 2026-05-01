<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import ListTablePage from '@/components/ListPage.vue';
import type ListPage from '@/components/ListPage.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type OrphanLayerRow = {
    layer_id: string;
    segment_id: string;
    product_id_atual: string;
    ean: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    subdomain: string;
    orphans?: Paginator<OrphanLayerRow>;
    filters: {
        search: string;
    };
}>();

const { t } = useT();
const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);
const { meta: orphansMeta, rows: orphanRows, loading: orphanLoading } = useDeferredPaginator(() => props.orphans, 10);
const indexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const orphanLayersPath = PlanogramController.orphanLayers.url(props.subdomain).replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: 'Layers sem produto válido',
    title: 'Layers sem produto válido',
    description: 'Gestão de layers com product_id inválido para correção de vínculos por produto.',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: indexPath },
        { title: 'Layers órfãs', href: orphanLayersPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <Link
                    :href="indexPath"
                    class="inline-flex h-9 items-center rounded-lg border border-border bg-background px-3 text-sm font-medium text-foreground transition hover:bg-muted"
                >
                    Voltar para planogramas
                </Link>
            </div>
        </template>

        <ListTablePage
            ref="listPageRef"
            :meta="orphansMeta"
            label="layer órfã"
            :action="orphanLayersPath"
            :clear-href="orphanLayersPath"
            :search-value="props.filters.search"
            search-placeholder="Buscar por layer, segmento, product_id ou EAN..."
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Layer ID</th>
                        <th class="px-4 py-3 font-medium">Segment ID</th>
                        <th class="px-4 py-3 font-medium">Product ID atual</th>
                        <th class="px-4 py-3 font-medium">EAN</th>
                        <th class="px-4 py-3 font-medium">Atualizado em</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="orphanLoading">
                        <TableLoadingSkeleton :columns="5" :rows="8" />
                    </template>
                    <tr v-else-if="orphanRows.length === 0">
                        <td class="px-4 py-10 text-center text-muted-foreground" colspan="5">
                            Nenhuma layer órfã encontrada.
                        </td>
                    </tr>
                    <tr
                        v-for="row in orphanRows"
                        :key="row.layer_id"
                        class="border-t border-sidebar-border/60 transition-colors hover:bg-muted/20 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3 font-mono text-xs">{{ row.layer_id }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ row.segment_id }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ row.product_id_atual }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ row.ean ?? '-' }}</td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">{{ row.updated_at ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </ListTablePage>
    </AppLayout>
</template>
