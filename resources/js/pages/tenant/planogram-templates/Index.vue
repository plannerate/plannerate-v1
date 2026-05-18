<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Upload } from 'lucide-vue-next';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnDate, ColumnLabel } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type TemplateRow = {
    id: string;
    code: string;
    name: string;
    department: string;
    is_active: boolean;
    subtemplates_count: number;
    template_products_count: number;
    created_at: string | null;
};

const props = defineProps<{
    subdomain: string;
    templates?: Paginator<TemplateRow>;
    filters: {
        search: string;
    };
}>();

const { t } = useT();
const indexPath = PlanogramTemplateController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const { meta: templatesMeta, rows: templatesRows, loading: templatesLoading } = useDeferredPaginator(() => props.templates, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.planogram_templates.title'),
    title: t('app.tenant.planogram_templates.title'),
    description: t('app.tenant.planogram_templates.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planogram_templates.navigation'), href: indexPath },
    ],
});

function handleDelete(id: string): void {
    router.delete(PlanogramTemplateController.destroy.url({ subdomain: props.subdomain, planogramTemplate: id }));
}
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center gap-2">
                <NewActionButton :href="PlanogramTemplateController.create.url(props.subdomain)">
                    <Plus class="size-4" />
                    {{ t('app.tenant.planogram_templates.actions.create') }}
                </NewActionButton>
                <Button variant="outline" :as="'a'" :href="PlanogramTemplateController.importPage.url(props.subdomain)">
                    <Upload class="size-4" />
                    {{ t('app.tenant.planogram_templates.actions.import') }}
                </Button>
            </div>
        </template>

        <ListPage
            :meta="templatesMeta"
            label="template"
            :action="indexPath"
            :clear-href="indexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.planogram_templates.search_placeholder')"
            :filter-label="t('app.tenant.planogram_templates.filter_label')"
            :clear-label="t('app.tenant.planogram_templates.clear_label')"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.planogram_templates.fields.code_name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.planogram_templates.fields.department') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.planogram_templates.fields.subtemplates') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.planogram_templates.fields.products') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.planogram_templates.fields.status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.planogram_templates.fields.created_at') }}</th>
                        <th class="px-4 py-3 font-medium ">{{ t('app.tenant.planogram_templates.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="templatesLoading">
                        <TableLoadingSkeleton :columns="7" :rows="6" />
                    </template>
                    <tr v-else-if="templatesRows.length === 0">
                        <td class="px-4 py-8 text-center text-muted-foreground" colspan="7">
                            {{ t('app.tenant.planogram_templates.empty') }}
                            <a :href="PlanogramTemplateController.create.url(props.subdomain)" class="ml-1 text-primary underline">
                                {{ t('app.tenant.planogram_templates.empty_action') }}
                            </a>
                        </td>
                    </tr>
                    <tr
                        v-for="template in templatesRows"
                        :key="template.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <ColumnLabel :label="template.name" :description="template.code" />
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ template.department }}</td>
                        <td class="px-4 py-3">{{ template.subtemplates_count }}</td>
                        <td class="px-4 py-3">{{ template.template_products_count }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="template.is_active ? 'default' : 'secondary'">
                                {{ template.is_active ? t('app.tenant.planogram_templates.status.active') : t('app.tenant.planogram_templates.status.inactive') }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <ColumnDate :date="template.created_at" />
                        </td>
                        <td class="px-4 py-3 ">
                            <ColumnActions
                                :edit-href="PlanogramTemplateController.edit.url({ subdomain: props.subdomain, planogramTemplate: template.id })"
                                :delete-href="PlanogramTemplateController.destroy.url({ subdomain: props.subdomain, planogramTemplate: template.id })"
                                :delete-label="template.name"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
