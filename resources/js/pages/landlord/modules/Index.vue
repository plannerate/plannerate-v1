<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ModuleController from '@/actions/App/Http/Controllers/Landlord/ModuleController';
import WayfinderLink from '@/components/WayfinderLink.vue';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginator } from '@/types';

type ModuleRow = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    tenants_count: number;
};

const props = defineProps<{
    modules?: Paginator<ModuleRow>;
    filters: {
        search: string;
        is_active: string;
    };
}>();

const { t } = useT();
const modulesIndexPath = ModuleController.index.url().replace(/^\/\/[^/]+/, '');
const { meta: modulesMeta, rows: modulesRows, loading: modulesLoading } = useDeferredPaginator(() => props.modules, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.modules.title'),
    title: t('app.landlord.modules.title'),
    description: t('app.landlord.modules.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.modules.navigation'),
            href: modulesIndexPath,
        },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="ModuleController.create.url()">
                    {{ t('app.landlord.modules.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="modulesMeta"
            label="modulo"
            :action="modulesIndexPath"
            :clear-href="modulesIndexPath"
            :search-value="props.filters.search"
            :search-placeholder="t('app.landlord.common.search')"
            :filter-label="t('app.landlord.common.filter')"
            :clear-label="t('app.landlord.common.clear_filters')"
            :show-trashed-filter="false"
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
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.modules.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">Slug</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.modules.fields.is_active') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.modules.fields.tenants_count') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('app.landlord.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="modulesLoading">
                        <TableLoadingSkeleton :columns="5" :rows="6" />
                    </template>
                    <tr v-else-if="modulesRows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="5">
                            {{ t('app.landlord.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="module in modulesRows"
                        :key="module.id"
                        class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ module.name }}</div>
                            <div v-if="module.description" class="text-xs text-muted-foreground">{{ module.description }}</div>
                        </td>
                        <td class="px-4 py-3">{{ module.slug }}</td>
                        <td class="px-4 py-3">{{ module.is_active ? t('app.landlord.common.yes') : t('app.landlord.common.no') }}</td>
                        <td class="px-4 py-3">{{ module.tenants_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <WayfinderLink :href="ModuleController.edit.url(module.id)">
                                        {{ t('app.landlord.common.edit') }}
                                    </WayfinderLink>
                                </Button>
                                <Button variant="destructive" size="sm" as-child>
                                    <WayfinderLink
                                        :href="ModuleController.destroy.url(module.id)"
                                        method="delete"
                                        as="button"
                                    >
                                        {{ t('app.landlord.common.delete') }}
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
