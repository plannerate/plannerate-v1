<script setup lang="ts">
import { computed, ref } from 'vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormTabsBar from '@/components/form/FormTabsBar.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import KeyValueTable from '@/components/form/KeyValueTable.vue';
import { useT } from '@/composables/useT';
import AuthSection from './AuthSection.vue';
import type { IntegrationPayload, IntegrationTypeOption, KeyValueRow } from './types';

const props = defineProps<{
    integration: IntegrationPayload | null;
    integrationTypes: IntegrationTypeOption[];
    errors: Record<string, string>;
}>();

const { t } = useT();

const activeTab = ref('authorization');

const connectionHeaders = ref<KeyValueRow[]>(
    props.integration?.connection_headers ?? [],
);
const connectionParams = ref<KeyValueRow[]>(
    props.integration?.connection_params ?? [],
);
const connectionBody = ref<KeyValueRow[]>(
    props.integration?.connection_body ?? [],
);

const tabs = computed(() => [
    { key: 'authorization', label: 'Authorization' },
    { key: 'headers', label: 'Headers' },
    { key: 'params', label: 'Params' },
    { key: 'body', label: 'Body' },
]);

const formData = computed(() => ({
    integration_type:
        props.integration?.integration_type ??
        props.integrationTypes[0]?.value ??
        '',
    api_url: props.integration?.api_url ?? '',
    auth_type: props.integration?.auth_type ?? 'none',
    auth_bearer_mode: props.integration?.auth_bearer_mode ?? 'manual',
    auth_token_header: props.integration?.auth_token_header ?? '',
    auth_username: props.integration?.auth_username ?? '',
    auth_password: props.integration?.auth_password ?? '',
    auth_token: props.integration?.auth_token ?? '',
    auth_token_username: props.integration?.auth_token_username ?? '',
    auth_token_password: props.integration?.auth_token_password ?? '',
    auth_token_method: props.integration?.auth_token_method ?? 'POST',
    auth_token_path: props.integration?.auth_token_path ?? '',
    auth_token_response_path:
        props.integration?.auth_token_response_path ?? 'token',
    auth_token_username_field:
        props.integration?.auth_token_username_field ?? 'username',
    auth_token_password_field:
        props.integration?.auth_token_password_field ?? 'password',
}));
</script>

<template>
    <div class="space-y-4">
        <!-- Tipo + URL -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <FormSelectField
                id="integration_type"
                name="integration_type"
                :label="
                    t(
                        'app.landlord.tenant_integrations.fields.integration_type',
                    )
                "
                :default-value="formData.integration_type"
                :error="errors.integration_type"
                class="md:col-span-3"
                required
            >
                <option value="" disabled>
                    {{
                        t(
                            'app.landlord.tenant_integrations.placeholders.integration_type',
                        )
                    }}
                </option>
                <option
                    v-for="type in integrationTypes"
                    :key="type.value"
                    :value="type.value"
                >
                    {{ type.label }}
                </option>
            </FormSelectField>

            <FormTextField
                id="api_url"
                name="api_url"
                :label="
                    t('app.landlord.tenant_integrations.fields.api_url')
                "
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
        <div v-show="activeTab === 'authorization'">
            <AuthSection
                :integration="integration"
                :form-data="formData"
                :errors="errors"
            />
        </div>

        <!-- Headers -->
        <div v-show="activeTab === 'headers'" class="space-y-2">
            <p class="text-sm text-muted-foreground">
                {{
                    t(
                        'app.landlord.tenant_integrations.tabs.headers_hint',
                    )
                }}
            </p>
            <KeyValueTable v-model="connectionHeaders" name="headers" />
        </div>

        <!-- Params -->
        <div v-show="activeTab === 'params'" class="space-y-2">
            <p class="text-sm text-muted-foreground">
                {{
                    t('app.landlord.tenant_integrations.tabs.params_hint')
                }}
            </p>
            <KeyValueTable v-model="connectionParams" name="params" />
        </div>

        <!-- Body -->
        <div v-show="activeTab === 'body'" class="space-y-2">
            <p class="text-sm text-muted-foreground">
                {{
                    t('app.landlord.tenant_integrations.tabs.body_hint')
                }}
            </p>
            <KeyValueTable v-model="connectionBody" name="body" />
        </div>
    </div>
</template>
