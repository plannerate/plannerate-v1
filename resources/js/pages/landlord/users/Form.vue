<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Landlord/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

    <div class="space-y-6 p-4">
        <Heading
            :title="isEdit ? t('app.landlord.users.actions.edit') : t('app.landlord.users.actions.new')"
            :description="t('app.landlord.users.description')"
        />

        <Form
            v-bind="isEdit ? UserController.update.form(props.user!.id) : UserController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
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

            <div class="space-y-3">
                <Label>{{ t('app.landlord.users.fields.roles') }}</Label>
                <div class="grid gap-2 md:grid-cols-2">
                    <label
                        v-for="role in props.roles"
                        :key="role.id"
                        class="flex items-center gap-2 rounded-md border border-input px-3 py-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="role_ids[]"
                            :value="role.id"
                            :checked="props.user?.role_ids.includes(role.id) ?? false"
                        />
                        <span>{{ role.name }}</span>
                    </label>
                </div>
                <InputError :message="errors.role_ids" />
            </div>

            <div class="flex items-center gap-3">
                <input type="hidden" name="is_active" value="0" />
                <input id="is_active" name="is_active" type="checkbox" value="1" :checked="props.user?.is_active ?? true" />
                <Label for="is_active">{{ t('app.landlord.users.fields.is_active') }}</Label>
                <InputError :message="errors.is_active" />
            </div>

            <div class="flex items-center gap-3">
                <Button :disabled="processing">{{ t('app.actions.save') }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="usersIndexPath">{{ t('app.actions.cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
