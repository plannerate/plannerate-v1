<script setup lang="ts">
import type { Errors } from '@inertiajs/core';
import FormTextField from '@/components/form/FormTextField.vue';
import { useT } from '@/composables/useT';

type GesCooperFormData = {
    products_page_size: string | number;
    processing_time: string;
    initial_setup_date: string;
    auto_processing_enabled: boolean;
    is_active: boolean;
};

defineProps<{
    data: GesCooperFormData;
    errors: Errors;
}>();

const { t } = useT();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
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
