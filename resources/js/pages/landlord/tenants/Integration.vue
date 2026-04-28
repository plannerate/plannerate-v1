<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Link2 } from 'lucide-vue-next';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantIntegrationController from '@/actions/App/Http/Controllers/Landlord/TenantIntegrationController';
import FormCard from '@/components/FormCard.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import SysmoIntegrationFields from '@/components/form/SysmoIntegrationFields.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';

type TenantPayload = {
    id: string;
    name: string;
};

type IntegrationPayload = {
    id: string;
    integration_type: string;
    identifier: string;
    external_name: string;
    external_name_ean: string;
    external_name_status: string;
    external_name_sale_date: string;
    http_method: string;
    api_url: string;
    auth_username: string;
    auth_password: string;
    partner_key: string;
    empresa: string;
    days_to_maintain: number;
    sales_initial_days: number;
    products_initial_days: number;
    daily_lookback_days: number;
    sales_page_size: number;
    products_page_size: number;
    sales_tipo_consulta: string;
    auto_processing_enabled: boolean;
    processing_time: string;
    initial_setup_date: string | null;
    is_active: boolean;
    last_sync: string | null;
};

type IntegrationTypeOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    tenant: TenantPayload;
    integration: IntegrationPayload | null;
    integration_types: IntegrationTypeOption[];
    http_methods: string[];
}>();

const { t } = useT();
const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');
const testPath = ref('/');
const testMethod = ref('GET');
const testBody = ref('');
const testLoading = ref(false);
const testError = ref<string | null>(null);
const testResult = ref<unknown>(null);

const pageMeta = useCrudPageMeta({
    headTitle: `${t('app.landlord.tenant_integrations.title')} - ${props.tenant.name}`,
    title: `${t('app.landlord.tenant_integrations.title')} - ${props.tenant.name}`,
    description: t('app.landlord.tenant_integrations.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantsIndexPath,
        },
        {
            title: t('app.landlord.tenant_integrations.navigation'),
            href: TenantIntegrationController.edit.url(props.tenant.id),
        },
    ],
});

const formData = computed(() => ({
    integration_type: props.integration?.integration_type ?? 'sysmo',
    identifier: props.integration?.identifier ?? '',
    external_name: props.integration?.external_name ?? 'produto',
    external_name_ean: props.integration?.external_name_ean ?? '',
    external_name_status: props.integration?.external_name_status ?? '',
    external_name_sale_date: props.integration?.external_name_sale_date ?? '',
    http_method: props.integration?.http_method ?? 'POST',
    api_url: props.integration?.api_url ?? '',
    auth_username: props.integration?.auth_username ?? '',
    auth_password: props.integration?.auth_password ?? '',
    partner_key: props.integration?.partner_key ?? '',
    empresa: props.integration?.empresa ?? '',
    days_to_maintain: props.integration?.days_to_maintain ?? 120,
    sales_initial_days: props.integration?.sales_initial_days ?? 120,
    products_initial_days: props.integration?.products_initial_days ?? 120,
    daily_lookback_days: props.integration?.daily_lookback_days ?? 7,
    sales_page_size: props.integration?.sales_page_size ?? 20000,
    products_page_size: props.integration?.products_page_size ?? 1000,
    sales_tipo_consulta: props.integration?.sales_tipo_consulta ?? 'produto',
    auto_processing_enabled: props.integration?.auto_processing_enabled ?? true,
    processing_time: props.integration?.processing_time ?? '02:00',
    initial_setup_date: props.integration?.initial_setup_date ?? '',
    is_active: props.integration?.is_active ?? true,
}));

type IntegrationTestResult = {
    ok: boolean;
    message?: string;
    meta?: Record<string, unknown>;
    data?: unknown;
};

