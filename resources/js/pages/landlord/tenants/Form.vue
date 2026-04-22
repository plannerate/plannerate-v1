<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Building2 } from 'lucide-vue-next';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
    database: string;
    status: string;
    plan_id: string | null;
    user_limit: number | null;
    host: string | null;
    domain_is_active: boolean;
};

type PlanOption = {
    id: string;
    name: string;
};

type StatusOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    tenant: TenantPayload | null;
    plans: PlanOption[];
    statuses: StatusOption[];
}>();

const { t } = useT();
const isEdit = computed(() => props.tenant !== null);

const name = ref(props.tenant?.name ?? '');
const slug = ref(props.tenant?.slug ?? '');
const database = ref(props.tenant?.database ?? '');
const slugTouched = ref(props.tenant !== null);
const databaseTouched = ref(props.tenant !== null);

const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantsIndexPath,
        },
        {
            title: isEdit.value ? t('app.landlord.common.edit') : t('app.landlord.common.create'),
            href: isEdit.value ? TenantController.edit.url(props.tenant!.id) : TenantController.create.url(),
        },
    ],
});

function toSlug(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

function suggestDatabase(slugValue: string): string {
    return `tenant_${slugValue.replace(/-/g, '_')}`;
}

function onNameInput(): void {
    const nameSlug = toSlug(name.value);
    if (!slugTouched.value) slug.value = nameSlug;
    if (!databaseTouched.value) database.value = suggestDatabase(nameSlug);
}

function onSlugInput(): void {
    slugTouched.value = true;
    if (!databaseTouched.value) database.value = suggestDatabase(toSlug(slug.value));
}

function onDatabaseInput(): void {
    databaseTouched.value = true;
}
</script>

<template>
    <Head :title="isEdit ? t('app.landlord.tenants.actions.edit') : t('app.landlord.tenants.actions.new')" />

    <div class="p-4">
        <Form
            v-bind="isEdit ? TenantController.update.form(props.tenant!.id) : TenantController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :title="isEdit ? t('app.landlord.tenants.actions.edit') : t('app.landlord.tenants.actions.new')"
                :description="t('app.landlord.tenants.description')"
                :processing="processing"
                :cancel-href="tenantsIndexPath"
            >
                <template #icon>
                    <Building2 class="size-5" />
                </template>

                <!-- Identity -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="name">{{ t('app.landlord.tenants.fields.name') }}</Label>
                        <Input id="name" v-model="name" name="name" required @input="onNameInput" />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="slug" name="slug" required @input="onSlugInput" />
                        <InputError :message="errors.slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="database">{{ t('app.landlord.tenants.fields.database') }}</Label>
                        <Input id="database" v-model="database" name="database" required @input="onDatabaseInput" />
                        <InputError :message="errors.database" />
                    </div>
                </div>

                <!-- Plan & Status -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="status">{{ t('app.landlord.tenants.fields.status') }}</Label>
                        <select
                            id="status"
                            name="status"
                            :value="props.tenant?.status ?? 'provisioning'"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                        >
                            <option v-for="status in statuses" :key="status.value" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="plan_id">{{ t('app.landlord.tenants.fields.plan') }}</Label>
                        <select
                            id="plan_id"
                            name="plan_id"
                            :value="props.tenant?.plan_id ?? ''"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="">— Sem plano</option>
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }}
                            </option>
                        </select>
                        <InputError :message="errors.plan_id" />
                    </div>
                </div>

                <!-- Limits & Domain -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="user_limit">{{ t('app.landlord.tenants.fields.user_limit') }}</Label>
                        <Input id="user_limit" name="user_limit" type="number" min="1" :default-value="props.tenant?.user_limit ?? ''" />
                        <p class="text-xs text-muted-foreground">Deixe em branco para usar o limite do plano.</p>
                        <InputError :message="errors.user_limit" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="host">{{ t('app.landlord.tenants.fields.host') }}</Label>
                        <Input id="host" name="host" :default-value="props.tenant?.host ?? ''" required />
                        <InputError :message="errors.host" />
                    </div>
                </div>

                <!-- Domain active -->
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                    <input type="hidden" name="domain_is_active" value="0" />
                    <input
                        id="domain_is_active"
                        name="domain_is_active"
                        type="checkbox"
                        value="1"
                        :checked="props.tenant?.domain_is_active ?? true"
                        class="accent-primary"
                    />
                    <div>
                        <span class="text-sm font-medium">{{ t('app.landlord.tenants.fields.domain_is_active') }}</span>
                        <p class="text-xs text-muted-foreground">Ativa o domínio primário do tenant imediatamente.</p>
                    </div>
                    <InputError :message="errors.domain_is_active" />
                </label>
            </FormCard>
        </Form>
    </div>
</template>
