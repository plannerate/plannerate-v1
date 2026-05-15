<script setup lang="ts">
import { ref } from 'vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import FormTextField from '@/components/form/FormTextField.vue';
import KeyValueTable from '@/components/form/KeyValueTable.vue';
import { useT } from '@/composables/useT';
import type { IntegrationPayload, KeyValueRow } from './types';

const props = defineProps<{
    integration: IntegrationPayload | null;
    formData: Pick<
        IntegrationPayload,
        | 'auth_type'
        | 'auth_bearer_mode'
        | 'auth_token'
        | 'auth_username'
        | 'auth_password'
        | 'auth_token_username'
        | 'auth_token_password'
        | 'auth_token_method'
        | 'auth_token_path'
        | 'auth_token_response_path'
        | 'auth_token_username_field'
        | 'auth_token_password_field'
    >;
    errors: Record<string, string>;
}>();

const { t } = useT();

const localAuthType = ref(props.formData.auth_type);
const localBearerMode = ref(props.formData.auth_bearer_mode);
const authTokenHeaders = ref<KeyValueRow[]>(
    props.integration?.auth_token_headers ?? [],
);
const authTokenParams = ref<KeyValueRow[]>(
    props.integration?.auth_token_params ?? [],
);
const authTokenBody = ref<KeyValueRow[]>(
    props.integration?.auth_token_body ?? [],
);
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <div class="space-y-1 md:col-span-4">
                <label
                    for="auth_type"
                    class="text-sm font-medium text-foreground"
                >
                    {{ t('app.landlord.tenant_integrations.fields.auth_type') }}
                </label>
                <select
                    id="auth_type"
                    v-model="localAuthType"
                    name="auth_type"
                    class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="none">
                        {{
                            t(
                                'app.landlord.tenant_integrations.auth_types.none',
                            )
                        }}
                    </option>
                    <option value="bearer">
                        {{
                            t(
                                'app.landlord.tenant_integrations.auth_types.bearer',
                            )
                        }}
                    </option>
                    <option value="basic">
                        {{
                            t(
                                'app.landlord.tenant_integrations.auth_types.basic',
                            )
                        }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Bearer Token -->
        <!-- v-show mantém inputs no DOM para que sejam submetidos independente do tipo ativo -->
        <div v-show="localAuthType === 'bearer'" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="space-y-1 md:col-span-4">
                    <label
                        for="auth_bearer_mode"
                        class="text-sm font-medium text-foreground"
                    >
                        {{
                            t(
                                'app.landlord.tenant_integrations.fields.auth_bearer_mode',
                            )
                        }}
                    </label>
                    <select
                        id="auth_bearer_mode"
                        v-model="localBearerMode"
                        name="auth_bearer_mode"
                        class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="manual">
                            {{
                                t(
                                    'app.landlord.tenant_integrations.auth_types.bearer_manual',
                                )
                            }}
                        </option>
                        <option value="fetch">
                            {{
                                t(
                                    'app.landlord.tenant_integrations.auth_types.bearer_fetch',
                                )
                            }}
                        </option>
                    </select>
                </div>
            </div>

            <div
                v-show="localBearerMode === 'manual'"
                class="grid grid-cols-1 gap-4 md:grid-cols-12"
            >
                <FormTextField
                    id="auth_token"
                    name="auth_token"
                    type="password"
                    :label="
                        t('app.landlord.tenant_integrations.fields.auth_token')
                    "
                    :default-value="formData.auth_token"
                    :error="errors.auth_token"
                    :placeholder="
                        !integration
                            ? ''
                            : t(
                                  'app.landlord.tenant_integrations.placeholders.keep_token',
                              )
                    "
                    class="md:col-span-8"
                />
            </div>

            <div v-show="localBearerMode === 'fetch'" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <FormSelectField
                        id="auth_token_method"
                        name="auth_token_method"
                        :label="
                            t(
                                'app.landlord.tenant_integrations.fields.auth_token_method',
                            )
                        "
                        :default-value="formData.auth_token_method"
                        :error="errors.auth_token_method"
                        class="md:col-span-3"
                    >
                        <option value="POST">POST</option>
                        <option value="GET">GET</option>
                        <option value="PUT">PUT</option>
                        <option value="PATCH">PATCH</option>
                    </FormSelectField>
                    <FormTextField
                        id="auth_token_path"
                        name="auth_token_path"
                        :label="
                            t(
                                'app.landlord.tenant_integrations.fields.auth_token_path',
                            )
                        "
                        :default-value="formData.auth_token_path"
                        :error="errors.auth_token_path"
                        placeholder="/token"
                        class="md:col-span-5"
                    />
                    <FormTextField
                        id="auth_token_response_path"
                        name="auth_token_response_path"
                        :label="
                            t(
                                'app.landlord.tenant_integrations.fields.auth_token_response_path',
                            )
                        "
                        :default-value="formData.auth_token_response_path"
                        :error="errors.auth_token_response_path"
                        placeholder="token"
                        class="md:col-span-4"
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <FormTextField
                        id="auth_token_username"
                        name="auth_token_username"
                        :label="
                            t(
                                'app.landlord.tenant_integrations.fields.auth_username',
                            )
                        "
                        :default-value="formData.auth_token_username"
                        :error="errors.auth_token_username"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="auth_token_password"
                        name="auth_token_password"
                        type="password"
                        :label="
                            t(
                                'app.landlord.tenant_integrations.fields.auth_password',
                            )
                        "
                        :default-value="formData.auth_token_password"
                        :error="errors.auth_token_password"
                        :placeholder="
                            !integration
                                ? ''
                                : t(
                                      'app.landlord.tenant_integrations.placeholders.keep_password',
                                  )
                        "
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="auth_token_username_field"
                        name="auth_token_username_field"
                        :label="
                            t(
                                'app.landlord.tenant_integrations.fields.auth_token_username_field',
                            )
                        "
                        :default-value="formData.auth_token_username_field"
                        :error="errors.auth_token_username_field"
                        placeholder="username"
                        class="md:col-span-3"
                    />
                    <FormTextField
                        id="auth_token_password_field"
                        name="auth_token_password_field"
                        :label="
                            t(
                                'app.landlord.tenant_integrations.fields.auth_token_password_field',
                            )
                        "
                        :default-value="formData.auth_token_password_field"
                        :error="errors.auth_token_password_field"
                        placeholder="password"
                        class="md:col-span-3"
                    />
                </div>

                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                'app.landlord.tenant_integrations.tabs.token_headers_hint',
                            )
                        }}
                    </p>
                    <KeyValueTable
                        v-model="authTokenHeaders"
                        name="auth_token_headers"
                    />
                </div>

                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                'app.landlord.tenant_integrations.tabs.token_params_hint',
                            )
                        }}
                    </p>
                    <KeyValueTable
                        v-model="authTokenParams"
                        name="auth_token_params"
                    />
                </div>

                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                'app.landlord.tenant_integrations.tabs.token_body_hint',
                            )
                        }}
                    </p>
                    <KeyValueTable
                        v-model="authTokenBody"
                        name="auth_token_body"
                    />
                </div>
            </div>
        </div>

        <!-- Basic Auth -->
        <div
            v-show="localAuthType === 'basic'"
            class="grid grid-cols-1 gap-4 md:grid-cols-12"
        >
            <FormTextField
                id="auth_username"
                name="auth_username"
                :label="
                    t(
                        'app.landlord.tenant_integrations.fields.auth_username',
                    )
                "
                :default-value="formData.auth_username"
                :error="errors.auth_username"
                class="md:col-span-4"
            />
            <FormTextField
                id="auth_password"
                name="auth_password"
                type="password"
                :label="
                    t(
                        'app.landlord.tenant_integrations.fields.auth_password',
                    )
                "
                :default-value="formData.auth_password"
                :error="errors.auth_password"
                :hint="
                    t(
                        'app.landlord.tenant_integrations.hints.auth_password',
                    )
                "
                :placeholder="
                    !integration
                        ? ''
                        : t(
                              'app.landlord.tenant_integrations.placeholders.keep_password',
                          )
                "
                class="md:col-span-4"
            />
        </div>

        <!-- None -->
        <div
            v-show="localAuthType === 'none'"
            class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3 text-sm text-muted-foreground"
        >
            {{
                t(
                    'app.landlord.tenant_integrations.auth_types.none_description',
                )
            }}
        </div>
    </div>
</template>
