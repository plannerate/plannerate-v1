<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Landlord/RoleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type RolePayload = {
    id: string;
    type: string;
    name: string;
    permissions: string[];
    is_protected: boolean;
};

type PermissionOption = {
    name: string;
    type: string;
};

type TypeOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    role: RolePayload | null;
    types: TypeOption[];
    permissions: PermissionOption[];
}>();

const { t } = useT();
const isEdit = computed(() => props.role !== null);
const isProtected = computed(() => props.role?.is_protected ?? false);
const rolesIndexPath = RoleController.index.url().replace(/^\/\/[^/]+/, '');
const selectedType = ref(props.role?.type ?? props.types[0]?.value ?? 'landlord');
const filteredPermissions = computed(() => {
    return props.permissions.filter((permission) => permission.type === selectedType.value);
});

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.roles.navigation'),
            href: rolesIndexPath,
        },
        {
            title: isEdit.value ? t('app.landlord.common.edit') : t('app.landlord.common.create'),
            href: isEdit.value ? RoleController.edit.url(props.role!.id) : RoleController.create.url(),
        },
    ],
});
</script>

<template>
    <Head :title="isEdit ? t('app.landlord.roles.actions.edit') : t('app.landlord.roles.actions.new')" />

    <div class="space-y-6 p-4">
        <Heading
            :title="isEdit ? t('app.landlord.roles.actions.edit') : t('app.landlord.roles.actions.new')"
            :description="t('app.landlord.roles.description')"
        />

        <Form
            v-bind="isEdit ? RoleController.update.form(props.role!.id) : RoleController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="type">{{ t('app.landlord.roles.fields.type') }}</Label>
                <select
                    id="type"
                    name="type"
                    v-model="selectedType"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    :disabled="isProtected"
                    required
                >
                    <option v-for="type in props.types" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
                <InputError :message="errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="name">{{ t('app.landlord.roles.fields.name') }}</Label>
                <Input id="name" name="name" :default-value="props.role?.name ?? ''" required :disabled="isProtected" />
                <InputError :message="errors.name" />
            </div>

            <div class="space-y-3">
                <Label>{{ t('app.landlord.roles.fields.permissions') }}</Label>
                <div class="grid gap-2 md:grid-cols-2">
                    <label
                        v-for="permission in filteredPermissions"
                        :key="permission.name"
                        class="flex items-center gap-2 rounded-md border border-input px-3 py-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="permissions[]"
                            :value="permission.name"
                            :checked="props.role?.permissions.includes(permission.name) ?? false"
                            :disabled="isProtected"
                        />
                        <span>{{ permission.name }}</span>
                    </label>
                </div>
                <InputError :message="errors.permissions" />
            </div>

            <div class="flex items-center gap-3">
                <Button :disabled="processing || isProtected">{{ t('app.actions.save') }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="rolesIndexPath">{{ t('app.actions.cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
