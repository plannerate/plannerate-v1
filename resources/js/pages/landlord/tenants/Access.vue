<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
};

type UserAccessRow = {
    id: string;
    name: string;
    email: string;
    role_names: string[];
};

type RoleOption = {
    id: string;
    name: string;
};

const props = defineProps<{
    tenant: TenantPayload;
    users: UserAccessRow[];
    roles: RoleOption[];
}>();

const { t } = useT();
const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');

const selectedUserId = ref<string>(props.users[0]?.id ?? '');

const selectedUser = computed<UserAccessRow | null>(() => {
    return props.users.find((user) => user.id === selectedUserId.value) ?? null;
});

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

        <div v-if="props.users.length === 0" class="rounded-lg border border-input p-4 text-sm text-muted-foreground">
            {{ t('app.landlord.tenant_access.no_user') }}
        </div>

        <Form
            v-else
            :key="selectedUserId"
            v-bind="TenantUserAccessController.update.form(props.tenant.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="user_id">{{ t('app.landlord.tenant_access.select_user') }}</Label>
                <select
                    id="user_id"
                    name="user_id"
                    v-model="selectedUserId"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    required
                >
                    <option v-for="user in props.users" :key="user.id" :value="user.id">
                        {{ user.name }} ({{ user.email }})
                    </option>
                </select>
                <InputError :message="errors.user_id" />
            </div>

            <div class="space-y-2">
                <Label>{{ t('app.landlord.tenant_access.current_roles') }}</Label>
                <div class="flex flex-wrap gap-2">
                    <Badge v-if="!selectedUser || selectedUser.role_names.length === 0" variant="outline">
                        {{ t('app.landlord.tenant_access.none') }}
                    </Badge>
                    <Badge v-for="roleName in selectedUser?.role_names ?? []" :key="roleName" variant="secondary">
                        {{ roleName }}
                    </Badge>
                </div>
            </div>

            <div class="space-y-3">
                <Label>{{ t('app.landlord.tenant_access.roles') }}</Label>
                <div class="grid gap-2 md:grid-cols-2">
                    <label
                        v-for="role in props.roles"
                        :key="role.id"
                        class="flex items-center gap-2 rounded-md border border-input px-3 py-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="roles[]"
                            :value="role.name"
                            :checked="selectedUser?.role_names.includes(role.name) ?? false"
                        />
                        <span>{{ role.name }}</span>
                    </label>
                </div>
                <InputError :message="errors.roles" />
            </div>

            <div class="flex items-center gap-3">
                <Button :disabled="processing">{{ t('app.actions.save') }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="tenantsIndexPath">{{ t('app.actions.cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
