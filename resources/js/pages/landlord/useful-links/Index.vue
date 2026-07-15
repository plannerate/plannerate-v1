<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UsefulLinkController from '@/actions/App/Http/Controllers/Landlord/UsefulLinkController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { ColumnActions } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginator } from '@/types';

type UsefulLinkRow = {
    id: string;
    name: string;
    url: string;
    logo: string | null;
    description: string | null;
    show_on_tenant_dashboard: boolean;
    trashed: boolean;
};

const props = defineProps<{
    useful_links?: Paginator<UsefulLinkRow>;
    filters: {
        search: string;
        show_on_tenant_dashboard: string;
        trashed: 'without' | 'only' | 'with';
    };
}>();

const { t } = useT();
const usefulLinksIndexPath = UsefulLinkController.index.url().replace(/^\/\/[^/]+/, '');
const { meta: usefulLinksMeta, rows: usefulLinksRows, loading: usefulLinksLoading } = useDeferredPaginator(() => props.useful_links, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.useful_links.title'),
    title: t('app.landlord.useful_links.title'),
    description: t('app.landlord.useful_links.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.useful_links.navigation'),
            href: usefulLinksIndexPath,
        },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="UsefulLinkController.create.url()">
                    {{ t('app.landlord.useful_links.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="usefulLinksMeta"
            label="link"
            :action="usefulLinksIndexPath"
            :clear-href="usefulLinksIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.landlord.common.search')"
            :filter-label="t('app.landlord.common.filter')"
            :clear-label="t('app.landlord.common.clear_filters')"
            :trashed-value="props.filters.trashed"
        >
            <template #filters>
                <select
                    name="show_on_tenant_dashboard"
                    :value="props.filters.show_on_tenant_dashboard"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">{{ t('app.landlord.common.all') }}</option>
                    <option value="1">{{ t('app.landlord.common.yes') }}</option>
                    <option value="0">{{ t('app.landlord.common.no') }}</option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.useful_links.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.useful_links.fields.url') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.useful_links.fields.show_on_tenant_dashboard') }}</th>
                        <th class="px-4 py-3 font-medium ">{{ t('app.landlord.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="usefulLinksLoading">
                        <TableLoadingSkeleton :columns="4" :rows="6" />
                    </template>
                    <tr v-else-if="usefulLinksRows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="4">
                            {{ t('app.landlord.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="usefulLink in usefulLinksRows"
                        :key="usefulLink.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <img v-if="usefulLink.logo" :src="usefulLink.logo" :alt="usefulLink.name" class="size-6 rounded object-cover" />
                                <div class="font-medium">{{ usefulLink.name }}</div>
                            </div>
                            <div v-if="usefulLink.description" class="text-xs text-muted-foreground">{{ usefulLink.description }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <a :href="usefulLink.url" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">
                                {{ usefulLink.url }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ usefulLink.show_on_tenant_dashboard ? t('app.landlord.common.yes') : t('app.landlord.common.no') }}</td>
                        <td class="px-4 py-3 ">
                            <ColumnActions
                                :edit-href="UsefulLinkController.edit.url(usefulLink.id)"
                                :delete-href="UsefulLinkController.destroy.url(usefulLink.id)"
                                :delete-label="usefulLink.name ?? undefined"
                                :require-confirm-word="true"
                                :is-trashed="usefulLink.trashed"
                                :restore-href="UsefulLinkController.restore.url(usefulLink.id)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
