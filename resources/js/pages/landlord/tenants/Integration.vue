<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Link2, PowerOff, Power } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantIntegrationController from '@/actions/App/Http/Controllers/Landlord/TenantIntegrationController';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import DeleteButton from '@/components/DeleteButton.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import FormTabsBar from '@/components/form/FormTabsBar.vue';
import KeyValueTable from '@/components/form/KeyValueTable.vue';
import FormCard from '@/components/FormCard.vue';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';

type TenantPayload = {
    id: string;
    name: string;
};

type KeyValueRow = {
    key: string;
    value: string;
    enabled: boolean;
};

type IntegrationPayload = {
    id: string;
    integration_type: string;
    api_url: string;
    auth_type: string;
    auth_token: string;
    auth_username: string;
    auth_password: string;
    sales_initial_days: number;
    products_initial_days: number;
    processing_time: string;
    is_active: boolean;
    last_sync: string | null;
    connection_headers: KeyValueRow[];
    connection_params: KeyValueRow[];
    connection_body: KeyValueRow[];
};

type IntegrationTypeOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    tenant: TenantPayload;
    integration: IntegrationPayload | null;
    integration_types: IntegrationTypeOption[];
}>();

const { t } = useT();
const activeTab = ref('authorization');
const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');

const localAuthType = ref(props.integration?.auth_type ?? 'none');
const connectionHeaders = ref<KeyValueRow[]>(props.integration?.connection_headers ?? []);
const connectionParams = ref<KeyValueRow[]>(props.integration?.connection_params ?? []);
const connectionBody = ref<KeyValueRow[]>(props.integration?.connection_body ?? []);

const testPath = ref('/');
const testMethod = ref('GET');
const testBody = ref('');
const testLoading = ref(false);
const testError = ref<string | null>(null);
const testResult = ref<unknown>(null);

const tabs = computed(() => [
    { key: 'authorization', label: 'Authorization' },
    { key: 'headers',       label: 'Headers' },
    { key: 'params',        label: 'Params' },
    { key: 'body',          label: 'Body' },
]);

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
            href: tenantWayfinderPath(TenantIntegrationController.edit.url(props.tenant.id)),
        },
    ],
});

const formData = computed(() => ({
    integration_type:      props.integration?.integration_type ?? 'sysmo',
    api_url:               props.integration?.api_url ?? '',
    auth_type:             props.integration?.auth_type ?? 'none',
    auth_username:         props.integration?.auth_username ?? '',
    auth_password:         props.integration?.auth_password ?? '',
    auth_token:            props.integration?.auth_token ?? '',
    sales_initial_days:    props.integration?.sales_initial_days ?? 120,
    products_initial_days: props.integration?.products_initial_days ?? 120,
    processing_time:       props.integration?.processing_time ?? '02:00',
    is_active:             props.integration?.is_active ?? true,
}));

const updateForm = computed(() => {
    const definition = TenantIntegrationController.update.form(props.tenant.id);

    return {
        ...definition,
        action: tenantWayfinderPath(definition.action),
    };
});

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

const statusLoading = ref(false);

function toggleStatus(): void {
    if (!props.integration || statusLoading.value) {
        return;
    }

    statusLoading.value = true;

    router.patch(
        tenantWayfinderPath(TenantIntegrationController.toggleStatus.url(props.tenant.id)),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                statusLoading.value = false;
            },
        },
    );
}

