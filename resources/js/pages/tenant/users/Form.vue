<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { UserCog } from 'lucide-vue-next';
import { computed } from 'vue';
import TenantUserController from '@/actions/App/Http/Controllers/Tenant/UserController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import RolesCheckboxList from '@/components/RolesCheckboxList.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';

type UserPayload = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    role_ids: string[];
};

type RoleOption = {
    id: string;
    name: string;
    is_admin: boolean;
};

const props = defineProps<{
    subdomain: string;
    user: UserPayload | null;
    roles: RoleOption[];
    tenant: {
        plan_user_limit: number | null;
        users_count: number;
        limit_message: string | null;
    };
}>();

const { t } = useT();
const isEdit = computed(() => props.user !== null);
const adminLimitReached = computed(
    () => props.tenant.plan_user_limit !== null && props.tenant.users_count >= props.tenant.plan_user_limit,
);
const rolesForField = computed(() =>
    props.roles.map((role) => ({
        value: role.id,
        label: role.name,
        isAdmin: role.is_admin,
    })),
);
const usersIndexPath = TenantUserController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.tenant.users.actions.edit') : t('app.tenant.users.actions.new'),
    title: isEdit.value ? t('app.tenant.users.actions.edit') : t('app.tenant.users.actions.new'),
    description: t('app.tenant.users.description'),
    breadcrumbs: [
        {
            title: t('app.tenant.users.navigation'),
            href: usersIndexPath,
        },
        {
            title: isEdit.value ? t('app.tenant.users.actions.edit') : t('app.tenant.users.actions.new'),
            href: isEdit.value
                ? TenantUserController.edit.url({ subdomain: props.subdomain, user: props.user!.id })
                : TenantUserController.create.url(props.subdomain),
        },
    ],
});

const storeFormAttrs = computed(() => {
    const def = TenantUserController.store.form(props.subdomain);

    return { ...def, action: def.action.replace(/^\/\/[^/]+/, '') };
});

const updateFormAttrs = computed(() => {
    const def = TenantUserController.update.form({
        subdomain: props.subdomain,
        user: props.user!.id,
    });

    return { ...def, action: def.action.replace(/^\/\/[^/]+/, '') };
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="isEdit ? updateFormAttrs : storeFormAttrs"
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="usersIndexPath"
                    :title="pageMeta.title"
                    :description="pageMeta.description"
                >
                    <template #icon>
                        <UserCog class="size-5" />
                    </template>

                    <InputError v-if="errors.limit" :message="errors.limit" />

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">{{ t('app.tenant.users.fields.name') }}</Label>
                            <Input id="name" name="name" :default-value="props.user?.name ?? ''" required />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">{{ t('app.tenant.users.fields.email') }}</Label>
                            <Input id="email" name="email" type="email" :default-value="props.user?.email ?? ''" required />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="password">{{ t('app.tenant.users.fields.password') }}</Label>
                            <Input id="password" name="password" type="password" :required="!isEdit" autocomplete="new-password" />
                            <p v-if="isEdit" class="text-xs text-muted-foreground">{{ t('app.tenant.users.fields.password_hint') }}</p>
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation">{{ t('app.tenant.users.fields.password_confirmation') }}</Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                :required="!isEdit"
                                autocomplete="new-password"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <Label>{{ t('app.tenant.users.fields.roles') }}</Label>
                        <RolesCheckboxList
                            name-attr="role_ids[]"
                            :roles="rolesForField"
                            :selected-values="props.user?.role_ids"
                            :admin-limit-reached="adminLimitReached"
                            :error="errors.role_ids"
                        />
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                        <input type="hidden" name="is_active" value="0" />
                        <input id="is_active" name="is_active" type="checkbox" value="1" :checked="props.user?.is_active ?? true" class="accent-primary" />
                        <div>
                            <span class="text-sm font-medium">{{ t('app.tenant.users.fields.is_active') }}</span>
                            <p class="text-xs text-muted-foreground">{{ t('app.tenant.users.fields.is_active_hint') }}</p>
                        </div>
                        <InputError :message="errors.is_active" />
                    </label>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>
