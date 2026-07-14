<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText, LayoutTemplate, PanelTop } from 'lucide-vue-next';
import ClientPlanogramController from '@/actions/App/Http/Controllers/Tenant/Editor/ClientPlanogramController';
import GondolaPdfPreviewController from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import ListPage from '@/components/ListPage.vue';
import { ColumnHeader, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue'; 
import type { Paginator } from '@/types';

type GondolaRow = {
    id: string;
    name: string;
    num_modulos: number;
    location: string | null;
    side: string | null;
    flow: 'left_to_right' | 'right_to_left';
    alignment: 'left' | 'right' | 'center' | 'justify';
    scale_factor: number;
    status: 'draft' | 'published';
};

const props = defineProps<{
    planogram: {
        id: string;
        name: string | null;
    };
    gondolas?: Paginator<GondolaRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const { meta: gondolasMeta, rows: gondolasRows, loading: gondolasLoading } = useDeferredPaginator(() => props.gondolas, 10);

const gondolasIndexPath = ClientPlanogramController.gondolas.url({
    planogram: props.planogram.id,
}).replace(/^\/\/[^/]+/, '');

const planogramsIndexPath = ClientPlanogramController.index.url().replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.gondolas.title'),
    title: props.planogram.name ?? t('app.tenant.gondolas.title'),
    description: t('app.tenant.gondolas.description'),
    breadcrumbs: [
        { title: 'Planogramas Publicados', href: planogramsIndexPath },
        { title: props.planogram.name ?? '-', href: planogramsIndexPath },
        { title: t('app.tenant.gondolas.navigation'), href: gondolasIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <Button variant="outline" size="sm" as-child>
                <a :href="planogramsIndexPath">{{ t('app.actions.back') }}</a>
            </Button>
        </template>

        <ListPage
            :meta="gondolasMeta"
            label="gôndola"
            :action="gondolasIndexPath"
            :clear-href="gondolasIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
            :show-trashed-filter="false"
        >
            <template #filters>
                <select
                    name="status"
                    :value="props.filters.status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <ColumnHeader field="name">{{ t('app.tenant.gondolas.fields.name') }}</ColumnHeader>
                        <ColumnHeader field="num_modulos">{{ t('app.tenant.gondolas.fields.modules') }}</ColumnHeader>
                        <ColumnHeader field="flow">{{ t('app.tenant.gondolas.fields.flow') }}</ColumnHeader>
                        <ColumnHeader field="alignment">{{ t('app.tenant.gondolas.fields.alignment') }}</ColumnHeader>
                        <ColumnHeader field="status">{{ t('app.tenant.gondolas.fields.status') }}</ColumnHeader>
                        <th class="px-4 py-3 font-medium ">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="gondolasLoading">
                        <TableLoadingSkeleton :columns="6" :rows="6" />
                    </template>
                    <tr v-else-if="gondolasRows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="6">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="gondola in gondolasRows"
                        :key="gondola.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <PanelTop class="size-4 shrink-0 text-muted-foreground" />
                                <ColumnLabel :label="gondola.name" :description="gondola.slug" />
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ gondola.num_modulos }}</td>
                        <td class="px-4 py-3">{{ gondola.flow }}</td>
                        <td class="px-4 py-3">{{ gondola.alignment }}</td>
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="gondola.status" />
                        </td>
                        <td class="px-4 py-3 ">
                            <div class="inline-flex items-center gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <a
                                        target="_blank"
                                        :href="GondolaPdfPreviewController.show.url({ gondola: gondola.id })"
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <FileText class="size-3.5" />
                                        Preview PDF
                                        <LayoutTemplate class="size-3" />
                                    </a>
                                </Button> 
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
