<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Layers } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkflowTemplateController from '@/actions/App/Http/Controllers/Tenant/WorkflowTemplateController';
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';
import ListTablePage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type TemplateRow = {
    id: string;
    name: string;
    slug: string;
    color: string | null;
    icon: string | null;
    suggested_order: number;
    status: 'draft' | 'published';
    created_at: string | null;
};

const props = defineProps<{
    subdomain: string;
    templates: Paginator<TemplateRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();

const indexPath = WorkflowTemplateController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const kanbanPath = WorkflowKanbanController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.kanban.templates.title'),
    title: t('app.kanban.templates.title'),
    description: t('app.kanban.templates.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.kanban.navigation'), href: kanbanPath },
        { title: t('app.kanban.templates.navigation'), href: indexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="WorkflowTemplateController.create.url(props.subdomain)">
                    {{ t('app.kanban.templates.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListTablePage
            :meta="props.templates"
            label="etapa"
            :action="indexPath"
            :clear-href="indexPath"
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
                    <option value="">{{ t('app.tenant.common.status') }}</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.kanban.templates.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.kanban.templates.fields.suggested_order') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.kanban.templates.fields.status') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.templates.data.length === 0">
                        <td class="px-4 py-10 text-center text-muted-foreground" colspan="4">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="template in props.templates.data"
                        :key="template.id"
                        class="border-t border-sidebar-border/60 transition-colors hover:bg-muted/20 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="template.color"
                                    class="size-3 rounded-full shrink-0"
                                    :style="{ backgroundColor: template.color }"
                                />
                                <Layers v-else class="size-4 shrink-0 text-muted-foreground" />
                                <ColumnLabel :label="template.name" :description="template.slug" />
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ template.suggested_order }}
                        </td>
                        <td class="px-4 py-3">
                            <ColumnStatusBadge :status="template.status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <ColumnActions
                                :edit-href="WorkflowTemplateController.edit.url({ subdomain: props.subdomain, template: template.id })"
                                :delete-href="WorkflowTemplateController.destroy.url({ subdomain: props.subdomain, template: template.id })"
                                :delete-label="template.name"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListTablePage>
    </AppLayout>
</template>
