<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProviderController from '@/actions/App/Http/Controllers/Tenant/ProviderController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions, ColumnLabel } from '@/components/table/columns';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ProviderRow = {
    id: string;
    code: string | null;
    name: string | null;
    email: string | null;
    phone: string | null;
    cnpj: string | null;
    is_default: boolean;
};

const props = defineProps<{
    subdomain: string;
    providers: Paginator<ProviderRow>;
    filters: {
        search: string;
        is_default: string;
    };
}>();

const { t } = useT();
const providersIndexPath = ProviderController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.providers.title'),
    title: t('app.tenant.providers.title'),
    description: t('app.tenant.providers.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.providers.navigation'), href: providersIndexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="ProviderController.create.url(props.subdomain)">
                    {{ t('app.tenant.providers.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="props.providers"
            label="provider"
            :action="providersIndexPath"
            :clear-href="providersIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')"
            :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')"
        >
            <template #filters>
                <select name="is_default" :value="props.filters.is_default" class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="1">{{ t('app.tenant.common.yes') }}</option>
                    <option value="0">{{ t('app.tenant.common.no') }}</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.cnpj') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.email') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.providers.fields.is_default') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.providers.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="5">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="provider in props.providers.data"
                        :key="provider.id"
                        class="border-t border-sidebar-border/60 transition-colors hover:bg-muted/20 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <ColumnLabel :label="provider.name ?? '-'" :description="provider.code" />
                        </td>
                        <td class="px-4 py-3">{{ provider.cnpj ?? '-' }}</td>
                        <td class="px-4 py-3">{{ provider.email ?? '-' }}</td>
                        <td class="px-4 py-3">{{ provider.is_default ? t('app.tenant.common.yes') : t('app.tenant.common.no') }}</td>
                        <td class="px-4 py-3 text-right">
                            <ColumnActions
                                :edit-href="ProviderController.edit.url({ subdomain: props.subdomain, provider: provider.id })"
                                :delete-href="ProviderController.destroy.url({ subdomain: props.subdomain, provider: provider.id })"
                                :delete-label="provider.name ?? undefined"
                                :require-confirm-word="true"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
