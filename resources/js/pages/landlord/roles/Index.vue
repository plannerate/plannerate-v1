<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/Landlord/RoleController';
import Heading from '@/components/Heading.vue';
import ListFiltersBar from '@/components/ListFiltersBar.vue';
import ListPagination from '@/components/ListPagination.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import type { Paginator } from '@/types';

type RoleRow = {
    id: string;
    name: string;
    type: string;
    permissions_count: number;
    is_protected: boolean;
};

defineProps<{
    roles: Paginator<RoleRow>;
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

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.roles.navigation'),
            href: rolesIndexPath,
        },
    ],
});
</script>

<template>
    <Head :title="t('app.landlord.roles.title')" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading :title="t('app.landlord.roles.title')" :description="t('app.landlord.roles.description')" />

            <NewActionButton :href="RoleController.create.url()">
                {{ t('app.landlord.roles.actions.new') }}
            </NewActionButton>
        </div>

        <ListFiltersBar
            :action="rolesIndexPath"
            :clear-href="rolesIndexPath"
            search-name="search"
            :search-value="filters.search"
            :search-placeholder="t('app.landlord.common.search')"
            :filter-label="t('app.landlord.common.filter')"
            :clear-label="t('app.landlord.common.clear_filters')"
        >
            <select
                name="type"
                :value="filters.type"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.landlord.common.all') }}</option>
                <option v-for="type in filter_options.types" :key="type.value" :value="type.value">
                    {{ type.label }}
                </option>
            </select>
        </ListFiltersBar>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
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
                    <tr v-if="roles.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="4">
                            {{ t('app.landlord.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="role in roles.data"
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
                                    <Link :href="RoleController.edit.url(role.id)">
                                        {{ t('app.landlord.common.edit') }}
                                    </Link>
                                </Button>
                                <Button v-if="!role.is_protected" variant="destructive" size="sm" as-child>
                                    <Link
                                        :href="RoleController.destroy.url(role.id)"
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
        </div>

        <ListPagination :meta="roles" label="função" />
    </div>
</template>
