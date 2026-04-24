<script setup lang="ts">
import { Form, Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { AlertCircle, Building2, ExternalLink, Loader2, RefreshCw } from 'lucide-vue-next';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import AppLayout from '@/layouts/AppLayout.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
    database: string;
    status: string;
    provisioning_error: string | null;
    plan_id: string | null;
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

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.landlord.tenants.actions.edit') : t('app.landlord.tenants.actions.new'),
    title: isEdit.value ? t('app.landlord.tenants.actions.edit') : t('app.landlord.tenants.actions.new'),
    description: t('app.landlord.tenants.description'),
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

const needsSetup = computed(
    () => isEdit.value && props.tenant!.status !== 'active',
);

const alreadyRunning = computed(
    () => props.tenant?.status === 'provisioning' && !props.tenant?.provisioning_error,
);

const provisionForm = useForm({});

function restartProvision(): void {
    provisionForm.post(TenantController.provision.url(props.tenant!.id));
}

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
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
        <Form
            v-bind="isEdit ? TenantController.update.form(props.tenant!.id) : TenantController.store.form()"
            v-slot="{ errors, processing }"
        >
            <FormCard
                :processing="processing"
                :cancel-href="tenantsIndexPath"
            >
                <template #icon>
                    <Building2 class="size-5" />
                </template>

                <!-- Setup banner (edit mode only when not active) -->
                <div
                    v-if="needsSetup"
                    class="flex items-start gap-3 rounded-lg border px-4 py-3"
                    :class="tenant!.provisioning_error
                        ? 'border-destructive/30 bg-destructive/5'
                        : 'border-yellow-400/30 bg-yellow-50 dark:bg-yellow-950/20'"
                >
                    <AlertCircle
                        class="mt-0.5 size-4 shrink-0"
                        :class="tenant!.provisioning_error ? 'text-destructive' : 'text-yellow-500'"
                    />
                    <div class="flex-1 text-sm">
                        <span v-if="tenant!.provisioning_error" class="font-medium text-destructive">
                            Erro no provisionamento:
                            <span class="font-normal">{{ tenant!.provisioning_error }}</span>
                        </span>
                        <span v-else class="text-yellow-700 dark:text-yellow-400">
                            Este tenant ainda não foi provisionado (status: <strong>{{ tenant!.status }}</strong>).
                        </span>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            v-if="!alreadyRunning"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-border bg-background px-2.5 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted disabled:opacity-60"
                            :disabled="provisionForm.processing"
                            @click="restartProvision"
                        >
                            <Loader2 v-if="provisionForm.processing" class="size-3 animate-spin" />
                            <RefreshCw v-else class="size-3" />
                            Reiniciar
                        </button>
                        <Link
                            :href="TenantController.setup.url(tenant!.id)"
                            class="inline-flex items-center gap-1 rounded-md border border-border bg-background px-2.5 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted"
                        >
                            <ExternalLink class="size-3" />
                            Ver setup
                        </Link>
                    </div>
                </div>

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

                <!-- Domain -->
                <div class="grid gap-4">
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
    </AppLayout>
</template>
