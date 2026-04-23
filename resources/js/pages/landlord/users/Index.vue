<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Landlord/UserController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import type { Paginator } from '@/types';

type UserRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    roles: string[];
};

const props = defineProps<{
    users: Paginator<UserRow>;
    filters: {
        search: string;
        is_active: string;
        role_id: string;
    };
    filter_options: {
        roles: Array<{ id: string; name: string }>;
    };
}>();

const { t } = useT();
const usersIndexPath = UserController.index.url().replace(/^\/\/[^/]+/, '');

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.users.navigation'),
            href: usersIndexPath,
        },
    ],
});
</script>

<template>
    <Head :title="t('app.landlord.users.title')" />

    <ListPage
        :title="t('app.landlord.users.title')"
        :description="t('app.landlord.users.description')"
        :meta="props.users"
        label="usuário"
        :action="usersIndexPath"
        :clear-href="usersIndexPath"
        :search-value="props.filters.search"
        :search-placeholder="t('app.landlord.common.search')"
        :filter-label="t('app.landlord.common.filter')"
        :clear-label="t('app.landlord.common.clear_filters')"
    >
        <template #action>
            <NewActionButton :href="UserController.create.url()">
                {{ t('app.landlord.users.actions.new') }}
            </NewActionButton>
        </template>

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

            <select
                name="role_id"
                :value="props.filters.role_id"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.landlord.common.all') }}</option>
                <option v-for="role in props.filter_options.roles" :key="role.id" :value="role.id">
                    {{ role.name }}
                </option>
            </select>
        </template>

        <table class="w-full text-sm">
            <thead class="bg-muted/30 text-left text-muted-foreground">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.users.fields.name') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.users.fields.email') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.users.fields.roles') }}</th>
                    <th class="px-4 py-3 font-medium">{{ t('app.landlord.users.fields.is_active') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ t('app.landlord.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="props.users.data.length === 0">
                    <td class="px-4 py-6 text-muted-foreground" colspan="5">
                        {{ t('app.landlord.common.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="user in props.users.data"
                    :key="user.id"
                    class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                >
                    <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                    <td class="px-4 py-3">{{ user.email }}</td>
                    <td class="px-4 py-3">{{ user.roles.length > 0 ? user.roles.join(', ') : '-' }}</td>
                    <td class="px-4 py-3">{{ user.is_active ? t('app.landlord.common.active') : t('app.landlord.common.inactive') }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="UserController.edit.url(user.id)">
                                    {{ t('app.landlord.common.edit') }}
                                </Link>
                            </Button>
                            <Button variant="destructive" size="sm" as-child>
                                <Link
                                    :href="UserController.destroy.url(user.id)"
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
