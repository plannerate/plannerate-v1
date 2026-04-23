<script setup lang="ts">
import { ref } from 'vue';
import { useVModel } from '@vueuse/core';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type CepResolvedPayload = {
    street: string;
    district: string;
    city: string;
    state: string;
    complement: string;
};

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        error?: string;
        hint?: string;
        required?: boolean;
        modelValue?: string;
        defaultValue?: string;
        disabled?: boolean;
    }>(),
    {
        error: '',
        hint: '',
        required: false,
        modelValue: undefined,
        defaultValue: '',
        disabled: false,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string): void;
    (e: 'resolved', payload: CepResolvedPayload): void;
}>();

const { t } = useT();
const loading = ref(false);
const lookupError = ref('');

const zipCode = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

async function searchCep(): Promise<void> {
    lookupError.value = '';

    const cep = String(zipCode.value ?? '').replace(/\D/g, '');

    if (cep.length !== 8) {
        lookupError.value = t('app.addresses.messages.invalid_zip_code');

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);

        if (!response.ok) {
            throw new Error('Request failed');
        }

        const payload = await response.json() as {
            erro?: boolean;
            logradouro?: string;
            bairro?: string;
            localidade?: string;
            uf?: string;
            complemento?: string;
        };

        if (payload.erro) {
            lookupError.value = t('app.addresses.messages.zip_code_not_found');

            return;
        }

        emits('resolved', {
            street: payload.logradouro ?? '',
            district: payload.bairro ?? '',
            city: payload.localidade ?? '',
            state: payload.uf ?? '',
            complement: payload.complemento ?? '',
        });
    } catch {
        lookupError.value = t('app.addresses.messages.zip_code_lookup_failed');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-y-1">
        <Label :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <div class="flex items-center gap-2">
            <input
                :id="id"
                v-model="zipCode"
                :name="name"
                :required="required"
                :disabled="disabled"
                class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
            />
            <Button type="button" variant="outline" :disabled="loading || disabled" class="shrink-0" @click="searchCep">
                {{ loading ? t('app.loading') : t('app.addresses.actions.search_zip_code') }}
            </Button>
        </div>

        <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
        <InputError :message="lookupError" />
    </div>
</template>