function testConnection(): void {
    if (!props.integration || testLoading.value) {
        return;
    }

    testLoading.value = true;
    testError.value = null;
    testResult.value = null;

    router.post(
        tenantWayfinderPath(TenantIntegrationController.testConnection.url(props.tenant.id)),
        {
            test_path:   testPath.value,
            test_method: testMethod.value,
            test_body:   testBody.value,
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
                v-bind="updateForm"
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
                        <Button
                            type="button"
                            :variant="props.integration?.is_active ? 'outline' : 'secondary'"
                            size="sm"
                            :disabled="!props.integration || statusLoading"
                            @click="toggleStatus"
                        >
                            <PowerOff v-if="props.integration?.is_active" class="size-4" />
                            <Power v-else class="size-4" />
                            {{ props.integration?.is_active
                                ? t('app.landlord.tenant_integrations.actions.deactivate')
                                : t('app.landlord.tenant_integrations.actions.activate') }}
                        </Button>
                        <DeleteButton
                            v-if="props.integration"
                            :href="tenantWayfinderPath(TenantIntegrationController.destroy.url(props.tenant.id))"
                            :label="t('app.landlord.tenant_integrations.title')"
                            require-confirm-word
                        >
                            {{ t('app.landlord.tenant_integrations.actions.delete') }}
                        </DeleteButton>
                    </template>

                    <!-- Tipo + URL — sempre visíveis -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <FormSelectField
                            id="integration_type"
                            name="integration_type"
                            :label="t('app.landlord.tenant_integrations.fields.integration_type')"
                            :default-value="formData.integration_type"
                            :error="errors.integration_type" 
                            class="md:col-span-3"
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

                        <FormTextField
                            id="api_url"
                            name="api_url"
                            :label="t('app.landlord.tenant_integrations.fields.api_url')"
                            :default-value="formData.api_url"
                            :error="errors.api_url"
                            placeholder="https://api.exemplo.com"
                            class="md:col-span-9"
                            required
                        />
                    </div>

                    <!-- Tabs Postman -->
                    <FormTabsBar v-model="activeTab" :tabs="tabs" />

                    <!-- Authorization -->
                    <!-- v-show mantém inputs no DOM para que sejam submetidos independente da tab ativa -->
                    <div v-show="activeTab === 'authorization'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <div class="space-y-1 md:col-span-4">
                                <label for="auth_type" class="text-sm font-medium text-foreground">
                                    {{ t('app.landlord.tenant_integrations.fields.auth_type') }}
                                </label>
                                <select
                                    id="auth_type"
                                    v-model="localAuthType"
                                    name="auth_type"
                                    class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="none">{{ t('app.landlord.tenant_integrations.auth_types.none') }}</option>
                                    <option value="bearer">{{ t('app.landlord.tenant_integrations.auth_types.bearer') }}</option>
                                    <option value="basic">{{ t('app.landlord.tenant_integrations.auth_types.basic') }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Bearer Token -->
                        <div v-show="localAuthType === 'bearer'" class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <FormTextField
                                id="auth_token"
                                name="auth_token"
                                type="password"
                                :label="t('app.landlord.tenant_integrations.fields.auth_token')"
                                :default-value="formData.auth_token"
                                :error="errors.auth_token"
                                class="md:col-span-8"
                            />
                        </div>

                        <!-- Basic Auth -->
                        <div v-show="localAuthType === 'basic'" class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <FormTextField
                                id="auth_username"
                                name="auth_username"
                                :label="t('app.landlord.tenant_integrations.fields.auth_username')"
                                :default-value="formData.auth_username"
                                :error="errors.auth_username"
                                class="md:col-span-4"
                            />
                            <FormTextField
                                id="auth_password"
                                name="auth_password"
                                type="password"
                                :label="t('app.landlord.tenant_integrations.fields.auth_password')"
                                :default-value="formData.auth_password"
                                :error="errors.auth_password"
                                :hint="t('app.landlord.tenant_integrations.hints.auth_password')"
                                :placeholder="!props.integration ? '' : t('app.landlord.tenant_integrations.placeholders.keep_password')"
                                class="md:col-span-4"
                            />
                        </div>

                        <!-- None -->
                        <div v-show="localAuthType === 'none'" class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                            {{ t('app.landlord.tenant_integrations.auth_types.none_description') }}
                        </div>
                    </div>

                    <!-- Headers -->
                    <div v-show="activeTab === 'headers'" class="space-y-2">
                        <p class="text-sm text-muted-foreground">
                            {{ t('app.landlord.tenant_integrations.tabs.headers_hint') }}
                        </p>
                        <KeyValueTable
                            v-model="connectionHeaders"
                            name="headers"
                        />
                    </div>

                    <!-- Params -->
                    <div v-show="activeTab === 'params'" class="space-y-2">
                        <p class="text-sm text-muted-foreground">
                            {{ t('app.landlord.tenant_integrations.tabs.params_hint') }}
                        </p>
                        <KeyValueTable
                            v-model="connectionParams"
                            name="params"
                        />
                    </div>

                    <!-- Body -->
                    <div v-show="activeTab === 'body'" class="space-y-2">
                        <p class="text-sm text-muted-foreground">
                            {{ t('app.landlord.tenant_integrations.tabs.body_hint') }}
                        </p>
                        <KeyValueTable
                            v-model="connectionBody"
                            name="body"
                        />
                    </div>

                    <!-- Processamento — fora das tabs -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <FormTextField
                            id="sales_initial_days"
                            name="sales_initial_days"
                            type="number"
                            :label="t('app.landlord.tenant_integrations.fields.sales_initial_days')"
                            :default-value="String(formData.sales_initial_days)"
                            :error="errors.sales_initial_days"
                            class="md:col-span-3"
                        />
                        <FormTextField
                            id="products_initial_days"
                            name="products_initial_days"
                            type="number"
                            :label="t('app.landlord.tenant_integrations.fields.products_initial_days')"
                            :default-value="String(formData.products_initial_days)"
                            :error="errors.products_initial_days"
                            class="md:col-span-3"
                        />
                        <FormTextField
                            id="processing_time"
                            name="processing_time"
                            :label="t('app.landlord.tenant_integrations.fields.processing_time')"
                            :default-value="formData.processing_time"
                            :error="errors.processing_time"
                            placeholder="02:00"
                            class="md:col-span-3"
                        />
                    </div>

                    <!-- Seção de teste de conexão -->
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

                            <div class="space-y-1 md:col-span-9">
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
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 font-mono text-xs text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
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
