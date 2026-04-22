<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PermissionController from '@/actions/App/Http/Controllers/Landlord/PermissionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type PermissionPayload = {
    id: string;
    type: string;
    name: string;
    is_protected: boolean;
};

type TypeOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    permission: PermissionPayload | null;
    types: TypeOption[];
}>();

const { t } = useT();
const isEdit = computed(() => props.permission !== null);
const isProtected = computed(() => props.permission?.is_protected ?? false);
const permissionsIndexPath = PermissionController.index.url().replace(/^\/\/[^/]+/, '');
const selectedType = ref(props.permission?.type ?? props.types[0]?.value ?? 'landlord');

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.permissions.navigation'),
            href: permissionsIndexPath,
        },
        {
            title: isEdit.value ? t('app.landlord.permissions.actions.edit') : t('app.landlord.permissions.actions.new'),
            href: isEdit.value ? PermissionController.edit.url(props.permission!.id) : PermissionController.create.url(),
        },
    ],
});
</script>

<template>
    <Head :title="isEdit ? t('app.landlord.permissions.actions.edit') : t('app.landlord.permissions.actions.new')" />

    <div class="space-y-6 p-4">
        <Heading
            :title="isEdit ? t('app.landlord.permissions.actions.edit') : t('app.landlord.permissions.actions.new')"
            :description="t('app.landlord.permissions.description')"
        />

        <Form
            v-bind="isEdit ? PermissionController.update.form(props.permission!.id) : PermissionController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="type">{{ t('app.landlord.permissions.fields.type') }}</Label>
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
                <Label for="name">{{ t('app.landlord.permissions.fields.name') }}</Label>
                <Input id="name" name="name" :default-value="props.permission?.name ?? ''" :disabled="isProtected" required />
                <InputError :message="errors.name" />
            </div>

            <div class="flex items-center gap-3">
                <Button :disabled="processing || isProtected">{{ t('app.actions.save') }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="permissionsIndexPath">{{ t('app.actions.cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
