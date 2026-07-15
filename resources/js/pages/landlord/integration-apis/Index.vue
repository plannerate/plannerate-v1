<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import IntegrationApiController from '@/actions/App/Http/Controllers/Landlord/IntegrationApiController';
import ImportFileButton from '@/components/imports/ImportFileButton.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginator } from '@/types';

type IntegrationApiRow = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    trashed: boolean;
};

const props = defineProps<{
    integrationApis?: Paginator<IntegrationApiRow>;
    filters: {
        search: string;
        is_active: string;
        trashed: 'without' | 'only' | 'with';
    };
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const { t } = useT();
const integrationApisIndexPath = IntegrationApiController.index.url().replace(/^\/\/[^/]+/, '');
const {
    meta: integrationApisMeta,
    rows: integrationApisRows,
    loading: integrationApisLoading,
} = useDeferredPaginator(() => props.integrationApis, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.integration_apis.title'),
    title: t('app.landlord.integration_apis.title'),
    description: t('app.landlord.integration_apis.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.integration_apis.navigation'),
            href: integrationApisIndexPath,
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
                    <a :href="IntegrationApiController.exportConfigurations.url()">
                        {{ t('app.landlord.integration_apis.actions.export') }}
                    </a>
                </Button>
                <ImportFileButton
                    :action="IntegrationApiController.importConfigurations.url()"
                    :button-label="t('app.landlord.integration_apis.actions.import')"
                    :title="t('app.landlord.integration_apis.import.title')"
                    :description="t('app.landlord.integration_apis.import.description')"
                    :file-label="t('app.landlord.integration_apis.import.file_label')"
                    :submit-label="t('app.landlord.integration_apis.import.submit')"
                    :submitting-label="t('app.landlord.integration_apis.import.submitting')"
                    :cancel-label="t('app.landlord.integration_apis.import.cancel')"
                    accept=".json,application/json,text/plain"
                    drop-label="Arraste e solte o arquivo JSON aqui"
                    drop-hint="ou clique para escolher um arquivo .json"
                />
                <NewActionButton v-if="can.create" :href="IntegrationApiController.create.url()">
                    {{ t('app.landlord.integration_apis.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="integrationApisMeta"
            label="api"
            :action="integrationApisIndexPath"
            :clear-href="integrationApisIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.landlord.common.search')"
            :filter-label="t('app.landlord.common.filter')"
            :clear-label="t('app.landlord.common.clear_filters')"
            :trashed-value="props.filters.trashed"
        >
            <template #filters>
                <select
                    name="is_active"
                    :value="props.filters.is_active"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.landlord.common.all') }}</option>
                    <option value="1">{{ t('app.landlord.common.active') }}</option>
                    <option value="0">{{ t('app.landlord.common.inactive') }}</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.integration_apis.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.integration_apis.fields.slug') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.integration_apis.fields.is_active') }}</th>
                        <th class="px-4 py-3 font-medium ">{{ t('app.landlord.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="integrationApisLoading">
                        <TableLoadingSkeleton :columns="4" :rows="6" />
                    </template>
                    <tr v-else-if="integrationApisRows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="4">
                            {{ t('app.landlord.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="integrationApi in integrationApisRows"
                        :key="integrationApi.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ integrationApi.name }}</div>
                            <div v-if="integrationApi.description" class="text-xs text-muted-foreground">{{ integrationApi.description }}</div>
                        </td>
                        <td class="px-4 py-3">{{ integrationApi.slug }}</td>
                        <td class="px-4 py-3">{{ integrationApi.is_active ? t('app.landlord.common.yes') : t('app.landlord.common.no') }}</td>
                        <td class="px-4 py-3 ">
                            <ColumnActions
                                :edit-href="IntegrationApiController.edit.url(integrationApi.id)"
                                :delete-href="IntegrationApiController.destroy.url(integrationApi.id)"
                                :delete-label="integrationApi.name ?? undefined"
                                :require-confirm-word="true"
                                :is-trashed="integrationApi.trashed"
                                :restore-href="IntegrationApiController.restore.url(integrationApi.id)"
                                :can-edit="can.update"
                                :can-delete="can.delete"
                                :can-restore="can.delete"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
