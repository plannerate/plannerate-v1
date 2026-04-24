<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ShieldCheck } from 'lucide-vue-next';
import PermissionController from '@/actions/App/Http/Controllers/Landlord/PermissionController';
import AppLayout from '@/layouts/AppLayout.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
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

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.landlord.permissions.actions.edit') : t('app.landlord.permissions.actions.new'),
    title: isEdit.value ? t('app.landlord.permissions.actions.edit') : t('app.landlord.permissions.actions.new'),
    description: t('app.landlord.permissions.description'),
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
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="space-y-6 p-4">
        <Form
            v-bind="isEdit ? PermissionController.update.form(props.permission!.id) : PermissionController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :processing="processing"
                :disabled="isProtected"
                :cancel-href="permissionsIndexPath"
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
                        <span>{{ t('app.landlord.permissions.protected') }}</span>
                    </div>
                </template>

                <!-- Type field -->
                <div class="grid gap-2">
                    <Label for="type">{{ t('app.landlord.permissions.fields.type') }}</Label>
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

                <!-- Name field -->
                <div class="grid gap-2">
                    <Label for="name">{{ t('app.landlord.permissions.fields.name') }}</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="props.permission?.name ?? ''"
                        :disabled="isProtected"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
            </FormCard>
        </Form>
        </div>
    </AppLayout>
</template>
