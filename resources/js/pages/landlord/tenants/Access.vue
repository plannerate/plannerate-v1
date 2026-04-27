<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, UserX } from 'lucide-vue-next';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController'; 
import ListPagination from '@/components/ListPagination.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import type { Paginator } from '@/types';
import AccessFiltersBar from './access/AccessFiltersBar.vue';
import AccessStatsCards from './access/AccessStatsCards.vue';
import AccessUserCard from './access/AccessUserCard.vue';
import AccessUserSheet from './access/AccessUserSheet.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
    plan_user_limit: number | null;
    users_count: number;
    can_create_users: boolean;
    limit_message: string | null;
};

type UserAccessRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    deleted_at: string | null;
    role_names: string[];
};

type RoleOption = {
    id: string;
    name: string;
};

type FilterOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    tenant: TenantPayload;
    users: Paginator<UserAccessRow>;
    roles: RoleOption[];
    filters: {
        search: string;
        status: string;
    };
    status_options: FilterOption[];
}>();

const { t } = useT();
const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');
const isDrawerOpen = ref(false);
const drawerMode = ref<'create' | 'edit'>('create');
const selectedUserId = ref<string | null>(null);

const selectedUser = computed<UserAccessRow | null>(() => {
    if (!selectedUserId.value) {
        return null;
    }

    return (
        props.users.data.find((user) => user.id === selectedUserId.value) ??
        null
    );
});

function openCreateDrawer(): void {
    drawerMode.value = 'create';
    selectedUserId.value = null;
    isDrawerOpen.value = true;
}

function openEditDrawer(userId: string): void {
    drawerMode.value = 'edit';
    selectedUserId.value = userId;
    isDrawerOpen.value = true;
}

const pageMeta = useCrudPageMeta({
    headTitle: `${t('app.landlord.tenant_access.title')} - ${props.tenant.name}`,
    title: `${t('app.landlord.tenant_access.title')} - ${props.tenant.name}`,
    description: t('app.landlord.tenant_access.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantsIndexPath,
        },
        {
            title: t('app.landlord.tenant_access.title'),
            href: TenantUserAccessController.edit.url(props.tenant.id),
        },
    ],
});
</script>

<template>
    <Head
        :title="`${t('app.landlord.tenant_access.title')} - ${props.tenant.name}`"
    />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <!-- Page header: title + create button -->
        <template #header-actions>
            <div class="flex items-end justify-between gap-4">
                <div></div>
                <Button
                    variant="gradient"
                    size="pill-sm"
                    class="shrink-0"
                    :disabled="!props.tenant.can_create_users"
                    @click="openCreateDrawer"
                >
                    <Plus class="size-4" />
                    {{ t('app.landlord.tenant_access.create_user') }}
                </Button>
            </div>
        </template>
        <div class="space-y-6 p-4">
            <AccessStatsCards :tenant="props.tenant" />

            <div
                v-if="props.tenant.limit_message"
                class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                {{ props.tenant.limit_message }}
            </div>

            <AccessFiltersBar
                :tenant-id="props.tenant.id"
                :filters="props.filters"
                :status-options="props.status_options"
                :users-from="props.users.from"
                :users-to="props.users.to"
                :users-total="props.users.total"
                :filter-label="t('app.landlord.common.filter')"
                :clear-label="t('app.landlord.common.clear_filters')"
            />

            <!-- Empty state -->
            <div
                v-if="props.users.data.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border p-16 text-center"
            >
                <div
                    class="mb-4 flex size-16 items-center justify-center rounded-full bg-muted"
                >
                    <UserX class="size-8 text-muted-foreground" />
                </div>
                <p class="font-semibold text-muted-foreground">
                    Nenhum usuário encontrado
                </p>
                <p class="mt-1 text-sm text-muted-foreground/70">
                    Tente ajustar os filtros ou adicione um novo usuário.
                </p>
            </div>

            <!-- User cards grid -->
            <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <AccessUserCard
                    v-for="user in props.users.data"
                    :key="user.id"
                    :user="user"
                    :tenant-id="props.tenant.id"
                    @edit="openEditDrawer"
                />

                <!-- Add user placeholder card -->
                <button
                    v-if="props.tenant.can_create_users"
                    class="group flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border p-10 text-muted-foreground/50 transition-all hover:border-muted-foreground/40 hover:text-muted-foreground"
                    @click="openCreateDrawer"
                >
                    <div
                        class="mb-4 flex size-16 items-center justify-center rounded-full bg-muted/50 transition-transform group-hover:scale-110"
                    >
                        <Plus class="size-7" />
                    </div>
                    <p class="text-sm font-bold">
                        {{ t('app.landlord.tenant_access.create_user') }}
                    </p>
                    <p v-if="props.tenant.plan_user_limit" class="mt-1 text-xs">
                        Sua conta permite mais
                        {{
                            props.tenant.plan_user_limit -
                            props.tenant.users_count
                        }}
                        usuário(s)
                    </p>
                </button>
            </div>

            <ListPagination :meta="props.users" label="usuário" />
        </div>

        <AccessUserSheet
            v-model:open="isDrawerOpen"
            :mode="drawerMode"
            :user="selectedUser"
            :tenant="props.tenant"
            :roles="props.roles"
        />
    </AppLayout>
</template>
