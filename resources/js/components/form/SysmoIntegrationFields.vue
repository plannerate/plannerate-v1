<script setup lang="ts">
import type { Errors } from '@inertiajs/core';
import FormTextField from '@/components/form/FormTextField.vue';
import { useT } from '@/composables/useT';

type IntegrationFormData = {
    identifier: string;
    external_name: string;
    external_name_ean: string;
    external_name_status: string;
    external_name_sale_date: string;
    empresa: string;
    partner_key: string;
    days_to_maintain: string | number;
    sales_initial_days: string | number;
    products_initial_days: string | number;
    daily_lookback_days: string | number;
    sales_page_size: string | number;
    products_page_size: string | number;
    sales_tipo_consulta: string;
    processing_time: string;
    initial_setup_date: string;
    auto_processing_enabled: boolean;
    is_active: boolean;
};

defineProps<{
    data: IntegrationFormData;
    errors: Errors;
}>();

const { t } = useT();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
        <FormTextField
            id="identifier"
            name="identifier"
            :label="t('app.landlord.tenant_integrations.fields.identifier')"
            :default-value="data.identifier"
            :error="errors.identifier"
            class="md:col-span-4"
            required
        />

        <FormTextField
            id="external_name"
            name="external_name"
            :label="t('app.landlord.tenant_integrations.fields.external_name')"
            :default-value="data.external_name"
            :error="errors.external_name"
            class="md:col-span-4"
            required
        />

        <FormTextField
            id="empresa"
            name="empresa"
            :label="t('app.landlord.tenant_integrations.fields.empresa')"
            :default-value="data.empresa"
            :error="errors.empresa"
            class="md:col-span-4"
        />

        <FormTextField
            id="partner_key"
            name="partner_key"
            :label="t('app.landlord.tenant_integrations.fields.partner_key')"
            :default-value="data.partner_key"
            :error="errors.partner_key"
            class="md:col-span-4"
            required
        />

        <FormTextField
            id="external_name_ean"
            name="external_name_ean"
            :label="t('app.landlord.tenant_integrations.fields.external_name_ean')"
            :default-value="data.external_name_ean"
            :error="errors.external_name_ean"
            class="md:col-span-4"
        />

        <FormTextField
            id="external_name_status"
            name="external_name_status"
            :label="t('app.landlord.tenant_integrations.fields.external_name_status')"
            :default-value="data.external_name_status"
            :error="errors.external_name_status"
            class="md:col-span-4"
        />

        <FormTextField
            id="external_name_sale_date"
            name="external_name_sale_date"
            :label="t('app.landlord.tenant_integrations.fields.external_name_sale_date')"
            :default-value="data.external_name_sale_date"
            :error="errors.external_name_sale_date"
            class="md:col-span-4"
        />

        <FormTextField
            id="days_to_maintain"
            name="days_to_maintain"
            type="number"
            :label="t('app.landlord.tenant_integrations.fields.days_to_maintain')"
            :default-value="data.days_to_maintain"
            :error="errors.days_to_maintain"
            class="md:col-span-4"
            min="1"
        />

        <FormTextField
            id="sales_initial_days"
            name="sales_initial_days"
            type="number"
            :label="t('app.landlord.tenant_integrations.fields.sales_initial_days')"
            :default-value="data.sales_initial_days"
            :error="errors.sales_initial_days"
            class="md:col-span-4"
            min="1"
        />

        <FormTextField
            id="products_initial_days"
            name="products_initial_days"
            type="number"
            :label="t('app.landlord.tenant_integrations.fields.products_initial_days')"
            :default-value="data.products_initial_days"
            :error="errors.products_initial_days"
            class="md:col-span-4"
            min="1"
        />

        <FormTextField
            id="daily_lookback_days"
            name="daily_lookback_days"
            type="number"
            :label="t('app.landlord.tenant_integrations.fields.daily_lookback_days')"
            :default-value="data.daily_lookback_days"
            :error="errors.daily_lookback_days"
            class="md:col-span-4"
            min="2"
        />

        <FormTextField
            id="sales_page_size"
            name="sales_page_size"
            type="number"
            :label="t('app.landlord.tenant_integrations.fields.sales_page_size')"
            :default-value="data.sales_page_size"
            :error="errors.sales_page_size"
            class="md:col-span-4"
            min="1"
        />

        <FormTextField
            id="products_page_size"
            name="products_page_size"
            type="number"
            :label="t('app.landlord.tenant_integrations.fields.products_page_size')"
            :default-value="data.products_page_size"
            :error="errors.products_page_size"
            class="md:col-span-4"
            min="1"
        />

        <FormTextField
            id="sales_tipo_consulta"
            name="sales_tipo_consulta"
            :label="t('app.landlord.tenant_integrations.fields.sales_tipo_consulta')"
            :default-value="data.sales_tipo_consulta"
            :error="errors.sales_tipo_consulta"
            class="md:col-span-4"
        />

        <FormTextField
            id="processing_time"
            name="processing_time"
            type="time"
            :label="t('app.landlord.tenant_integrations.fields.processing_time')"
            :default-value="data.processing_time"
            :error="errors.processing_time"
            class="md:col-span-4"
        />

        <FormTextField
            id="initial_setup_date"
            name="initial_setup_date"
            type="date"
            :label="t('app.landlord.tenant_integrations.fields.initial_setup_date')"
            :default-value="data.initial_setup_date"
            :error="errors.initial_setup_date"
            class="md:col-span-4"
        />
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/20 px-4 py-3">
            <input type="hidden" name="auto_processing_enabled" value="0" />
            <input
                name="auto_processing_enabled"
                type="checkbox"
                value="1"
                :checked="data.auto_processing_enabled"
                class="accent-primary"
            />
            <span class="text-sm">{{ t('app.landlord.tenant_integrations.fields.auto_processing_enabled') }}</span>
        </label>

        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/20 px-4 py-3">
            <input type="hidden" name="is_active" value="0" />
            <input
                name="is_active"
                type="checkbox"
                value="1"
                :checked="data.is_active"
                class="accent-primary"
            />
            <span class="text-sm">{{ t('app.landlord.tenant_integrations.fields.is_active') }}</span>
        </label>
    </div>
</template>