const removeFlashListener = router.on('flash', (event) => {
    const flash = (event as CustomEvent).detail?.flash as
        | { tenant_integration_test?: IntegrationTestResult }
        | undefined;

    const payload = flash?.tenant_integration_test;
    if (!payload) {
        return;
    }

    testResult.value = payload;
    testError.value = payload.ok ? null : (payload.message ?? t('app.messages.generic_error'));
});

onBeforeUnmount(() => {
    removeFlashListener();
});

function testConnection(): void {
    if (!props.integration || testLoading.value) {
        return;
    }

    testLoading.value = true;
    testError.value = null;
    testResult.value = null;

    router.post(
        TenantIntegrationController.testConnection.url(props.tenant.id),
        {
            test_path: testPath.value,
            test_method: testMethod.value,
            test_body: testBody.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                testError.value = t('app.messages.generic_error');
            },
            onFinish: () => {
                testLoading.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="TenantIntegrationController.update.form(props.tenant.id)"
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="tenantsIndexPath"
                >
                    <template #icon>
                        <Link2 class="size-5" />
                    </template>
                    <template #header-extra>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="!props.integration || testLoading"
                            @click="testConnection"
                        >
                            {{ t('app.landlord.tenant_integrations.actions.test_connection') }}
                        </Button>
                    </template>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <FormSelectField
                            id="integration_type"
                            name="integration_type"
                            :label="t('app.landlord.tenant_integrations.fields.integration_type')"
                            :default-value="formData.integration_type"
                            :error="errors.integration_type"
                            class="md:col-span-4"
                            required
                        >
                            <option
                                v-for="type in props.integration_types"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </FormSelectField>
                    </div>

                    <SysmoIntegrationFields
                        :data="formData"
                        :errors="errors"
                        :http-methods="props.http_methods"
                        :password-required="!props.integration"
                    />

                    <div class="space-y-3 rounded-lg border border-border/60 bg-muted/20 p-4">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ t('app.landlord.tenant_integrations.actions.test_connection') }}
                        </h3>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                            <div class="md:col-span-3">
                                <FormSelectField
                                    id="test_method"
                                    name="test_method"
                                    :label="t('app.landlord.tenant_integrations.fields.test_method')"
                                    :model-value="testMethod"
                                    @update:model-value="(value) => testMethod = String(value)"
                                >
                                    <option value="GET">GET</option>
                                    <option value="POST">POST</option>
                                    <option value="PUT">PUT</option>
                                    <option value="PATCH">PATCH</option>
                                    <option value="DELETE">DELETE</option>
                                </FormSelectField>
                            </div>

                            <div class="md:col-span-9 space-y-1">
                                <label for="test_path" class="text-sm font-medium text-foreground">
                                    {{ t('app.landlord.tenant_integrations.fields.test_path') }}
                                </label>
                                <input
                                    id="test_path"
                                    v-model="testPath"
                                    type="text"
                                    class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                    placeholder="/"
                                />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="test_body" class="text-sm font-medium text-foreground">
                                {{ t('app.landlord.tenant_integrations.fields.test_body') }}
                            </label>
                            <textarea
                                id="test_body"
                                v-model="testBody"
                                rows="6"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-xs font-mono text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                :placeholder="t('app.landlord.tenant_integrations.placeholders.test_body')"
                            />
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                :disabled="!props.integration || testLoading"
                                @click="testConnection"
                            >
                                {{ t('app.landlord.tenant_integrations.actions.run_test') }}
                            </Button>
                            <span v-if="testLoading" class="text-xs text-muted-foreground">
                                {{ t('app.loading') }}
                            </span>
                        </div>

                        <div v-if="testError" class="rounded border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {{ testError }}
                        </div>

                        <div v-if="testResult !== null" class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">
                                {{ t('app.landlord.tenant_integrations.fields.test_response') }}
                            </p>
                            <pre class="max-h-96 overflow-auto rounded-md border border-border bg-background p-3 text-xs text-foreground">{{ JSON.stringify(testResult, null, 2) }}</pre>
                        </div>
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>
