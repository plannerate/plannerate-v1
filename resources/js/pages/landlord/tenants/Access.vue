<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import FilterActionButton from '@/components/FilterActionButton.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useT } from '@/composables/useT';

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

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginator<T> = {
    data: T[];
    links: PaginatorLink[];
    from: number | null;
    to: number | null;
    total: number;
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

    return props.users.data.find((user) => user.id === selectedUserId.value) ?? null;
});

const usersLimitText = computed(() => {
    return props.tenant.plan_user_limit === null ? '-' : props.tenant.plan_user_limit;
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

function getUserInitials(name: string): string {
    const tokens = name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

    if (tokens.length === 0) {
        return 'US';
    }

    return tokens.map((part) => part.charAt(0).toUpperCase()).join('');
}

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantsIndexPath,
        },
        {
            title: props.tenant.name,
            href: TenantController.edit.url(props.tenant.id),
        },
        {
            title: t('app.landlord.tenant_access.title'),
            href: TenantUserAccessController.edit.url(props.tenant.id),
        },
    ],
});
</script>

<template>
    <Head :title="`${t('app.landlord.tenant_access.title')} - ${props.tenant.name}`" />

    <div class="space-y-6 p-4">
        <Heading
            :title="`${t('app.landlord.tenant_access.title')} - ${props.tenant.name}`"
            :description="t('app.landlord.tenant_access.description')"
        />

        <div class="grid gap-3 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">{{ t('app.landlord.tenant_access.users_count') }}</CardTitle>
                </CardHeader>
                <CardContent class="pt-0 text-2xl font-semibold">
                    {{ props.tenant.users_count }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">{{ t('app.landlord.tenant_access.users_limit') }}</CardTitle>
                </CardHeader>
                <CardContent class="pt-0 text-2xl font-semibold">
                    {{ usersLimitText }}
                </CardContent>
            </Card>

            <Card class="flex items-center justify-between p-4">
                <div class="text-sm text-muted-foreground">
                    {{ props.tenant.users_count }} / {{ usersLimitText }}
                </div>
                <Button
                    variant="gradient"
                    size="pill-sm"
                    :disabled="!props.tenant.can_create_users"
                    @click="openCreateDrawer"
                >
                    {{ t('app.landlord.tenant_access.create_user') }}
                </Button>
            </Card>
        </div>

        <div
            v-if="props.tenant.limit_message"
            class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
            {{ props.tenant.limit_message }}
        </div>

        <form
            :action="TenantUserAccessController.edit.url(props.tenant.id)"
            method="get"
            class="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-6 dark:border-sidebar-border"
        >
            <Input
                name="search"
                :default-value="props.filters.search"
                :placeholder="t('app.landlord.tenant_access.search')"
                class="h-10 rounded-xl border-sidebar-border/70 bg-background/70 text-sm shadow-none focus-visible:border-primary/40 focus-visible:ring-primary/30 dark:border-sidebar-border md:col-span-2"
            />

            <select
                name="status"
                :value="props.filters.status"
                class="h-10 rounded-xl border border-sidebar-border/70 bg-background/70 px-3 text-sm outline-none transition-[color,box-shadow] focus-visible:border-primary/40 focus-visible:ring-3 focus-visible:ring-primary/30 dark:border-sidebar-border md:col-span-2"
            >
                <option v-for="option in props.status_options" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>

            <div class="flex items-center gap-2 md:col-span-2">
                <FilterActionButton class="w-full">
                    {{ t('app.landlord.common.filter') }}
                </FilterActionButton>
                <Button variant="outline" size="pill-sm" as-child class="w-full rounded-xl border-sidebar-border/70 bg-background/70 shadow-none hover:bg-background dark:border-sidebar-border">
                    <Link :href="TenantUserAccessController.edit.url(props.tenant.id)">
                        {{ t('app.landlord.common.clear_filters') }}
                    </Link>
                </Button>
            </div>
        </form>

        <div v-if="props.users.data.length === 0" class="rounded-lg border border-input p-4 text-sm text-muted-foreground">
            {{ t('app.landlord.tenant_access.no_user') }}
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card v-for="user in props.users.data" :key="user.id">
                <CardContent class="flex h-full flex-col justify-between gap-5 p-4">
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <Avatar class="size-11 border border-sidebar-border/70 dark:border-sidebar-border">
                                <AvatarFallback class="bg-primary/15 text-sm font-semibold text-primary">
                                    {{ getUserInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="truncate text-base font-semibold">{{ user.name }}</div>
                                    <Badge v-if="user.deleted_at" variant="destructive">{{ t('app.landlord.tenant_access.statuses.deleted') }}</Badge>
                                    <Badge v-else-if="user.is_active" variant="secondary">{{ t('app.landlord.common.active') }}</Badge>
                                    <Badge v-else variant="outline">{{ t('app.landlord.common.inactive') }}</Badge>
                                </div>
                                <div class="truncate text-sm text-muted-foreground">{{ user.email }}</div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Badge v-if="user.role_names.length === 0" variant="outline">{{ t('app.landlord.tenant_access.none') }}</Badge>
                            <Badge v-for="roleName in user.role_names" :key="roleName" variant="secondary">
                                {{ roleName }}
                            </Badge>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t border-sidebar-border/50 pt-3 dark:border-sidebar-border/60">
                        <Button
                            v-if="!user.deleted_at"
                            variant="outline"
                            size="sm"
                            @click="openEditDrawer(user.id)"
                        >
                            {{ t('app.landlord.common.edit') }}
                        </Button>

                        <Button
                            v-if="!user.deleted_at"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="TenantUserAccessController.toggleActive.url({ tenant: props.tenant.id, userId: user.id })"
                                method="patch"
                                as="button"
                                :data="{ is_active: user.is_active ? 0 : 1 }"
                            >
                                {{ user.is_active ? t('app.landlord.common.inactive') : t('app.landlord.common.active') }}
                            </Link>
                        </Button>

                        <Button
                            v-if="!user.deleted_at"
                            variant="destructive"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="TenantUserAccessController.destroy.url({ tenant: props.tenant.id, userId: user.id })"
                                method="delete"
                                as="button"
                            >
                                {{ t('app.landlord.common.delete') }}
                            </Link>
                        </Button>

                        <Button
                            v-if="user.deleted_at"
                            variant="secondary"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="TenantUserAccessController.restore.url({ tenant: props.tenant.id, userId: user.id })"
                                method="patch"
                                as="button"
                            >
                                {{ t('app.actions.restore') }}
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="props.users.links.length > 3"
            class="flex flex-wrap items-center gap-2"
        >
            <template v-for="link in props.users.links" :key="link.label">
                <Button
                    v-if="link.url"
                    :variant="link.active ? 'secondary' : 'outline'"
                    size="sm"
                    as-child
                >
                    <Link :href="link.url">
                        <span v-html="link.label" />
                    </Link>
                </Button>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    disabled
                >
                    <span v-html="link.label" />
                </Button>
            </template>
        </div>
    </div>

    <Sheet v-model:open="isDrawerOpen">
        <SheetContent class="w-full p-0 sm:max-w-lg">
            <div class="flex h-full flex-col">
                <div class="shrink-0 border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                    <SheetHeader class="space-y-0 text-left">
                        <SheetTitle>
                            {{ drawerMode === 'create' ? t('app.landlord.tenant_access.create_user') : t('app.landlord.tenant_access.edit_user') }}
                        </SheetTitle>
                    </SheetHeader>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <Form
                        v-if="drawerMode === 'create'"
                        v-bind="TenantUserAccessController.store.form(props.tenant.id)"
                        class="flex min-h-full flex-col"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-6">
                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="create_name">{{ t('app.landlord.users.fields.name') }}</Label>
                                    <Input id="create_name" name="name" required />
                                    <InputError :message="errors.name" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="create_email">{{ t('app.landlord.users.fields.email') }}</Label>
                                    <Input id="create_email" name="email" type="email" required />
                                    <InputError :message="errors.email" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="create_password">{{ t('app.landlord.users.fields.password') }}</Label>
                                    <Input id="create_password" name="password" type="password" required />
                                    <InputError :message="errors.password" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="create_password_confirmation">{{ t('app.landlord.users.fields.password_confirmation') }}</Label>
                                    <Input id="create_password_confirmation" name="password_confirmation" type="password" required />
                                    <InputError :message="errors.password_confirmation" />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <Label>{{ t('app.landlord.tenant_access.roles') }}</Label>
                                <div class="grid gap-2">
                                    <label
                                        v-for="role in props.roles"
                                        :key="role.id"
                                        class="flex items-center gap-2 rounded-md border border-input px-3 py-2 text-sm"
                                    >
                                        <input type="checkbox" name="role_names[]" :value="role.name" />
                                        <span>{{ role.name }}</span>
                                    </label>
                                </div>
                                <InputError :message="errors.role_names" />
                            </div>

                            <div class="flex items-center gap-3">
                                <input type="hidden" name="is_active" value="0" />
                                <input id="create_is_active" name="is_active" type="checkbox" value="1" checked />
                                <Label for="create_is_active">{{ t('app.landlord.users.fields.is_active') }}</Label>
                            </div>

                            <InputError :message="errors.limit" />
                        </div>

                        <div
                            class="sticky bottom-0 z-10 -mx-6 mt-6 border-t border-sidebar-border/70 bg-background/95 px-6 py-4 backdrop-blur dark:border-sidebar-border"
                        >
                            <div class="flex items-center gap-3">
                                <Button :disabled="processing || !props.tenant.can_create_users">{{ t('app.actions.save') }}</Button>
                                <Button type="button" variant="outline" @click="isDrawerOpen = false">{{ t('app.actions.cancel') }}</Button>
                            </div>
                        </div>
                    </Form>

                    <Form
                        v-else-if="selectedUser"
                        v-bind="TenantUserAccessController.update.form({ tenant: props.tenant.id, userId: selectedUser.id })"
                        class="flex min-h-full flex-col"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-6">
                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="edit_name">{{ t('app.landlord.users.fields.name') }}</Label>
                                    <Input id="edit_name" name="name" :default-value="selectedUser.name" required />
                                    <InputError :message="errors.name" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="edit_email">{{ t('app.landlord.users.fields.email') }}</Label>
                                    <Input id="edit_email" name="email" type="email" :default-value="selectedUser.email" required />
                                    <InputError :message="errors.email" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="edit_password">{{ t('app.landlord.users.fields.password') }}</Label>
                                    <Input id="edit_password" name="password" type="password" />
                                    <p class="text-xs text-muted-foreground">{{ t('app.landlord.users.fields.password_hint') }}</p>
                                    <InputError :message="errors.password" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="edit_password_confirmation">{{ t('app.landlord.users.fields.password_confirmation') }}</Label>
                                    <Input id="edit_password_confirmation" name="password_confirmation" type="password" />
                                    <InputError :message="errors.password_confirmation" />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <Label>{{ t('app.landlord.tenant_access.roles') }}</Label>
                                <div class="grid gap-2">
                                    <label
                                        v-for="role in props.roles"
                                        :key="role.id"
                                        class="flex items-center gap-2 rounded-md border border-input px-3 py-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="role_names[]"
                                            :value="role.name"
                                            :checked="selectedUser.role_names.includes(role.name)"
                                        />
                                        <span>{{ role.name }}</span>
                                    </label>
                                </div>
                                <InputError :message="errors.role_names" />
                            </div>

                            <div class="flex items-center gap-3">
                                <input type="hidden" name="is_active" value="0" />
                                <input id="edit_is_active" name="is_active" type="checkbox" value="1" :checked="selectedUser.is_active" />
                                <Label for="edit_is_active">{{ t('app.landlord.users.fields.is_active') }}</Label>
                            </div>
                        </div>

                        <div
                            class="sticky bottom-0 z-10 -mx-6 mt-6 border-t border-sidebar-border/70 bg-background/95 px-6 py-4 backdrop-blur dark:border-sidebar-border"
                        >
                            <div class="flex items-center gap-3">
                                <Button :disabled="processing">{{ t('app.actions.save') }}</Button>
                                <Button type="button" variant="outline" @click="isDrawerOpen = false">{{ t('app.actions.cancel') }}</Button>
                            </div>
                        </div>
                    </Form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
