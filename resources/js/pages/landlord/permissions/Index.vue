<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import PermissionController from '@/actions/App/Http/Controllers/Landlord/PermissionController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import type { Paginator } from '@/types';

type PermissionRow = {
    id: string;
    name: string;
    type: string;
    is_protected: boolean;
};

const props = defineProps<{
    permissions: Paginator<PermissionRow>;
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

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.permissions.navigation'),
            href: permissionsIndexPath,
        },
    ],
});
</script>

<template>
    <Head :title="t('app.landlord.permissions.title')" />

    <ListPage
        :title="t('app.landlord.permissions.title')"
        :description="t('app.landlord.permissions.description')"
        :meta="props.permissions"
        label="permissão"
        :action="permissionsIndexPath"
        :clear-href="permissionsIndexPath"
        :search-value="props.filters.search"
        :search-placeholder="t('app.landlord.common.search')"
        :filter-label="t('app.landlord.common.filter')"
        :clear-label="t('app.landlord.common.clear_filters')"
    >
        <template #action>
            <NewActionButton :href="PermissionController.create.url()">
                {{ t('app.landlord.permissions.actions.new') }}
            </NewActionButton>
        </template>

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
                <tr v-if="props.permissions.data.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="3">
                        {{ t('app.landlord.common.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="permission in props.permissions.data"
                    :key="permission.id"
                    class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                >
                    <td class="px-4 py-3 font-medium">{{ permission.name }}</td>
                    <td class="px-4 py-3">{{ t(`app.landlord.roles.types.${permission.type}`) }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="PermissionController.edit.url(permission.id)">
                                    {{ t('app.landlord.common.edit') }}
                                </Link>
                            </Button>
                            <Button v-if="!permission.is_protected" variant="destructive" size="sm" as-child>
                                <Link
                                    :href="PermissionController.destroy.url(permission.id)"
                                    method="delete"
                                    as="button"
                                >
                                    {{ t('app.landlord.common.delete') }}
                                </Link>
                            </Button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </ListPage>
</template>
