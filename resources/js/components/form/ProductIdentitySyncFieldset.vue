<script setup lang="ts">
import { computed } from 'vue';
import FormTextField from '@/components/form/FormTextField.vue';
import ProductEanSyncButton from '@/components/form/ProductEanSyncButton.vue';

const props = withDefaults(
    defineProps<{
        subdomain: string;
        ean: string;
        codigoErp: string;
        storeIds?: string[];
        eanError?: string;
        codigoErpError?: string;
        eanLabel?: string;
        codigoErpLabel?: string;
    }>(),
    {
        storeIds: () => [],
        eanError: '',
        codigoErpError: '',
        eanLabel: 'EAN',
        codigoErpLabel: 'Código ERP',
    },
);

const emit = defineEmits<{
    (event: 'update:ean', value: string): void;
    (event: 'update:codigoErp', value: string): void;
    (event: 'sync-error', payload: { message: string }): void;
    (event: 'sync-finish'): void;
}>();

const eanModel = computed({
    get: () => props.ean,
    set: (value: string) => emit('update:ean', value),
});

const codigoErpModel = computed({
    get: () => props.codigoErp,
    set: (value: string) => emit('update:codigoErp', value),
});
</script>

<template>
    <fieldset class="rounded-lg border border-border/80 p-3">
        <legend class="px-1 text-sm font-semibold">Identificação</legend>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <FormTextField
                id="ean"
                v-model="eanModel"
                name="ean"
                :label="eanLabel"
                :placeholder="eanLabel"
                :error="eanError"
                class="md:col-span-5"
            />
            <FormTextField
                id="codigo_erp"
                v-model="codigoErpModel"
                name="codigo_erp"
                :label="codigoErpLabel"
                :placeholder="codigoErpLabel"
                :error="codigoErpError"
                class="md:col-span-4"
            />
            <div class="md:col-span-3 md:pt-4.5">
                <ProductEanSyncButton
                    :subdomain="subdomain"
                    :ean="eanModel"
                    :store-ids="storeIds"
                    @sync-error="(payload) => emit('sync-error', payload)"
                    @sync-finish="() => emit('sync-finish')"
                />
            </div>
        </div>
    </fieldset>
</template>
