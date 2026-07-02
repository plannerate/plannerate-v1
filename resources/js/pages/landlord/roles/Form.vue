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
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

type RolePayload = {
    id: string;
    type: string;
    name: string;
    system_name: string | null;
    permissions: string[];
    is_protected: boolean;
};

type PermissionOption = {
    name: string;
    type: string;
    short_name: string | null;
    description: string | null;
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
const permissionSearch = ref('');

const filteredPermissions = computed(() =>
    props.permissions.filter((p) => {
        if (p.type !== selectedType.value) return false;
        if (!permissionSearch.value) return true;
        const term = permissionSearch.value.toLowerCase();
        return (
            p.name.toLowerCase().includes(term) ||
            (p.short_name?.toLowerCase().includes(term) ?? false) ||
            (p.description?.toLowerCase().includes(term) ?? false)
        );
    }),
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
            href: isEdit.value ? tenantWayfinderPath(RoleController.edit.url(props.role!.id)) : tenantWayfinderPath(RoleController.create.url()),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
        <Form
            v-bind="isEdit
                ? { ...RoleController.update.form(props.role!.id), action: tenantWayfinderPath(RoleController.update.form(props.role!.id).action) }
                : { ...RoleController.store.form(), action: tenantWayfinderPath(RoleController.store.form().action) }"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :processing="processing"
                :disabled="isProtected"
                :cancel-href="rolesIndexPath"
                :title="pageMeta.title"
                :description="pageMeta.description"
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

                <!-- System Name -->
                <div class="grid gap-2">
                    <Label for="system_name">{{ t('app.landlord.roles.fields.system_name') }}</Label>
                    <Input id="system_name" name="system_name" :default-value="props.role?.system_name ?? ''" :disabled="isProtected" />
                    <InputError :message="errors.system_name" />
                </div>

                <!-- Permissions -->
                <div class="space-y-3">
                    <Label>{{ t('app.landlord.roles.fields.permissions') }}</Label>
                    <Input
                        v-model="permissionSearch"
                        type="search"
                        placeholder="Filtrar permissões..."
                        class="h-9"
                    />
                    <div class="grid gap-2 md:grid-cols-2">
                        <label
                            v-for="permission in filteredPermissions"
                            :key="permission.name"
                            class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border px-3 py-2.5 text-sm transition-colors hover:bg-muted/40 has-checked:border-primary/50 has-checked:bg-primary/5"
                            :title="permission.description ?? permission.name"
                        >
                            <input
                                type="checkbox"
                                name="permissions[]"
                                :value="permission.name"
                                :checked="props.role?.permissions.includes(permission.name) ?? false"
                                :disabled="isProtected"
                                class="mt-0.5 accent-primary"
                            />
                            <span class="min-w-0 flex-1 space-y-0.5">
                                <span class="block font-medium text-foreground">
                                    {{ permission.short_name || permission.name }}
                                </span>
                                <span v-if="permission.description" class="block text-xs text-muted-foreground">
                                    {{ permission.description }}
                                </span>
                                <span class="block font-mono text-[10px] text-muted-foreground/70">
                                    {{ permission.name }}
                                </span>
                            </span>
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
