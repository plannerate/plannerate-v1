<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TenantUserController from '@/actions/App/Http/Controllers/Tenant/UserController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import ResendPasswordSetupButton from '@/components/ResendPasswordSetupButton.vue';
import { ColumnActions } from '@/components/table/columns';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type UserRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    roles: string[];
    trashed: boolean;
};

const props = defineProps<{
    users?: Paginator<UserRow>;
    filters: {
        search: string;
        is_active: string;
        trashed: 'without' | 'only' | 'with';
    };
    tenant: {
        plan_user_limit: number | null;
        users_count: number;
        limit_message: string | null;
    };

    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}>();

const { t } = useT();
const usersIndexPath = TenantUserController.index.url().replace(/^\/\/[^/]+/, '');
const { meta: usersMeta, rows: usersRows, loading: usersLoading } = useDeferredPaginator(() => props.users, 10);
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.users.title'),
    title: t('app.tenant.users.title'),
    description: t('app.tenant.users.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.tenant.users.navigation'),
            href: usersIndexPath,
        },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">

        <Head :title="pageMeta.headTitle" />
        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton v-if="props.can.create" :href="TenantUserController.create.url()">
                    {{ t('app.tenant.users.actions.new') }}
                </NewActionButton>
            </div>
        </template>

        <div class="space-y-4">
            <Alert v-if="props.tenant.limit_message" variant="destructive">
                <AlertTitle>{{ t('app.tenant.users.limit.title') }}</AlertTitle>
                <AlertDescription>
                    {{ props.tenant.limit_message }}
                </AlertDescription>
            </Alert>

            <ListPage :meta="usersMeta" label="usuário" :action="usersIndexPath" :clear-href="usersIndexPath"
                :search-value="props.filters.search" :search-placeholder="t('app.tenant.common.search')"
                :filter-label="t('app.tenant.common.filter')" :clear-label="t('app.tenant.common.clear_filters')"
                :trashed-value="props.filters.trashed">
                <template #filters>
                    <select name="is_active" :value="props.filters.is_active"
                        class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                        <option value="">{{ t('app.tenant.common.all') }}</option>
                        <option value="1">{{ t('app.tenant.common.active') }}</option>
                        <option value="0">{{ t('app.tenant.common.inactive') }}</option>
                    </select>
                </template>

                <table class="w-full text-sm">
                    <thead class="bg-muted/30 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('app.tenant.users.fields.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('app.tenant.users.fields.email') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('app.tenant.users.fields.roles') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('app.tenant.users.fields.is_active') }}</th>
                            <th class="px-4 py-3 font-medium ">{{ t('app.tenant.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="usersLoading">
                            <TableLoadingSkeleton :columns="5" :rows="6" />
                        </template>
                        <tr v-else-if="usersRows.length === 0">
                            <td class="px-4 py-6 text-muted-foreground" colspan="5">
                                {{ t('app.tenant.common.empty') }}
                            </td>
                        </tr>
                        <tr v-for="user in usersRows" :key="user.id"
                            class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border">
                            <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-4 py-3">{{ user.email }}</td>
                            <td class="px-4 py-3">{{ user.roles.length > 0 ? user.roles.join(', ') : '-' }}</td>
                            <td class="px-4 py-3">{{ user.is_active ? t('app.tenant.common.active') :
                                t('app.tenant.common.inactive') }}</td>
                            <td class="px-4 py-3 ">
                                <ColumnActions
                                    :edit-href="TenantUserController.edit.url({ user: user.id })"
                                    :delete-href="TenantUserController.destroy.url({ user: user.id })"
                                    :delete-label="user.name"
                                    :require-confirm-word="true"
                                    :is-trashed="user.trashed"
                                    :restore-href="TenantUserController.restore.url({ user: user.id })"
                                    :can-edit="can.update"
                                    :can-delete="can.delete"
                                    :can-restore="can.delete"
                                >
                                    <ResendPasswordSetupButton
                                        v-if="user.is_active && can.update"
                                        variant="button"
                                        :resend-url="TenantUserController.resendPasswordSetup.url({ user: user.id }).replace(/^\/\/[^/]+/, '')"
                                        :user-name="user.name"
                                    />
                                </ColumnActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </ListPage>
        </div>
    </AppLayout>
</template>
