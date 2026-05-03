<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PermissionController from '@/actions/App/Http/Controllers/Landlord/PermissionController';
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

type PermissionRow = {
    id: string;
    name: string;
    type: string;
    is_protected: boolean;
};

const props = defineProps<{
    permissions?: Paginator<PermissionRow>;
    filters: {
        search: string;
        type: string;
    };
    filter_options: {
        types: Array<{ value: string; label: string }>;
    };
}>();

const { t } = useT();
const permissionsIndexPath = PermissionController.index.url().replace(/^\/\/[^/]+/, '');
const { meta: permissionsMeta, rows: permissionsRows, loading: permissionsLoading } = useDeferredPaginator(() => props.permissions, 15);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.permissions.title'),
    title: t('app.landlord.permissions.title'),
    description: t('app.landlord.permissions.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.permissions.navigation'),
            href: permissionsIndexPath,
        },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="PermissionController.create.url()">
                    {{ t('app.landlord.permissions.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="permissionsMeta"
        label="permissão"
        :action="permissionsIndexPath"
        :clear-href="permissionsIndexPath"
        :search-value="props.filters.search"
        :search-placeholder="t('app.landlord.common.search')"
        :filter-label="t('app.landlord.common.filter')"
        :clear-label="t('app.landlord.common.clear_filters')"
        :show-trashed-filter="false"
    >
        <template #filters>
            <select
                name="type"
                :value="props.filters.type"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.landlord.common.all') }}</option>
                <option v-for="type in props.filter_options.types" :key="type.value" :value="type.value">
                    {{ type.label }}
                </option>
            </select>
        </template>

        <table class="w-full text-sm">
            <thead class="bg-muted/30 text-left text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.permissions.fields.name') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.permissions.fields.type') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.landlord.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="permissionsLoading">
                    <TableLoadingSkeleton :columns="3" :rows="6" />
                </template>
                <tr v-else-if="permissionsRows.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="3">
                        {{ t('app.landlord.common.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="permission in permissionsRows"
                    :key="permission.id"
                    class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                >
                    <td class="px-4 py-3 font-medium">{{ permission.name }}</td>
                    <td class="px-4 py-3">{{ t(`app.landlord.roles.types.${permission.type}`) }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <WayfinderLink :href="PermissionController.edit.url(permission.id)">
                                    {{ t('app.landlord.common.edit') }}
                                </WayfinderLink>
                            </Button>
                            <Button v-if="!permission.is_protected" variant="destructive" size="sm" as-child>
                                <WayfinderLink
                                    :href="PermissionController.destroy.url(permission.id)"
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
