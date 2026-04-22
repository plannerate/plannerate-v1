<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { UserCog } from 'lucide-vue-next';
import UserController from '@/actions/App/Http/Controllers/Landlord/UserController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

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
};

const props = defineProps<{
    user: UserPayload | null;
    roles: RoleOption[];
}>();

const { t } = useT();
const isEdit = computed(() => props.user !== null);
const usersIndexPath = UserController.index.url().replace(/^\/\/[^/]+/, '');

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.users.navigation'),
            href: usersIndexPath,
        },
        {
            title: isEdit.value ? t('app.landlord.users.actions.edit') : t('app.landlord.users.actions.new'),
            href: isEdit.value ? UserController.edit.url(props.user!.id) : UserController.create.url(),
        },
    ],
});
</script>

<template>
    <Head :title="isEdit ? t('app.landlord.users.actions.edit') : t('app.landlord.users.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit ? UserController.update.form(props.user!.id) : UserController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.landlord.users.actions.edit') : t('app.landlord.users.actions.new')"
                :description="t('app.landlord.users.description')"
                :processing="processing"
                :cancel-href="usersIndexPath"
            >
                <template #icon>
                    <UserCog class="size-5" />
                </template>

                <!-- Identity -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.landlord.users.fields.name') }}</Label>
                        <Input id="name" name="name" :default-value="props.user?.name ?? ''" required />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ t('app.landlord.users.fields.email') }}</Label>
                        <Input id="email" name="email" type="email" :default-value="props.user?.email ?? ''" required />
                        <InputError :message="errors.email" />
                    </div>
                </div>

                <!-- Password -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password">{{ t('app.landlord.users.fields.password') }}</Label>
                        <Input id="password" name="password" type="password" :required="!isEdit" autocomplete="new-password" />
                        <p v-if="isEdit" class="text-xs text-muted-foreground">{{ t('app.landlord.users.fields.password_hint') }}</p>
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">{{ t('app.landlord.users.fields.password_confirmation') }}</Label>
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

                <!-- Roles -->
                <div class="space-y-3">
                    <Label>{{ t('app.landlord.users.fields.roles') }}</Label>
                    <div class="grid gap-2 md:grid-cols-2">
                        <label
                            v-for="role in props.roles"
                            :key="role.id"
                            class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-border px-3 py-2.5 text-sm transition-colors hover:bg-muted/40 has-checked:border-primary/50 has-checked:bg-primary/5"
                        >
                            <input
                                type="checkbox"
                                name="role_ids[]"
                                :value="role.id"
                                :checked="props.user?.role_ids.includes(role.id) ?? false"
                                class="accent-primary"
                            />
                            <span>{{ role.name }}</span>
                        </label>
                    </div>
                    <InputError :message="errors.role_ids" />
                </div>

                <!-- Active -->
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                    <input type="hidden" name="is_active" value="0" />
                    <input id="is_active" name="is_active" type="checkbox" value="1" :checked="props.user?.is_active ?? true" class="accent-primary" />
                    <div>
                        <span class="text-sm font-medium">{{ t('app.landlord.users.fields.is_active') }}</span>
                        <p class="text-xs text-muted-foreground">Usuários inativos não conseguem fazer login.</p>
                    </div>
                    <InputError :message="errors.is_active" />
                </label>
            </FormCard>
        </Form>
    </div>
</template>
