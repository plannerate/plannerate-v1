<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PanelTop } from 'lucide-vue-next';
import GondolaController from '@/actions/App/Http/Controllers/Tenant/GondolaController';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import PlanLimitAlert from '@/components/PlanLimitAlert.vue';
import { ColumnActions, ColumnHeader, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Button } from '@/components/ui/button';
import WayfinderLink from '@/components/WayfinderLink.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { editor as tenantEditorPlanogramGondolas } from '@/routes/tenant/planograms/gondolas';
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
const { meta: gondolasMeta, rows: gondolasRows, loading: gondolasLoading } = useDeferredPaginator(() => props.gondolas, 10);
const gondolasIndexPath = GondolaController.index.url({
    planogram: props.planogram.id,
}).replace(/^\/\/[^/]+/, '');
const planogramsIndexPath = PlanogramController.index.url().replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.gondolas.title'),
    title: t('app.tenant.gondolas.title'),
    description: t('app.tenant.gondolas.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: planogramsIndexPath },
        { title: props.planogram.name ?? '-', href: planogramsIndexPath },
        { title: t('app.tenant.gondolas.navigation'), href: gondolasIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button variant="outline" size="sm" as-child>
                    <WayfinderLink :href="planogramsIndexPath">{{ t('app.actions.back') }}</WayfinderLink>
                </Button>
                <NewActionButton
                    v-if="can.create"
                    :href="GondolaController.create.url({
                        planogram: props.planogram.id,
                    })"
                >
                    {{ t('app.tenant.gondolas.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <PlanLimitAlert v-if="can.limit_reached" :message="can.limit_message!" :upgrade-url="can.upgrade_url" />

        <ListPage
            :meta="gondolasMeta"
            label="gôndola"
            :action="gondolasIndexPath"
            :clear-href="gondolasIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
            :trashed-value="props.filters.trashed"
        >
            <template #filters>
                <select name="status" :value="props.filters.status" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
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
                            <ColumnActions
                                :edit-href="GondolaController.edit.url({ planogram: props.planogram.id, gondola: gondola.id })"
                                :delete-href="GondolaController.destroy.url({ planogram: props.planogram.id, gondola: gondola.id })"
                                :delete-label="gondola.name"
                                :require-confirm-word="true"
                            >
                                <Button variant="outline" size="sm" as-child>
                                    <a
                                        target="_blank"
                                        :href="tenantEditorPlanogramGondolas.url({ record: gondola.id })"
                                    >
                                        {{ t('app.tenant.planograms.actions.view_gondolas') }}
                                    </a>
                                </Button>
                            </ColumnActions>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
