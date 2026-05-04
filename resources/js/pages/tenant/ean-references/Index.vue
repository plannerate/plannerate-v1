<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EanReferenceController from '@/actions/App/Http/Controllers/Tenant/EanReferenceController';
import WayfinderLink from '@/components/WayfinderLink.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import type { Paginator } from '@/types';

type EanReferenceRow = {
    id: string;
    ean: string;
    reference_description: string | null;
    brand: string | null;
    subbrand: string | null;
    packaging_type: string | null;
    packaging_size: string | null;
    measurement_unit: string | null;
};

const props = defineProps<{
    subdomain: string;
    ean_references?: Paginator<EanReferenceRow>;
    filters: {
        search: string;
    };
}>();

const { t } = useT();
const eanReferencesIndexPath = tenantWayfinderPath(EanReferenceController.index.url(props.subdomain));
const { meta: eanReferencesMeta, rows: eanReferencesRows, loading: eanReferencesLoading } = useDeferredPaginator(() => props.ean_references, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.ean_references.title'),
    title: t('app.tenant.ean_references.title'),
    description: t('app.tenant.ean_references.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.tenant.ean_references.navigation'),
            href: eanReferencesIndexPath,
        },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="tenantWayfinderPath(EanReferenceController.create.url(props.subdomain))">
                    {{ t('app.tenant.ean_references.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="eanReferencesMeta"
            label="referencia ean"
            :action="eanReferencesIndexPath"
            :clear-href="eanReferencesIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
            :show-trashed-filter="false"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.ean_references.fields.ean') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.ean_references.fields.reference_description') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.ean_references.fields.brand') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.ean_references.fields.packaging_type') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="eanReferencesLoading">
                        <TableLoadingSkeleton :columns="5" :rows="6" />
                    </template>
                    <tr v-else-if="eanReferencesRows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="5">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="eanReference in eanReferencesRows"
                        :key="eanReference.id"
                        class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3 font-medium">{{ eanReference.ean }}</td>
                        <td class="px-4 py-3">{{ eanReference.reference_description || '-' }}</td>
                        <td class="px-4 py-3">{{ eanReference.brand || '-' }}</td>
                        <td class="px-4 py-3">{{ eanReference.packaging_type || '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <WayfinderLink :href="tenantWayfinderPath(EanReferenceController.edit.url({ subdomain: props.subdomain, ean_reference: eanReference.id }))">
                                        {{ t('app.tenant.common.edit') }}
                                    </WayfinderLink>
                                </Button>
                                <Button variant="destructive" size="sm" as-child>
                                    <WayfinderLink
                                        :href="tenantWayfinderPath(EanReferenceController.destroy.url({ subdomain: props.subdomain, ean_reference: eanReference.id }))"
                                        method="delete"
                                        as="button"
                                    >
                                        {{ t('app.tenant.common.delete') }}
                                    </WayfinderLink>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
