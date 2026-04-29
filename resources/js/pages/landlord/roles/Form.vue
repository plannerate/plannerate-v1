<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Landlord/RoleController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';

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

const filteredPermissions = computed(() =>
    props.permissions.filter((p) => p.type === selectedType.value),
);

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.landlord.roles.actions.edit') : t('app.landlord.roles.actions.new'),
    title: isEdit.value ? t('app.landlord.roles.actions.edit') : t('app.landlord.roles.actions.new'),
    description: t('app.landlord.roles.description'),
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
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
        <Form
            v-bind="isEdit ? RoleController.update.form(props.role!.id) : RoleController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :processing="processing"
                :disabled="isProtected"
                :cancel-href="rolesIndexPath"
            >
                <template #icon>
                    <ShieldCheck class="size-5" />
                </template>

                <template v-if="isProtected" #header-extra>
                    <Badge variant="secondary" class="gap-1.5 text-xs">
                        <ShieldCheck class="size-3" />
                        {{ t('app.landlord.common.protected') }}
                    </Badge>
                </template>

                <template v-if="isProtected" #before>
                    <div class="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-600 dark:text-amber-400">
                        <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                        <span>{{ t('app.landlord.roles.protected') }}</span>
                    </div>
                </template>

                <!-- Type -->
                <div class="grid gap-2">
                    <Label for="type">{{ t('app.landlord.roles.fields.type') }}</Label>
                    <select
                        id="type"
                        name="type"
                        v-model="selectedType"
                        :disabled="isProtected"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
                        required
                    >
                        <option v-for="type in props.types" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <!-- Name -->
                <div class="grid gap-2">
                    <Label for="name">{{ t('app.landlord.roles.fields.name') }}</Label>
                    <Input id="name" name="name" :default-value="props.role?.name ?? ''" :disabled="isProtected" required />
                    <InputError :message="errors.name" />
                </div>

                <!-- Permissions -->
                <div class="space-y-3">
                    <Label>{{ t('app.landlord.roles.fields.permissions') }}</Label>
                    <div class="grid gap-2 md:grid-cols-2">
                        <label
                            v-for="permission in filteredPermissions"
                            :key="permission.name"
                            class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-border px-3 py-2.5 text-sm transition-colors hover:bg-muted/40 has-checked:border-primary/50 has-checked:bg-primary/5"
                        >
                            <input
                                type="checkbox"
                                name="permissions[]"
                                :value="permission.name"
                                :checked="props.role?.permissions.includes(permission.name) ?? false"
                                :disabled="isProtected"
                                class="accent-primary"
                            />
                            <span>{{ permission.name }}</span>
                        </label>
                    </div>
                    <p v-if="filteredPermissions.length === 0" class="text-sm text-muted-foreground">
                        Nenhuma permissão disponível para este tipo.
                    </p>
                    <InputError :message="errors.permissions" />
                </div>
            </FormCard>
        </Form>
        </div>
    </AppLayout>
</template>
