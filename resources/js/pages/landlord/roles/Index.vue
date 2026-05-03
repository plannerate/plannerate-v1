<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/Landlord/RoleController';
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

type RoleRow = {
    id: string;
    name: string;
    type: string;
    permissions_count: number;
    is_protected: boolean;
};

const props = defineProps<{
    roles?: Paginator<RoleRow>;
    filters: {
        search: string;
        type: string;
    };
    filter_options: {
        types: Array<{ value: string; label: string }>;
    };
}>();

const { t } = useT();
const rolesIndexPath = RoleController.index.url().replace(/^\/\/[^/]+/, '');
const { meta: rolesMeta, rows: rolesRows, loading: rolesLoading } = useDeferredPaginator(() => props.roles, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.roles.title'),
    title: t('app.landlord.roles.title'),
    description: t('app.landlord.roles.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.roles.navigation'),
            href: rolesIndexPath,
        },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="RoleController.create.url()">
                    {{ t('app.landlord.roles.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <ListPage
            :meta="rolesMeta"
        label="função"
        :action="rolesIndexPath"
        :clear-href="rolesIndexPath"
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
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.roles.fields.name') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.roles.fields.type') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.roles.fields.permissions_count') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.landlord.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="rolesLoading">
                    <TableLoadingSkeleton :columns="4" :rows="6" />
                </template>
                <tr v-else-if="rolesRows.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="4">
                        {{ t('app.landlord.common.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="role in rolesRows"
                    :key="role.id"
                    class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                >
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ role.name }}</div>
                    </td>
                    <td class="px-4 py-3">{{ t(`app.landlord.roles.types.${role.type}`) }}</td>
                    <td class="px-4 py-3">{{ role.permissions_count }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <WayfinderLink :href="RoleController.edit.url(role.id)">
                                    {{ t('app.landlord.common.edit') }}
                                </WayfinderLink>
                            </Button>
                            <Button v-if="!role.is_protected" variant="destructive" size="sm" as-child>
                                <WayfinderLink
                                    :href="RoleController.destroy.url(role.id)"
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
