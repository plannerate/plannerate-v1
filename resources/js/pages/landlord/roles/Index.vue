<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/Landlord/RoleController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

type RoleRow = {
    id: string;
    name: string;
    type: string;
    permissions_count: number;
    is_protected: boolean;
};

type Paginator<T> = {
    data: T[];
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

            <Button as-child>
                <Link :href="RoleController.create.url()">
                    {{ t('app.landlord.roles.actions.new') }}
                </Link>
            </Button>
        </div>

        <form :action="rolesIndexPath" method="get" class="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-4 dark:border-sidebar-border">
            <input
                name="search"
                :value="filters.search"
                type="text"
                :placeholder="t('app.landlord.common.search')"
                class="h-10 rounded-md border border-input bg-background px-3 text-sm"
            />

            <select
                name="type"
                :value="filters.type"
                class="h-10 rounded-md border border-input bg-background px-3 text-sm"
            >
                <option value="">{{ t('app.landlord.common.all') }}</option>
                <option v-for="type in filter_options.types" :key="type.value" :value="type.value">
                    {{ type.label }}
                </option>
            </select>

            <Button type="submit">{{ t('app.landlord.common.filter') }}</Button>

            <Button variant="outline" as-child>
                <Link :href="rolesIndexPath">{{ t('app.landlord.common.clear_filters') }}</Link>
            </Button>
        </form>

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
    </div>
</template>
