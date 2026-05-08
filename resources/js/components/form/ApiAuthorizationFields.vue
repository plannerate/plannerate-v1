<script setup lang="ts">
import type { Errors } from '@inertiajs/core';
import { ref } from 'vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { useT } from '@/composables/useT';

type AuthData = {
    auth_type: string;
    auth_username: string;
    auth_password: string;
    auth_token: string;
    auth_api_key: string;
    auth_api_key_name: string;
    usuario: string;
    senha: string;
    dispositivo_uid: string;
};

const props = defineProps<{
    data: AuthData;
    integrationType: string;
    passwordRequired: boolean;
    errors: Errors;
}>();

const localAuthType = ref(props.data.auth_type || 'none');

const { t } = useT();
</script>

<template>
    <div class="space-y-4">
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
                    <option value="api_key_header">{{ t('app.landlord.tenant_integrations.auth_types.api_key_header') }}</option>
                    <option value="api_key_query">{{ t('app.landlord.tenant_integrations.auth_types.api_key_query') }}</option>
                    <option v-if="integrationType === 'gescooper'" value="gescooper">
                        GesCooper
                    </option>
                </select>
            </div>
        </div>

        <!-- Bearer Token -->
        <div v-if="localAuthType === 'bearer'" class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <FormTextField
                id="auth_token"
                name="auth_token"
                type="password"
                :label="t('app.landlord.tenant_integrations.fields.auth_token')"
                :default-value="data.auth_token"
                :error="errors.auth_token"
                class="md:col-span-8"
            />
        </div>

        <!-- Basic Auth -->
        <div v-else-if="localAuthType === 'basic'" class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <FormTextField
                id="auth_username"
                name="auth_username"
                :label="t('app.landlord.tenant_integrations.fields.auth_username')"
                :default-value="data.auth_username"
                :error="errors.auth_username"
                class="md:col-span-4"
                required
            />
            <FormTextField
                id="auth_password"
                name="auth_password"
                type="password"
                :label="t('app.landlord.tenant_integrations.fields.auth_password')"
                :default-value="data.auth_password"
                :error="errors.auth_password"
                :hint="t('app.landlord.tenant_integrations.hints.auth_password')"
                :placeholder="passwordRequired ? '' : t('app.landlord.tenant_integrations.placeholders.keep_password')"
                class="md:col-span-4"
                :required="passwordRequired"
            />
        </div>

        <!-- API Key (Header ou Query) -->
        <div
            v-else-if="localAuthType === 'api_key_header' || localAuthType === 'api_key_query'"
            class="grid grid-cols-1 gap-4 md:grid-cols-12"
        >
            <FormTextField
                id="auth_api_key_name"
                name="auth_api_key_name"
                :label="t('app.landlord.tenant_integrations.fields.auth_api_key_name')"
                :default-value="data.auth_api_key_name"
                :error="errors.auth_api_key_name"
                :placeholder="localAuthType === 'api_key_header' ? 'X-API-KEY' : 'api_key'"
                class="md:col-span-4"
            />
            <FormTextField
                id="auth_api_key"
                name="auth_api_key"
                type="password"
                :label="t('app.landlord.tenant_integrations.fields.auth_api_key')"
                :default-value="data.auth_api_key"
                :error="errors.auth_api_key"
                class="md:col-span-4"
            />
        </div>

        <!-- GesCooper Auth -->
        <div v-else-if="localAuthType === 'gescooper'" class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <FormTextField
                id="usuario"
                name="usuario"
                :label="t('app.landlord.tenant_integrations.fields.usuario')"
                :default-value="data.usuario"
                :error="errors.usuario"
                class="md:col-span-4"
                required
            />
            <FormTextField
                id="senha"
                name="senha"
                type="password"
                :label="t('app.landlord.tenant_integrations.fields.senha')"
                :default-value="data.senha"
                :error="errors.senha"
                :hint="t('app.landlord.tenant_integrations.hints.auth_password')"
                :placeholder="passwordRequired ? '' : t('app.landlord.tenant_integrations.placeholders.keep_password')"
                class="md:col-span-4"
                :required="passwordRequired"
            />
            <FormTextField
                id="dispositivo_uid"
                name="dispositivo_uid"
                :label="t('app.landlord.tenant_integrations.fields.dispositivo_uid')"
                :default-value="data.dispositivo_uid"
                :error="errors.dispositivo_uid"
                class="md:col-span-4"
            />
        </div>

        <!-- None -->
        <div v-else class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
            {{ t('app.landlord.tenant_integrations.auth_types.none_description') }}
        </div>
    </div>
</template>
