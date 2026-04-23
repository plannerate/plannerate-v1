<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import StoreController from '@/actions/App/Http/Controllers/Tenant/StoreController';
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type StoreRow = {
    id: string;
    name: string | null;
    slug: string | null;
    code: string | null;
    document: string | null;
    status: 'draft' | 'published';
};

const props = defineProps<{
    subdomain: string;
    stores: Paginator<StoreRow>;
    filters: {
        search: string;
        status: string;
    };
}>();

const { t } = useT();
const storesIndexPath = StoreController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.stores.title'),
    title: t('app.tenant.stores.title'),
    description: t('app.tenant.stores.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.stores.navigation'), href: storesIndexPath },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />

    <ListPage
        :title="pageMeta.title"
        :description="pageMeta.description"
        :meta="props.stores"
        label="loja"
        :action="storesIndexPath"
        :clear-href="storesIndexPath"
        :search-value="props.filters.search"
        :search-placeholder="t('app.tenant.common.search')"
        :filter-label="t('app.tenant.common.filter')"
        :clear-label="t('app.tenant.common.clear_filters')"
    >
        <template #action>
            <NewActionButton :href="StoreController.create.url(props.subdomain)">
                {{ t('app.tenant.stores.actions.new') }}
            </NewActionButton>
        </template>

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
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.name') }}</th>
                    <th class="px-4 py-3 font-medium">Slug</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.code') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.document') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.tenant.stores.fields.status') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.tenant.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="props.stores.data.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="6">
                        {{ t('app.tenant.common.empty') }}
                    </td>
                </tr>
                <tr v-for="store in props.stores.data" :key="store.id" class="border-t border-sidebar-border/60 dark:border-sidebar-border">
                    <td class="px-4 py-3 font-medium">{{ store.name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ store.slug ?? '-' }}</td>
                    <td class="px-4 py-3">{{ store.code ?? '-' }}</td>
                    <td class="px-4 py-3">{{ store.document ?? '-' }}</td>
                    <td class="px-4 py-3">{{ store.status }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <EditButton :href="StoreController.edit.url({ subdomain: props.subdomain, store: store.id })" />
                            <DeleteButton
                                :href="StoreController.destroy.url({ subdomain: props.subdomain, store: store.id })"
                                :label="store.name ?? undefined"
                                require-confirm-word
                            />
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </ListPage>
</template>
