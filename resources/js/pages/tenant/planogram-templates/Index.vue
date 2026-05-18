<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Upload } from 'lucide-vue-next';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnDate, ColumnLabel, ColumnStatusBadge } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
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
    headTitle: 'Templates de Planograma',
    title: 'Templates de Planograma',
    description: 'Importe e gerencie templates de planograma para geração automática com layout predefinido',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: 'Templates de Planograma', href: indexPath },
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
            <NewActionButton :href="PlanogramTemplateController.create.url(props.subdomain)">
                <Upload class="size-4" />
                Importar Template
            </NewActionButton>
        </template>

        <ListPage
            :meta="templatesMeta"
            label="template"
            :action="indexPath"
            :clear-href="indexPath"
            :search-value="props.filters.search"
            search-placeholder="Buscar por código, nome ou departamento..."
            filter-label="Filtros"
            clear-label="Limpar filtros"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Código / Nome</th>
                        <th class="px-4 py-3 font-medium">Departamento</th>
                        <th class="px-4 py-3 font-medium">Subtemplates</th>
                        <th class="px-4 py-3 font-medium">Produtos</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Criado em</th>
                        <th class="px-4 py-3 font-medium text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="templatesLoading">
                        <TableLoadingSkeleton :columns="7" :rows="6" />
                    </template>
                    <tr v-else-if="templatesRows.length === 0">
                        <td class="px-4 py-8 text-center text-muted-foreground" colspan="7">
                            Nenhum template importado ainda.
                            <a :href="PlanogramTemplateController.create.url(props.subdomain)" class="ml-1 text-primary underline">
                                Importar template →
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
                            <ColumnStatusBadge :active="template.is_active" active-label="Ativo" inactive-label="Inativo" />
                        </td>
                        <td class="px-4 py-3">
                            <ColumnDate :date="template.created_at" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <ColumnActions
                                :show-href="PlanogramTemplateController.show.url({ subdomain: props.subdomain, planogramTemplate: template.id })"
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
