<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        required?: boolean;
        error?: string;
        hint?: string;
        modelValue?: string | number;
        defaultValue?: string | number;
        placeholder?: string;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
        locale?: string;
        currency?: string;
    }>(),
    {
        required: false,
        error: '',
        hint: '',
        modelValue: undefined,
        defaultValue: undefined,
        placeholder: '',
        disabled: false,
        class: undefined,
        locale: 'pt-BR',
        currency: 'BRL',
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string): void;
}>();

const displayValue = ref('');
const hiddenValue = ref('');

const sourceValue = computed(() => props.modelValue ?? props.defaultValue ?? '');

const formatter = computed(
    () =>
        new Intl.NumberFormat(props.locale, {
            style: 'currency',
            currency: props.currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }),
);

watch(
    sourceValue,
    (value) => {
        const numeric = Number(String(value ?? '').replace(',', '.'));

        if (!Number.isFinite(numeric)) {
            hiddenValue.value = '';
            displayValue.value = '';

            return;
        }

        const normalized = numeric.toFixed(2);
        hiddenValue.value = normalized;
        displayValue.value = formatter.value.format(numeric);
    },
    { immediate: true },
);

function handleInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const digits = target.value.replace(/\D/g, '');

    if (digits === '') {
        hiddenValue.value = '';
        displayValue.value = '';
        emits('update:modelValue', '');

        return;
    }

    const numeric = Number(digits) / 100;
    const normalized = numeric.toFixed(2);

    hiddenValue.value = normalized;
    displayValue.value = formatter.value.format(numeric);
    emits('update:modelValue', normalized);
}
</script>

<template>
    <div :class="cn('flex flex-col gap-y-1', props.class)">
        <Label :for="id">
            {{ label }}
            <slot name="label-extra">
                <span v-if="required" class="text-destructive">*</span>
            </slot>
        </Label>

        <input
            :id="id"
            :value="displayValue"
            type="text"
            inputmode="numeric"
            :required="required"
            :placeholder="placeholder"
            :disabled="disabled"
            class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
            @input="handleInput"
        />

        <input type="hidden" :name="name" :value="hiddenValue" />

        <slot name="help">
            <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        </slot>

        <InputError :message="error" />

        <slot name="after" />
    </div>
</template>
