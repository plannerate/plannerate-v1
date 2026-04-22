<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Landlord/UserController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

type UserRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    roles: string[];
};

type Paginator<T> = {
    data: T[];
};

defineProps<{
    users: Paginator<UserRow>;
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

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading :title="t('app.landlord.users.title')" :description="t('app.landlord.users.description')" />

            <Button as-child>
                <Link :href="UserController.create.url()">
                    {{ t('app.landlord.users.actions.new') }}
                </Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
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
                    <tr v-if="users.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="5">
                            {{ t('app.landlord.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="user in users.data"
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
        </div>
    </div>
</template>
