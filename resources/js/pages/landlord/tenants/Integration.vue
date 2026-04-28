<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Link2 } from 'lucide-vue-next';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantIntegrationController from '@/actions/App/Http/Controllers/Landlord/TenantIntegrationController';
import FormCard from '@/components/FormCard.vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import SysmoIntegrationFields from '@/components/form/SysmoIntegrationFields.vue';
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
    auto_processing_enabled: props.integration?.auto_processing_enabled ?? true,
    processing_time: props.integration?.processing_time ?? '02:00',
    initial_setup_date: props.integration?.initial_setup_date ?? '',
    is_active: props.integration?.is_active ?? true,
}));
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
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>
