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
        decimals?: number;
        allowNegative?: boolean;
        rightToLeft?: boolean;
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
        decimals: 2,
        allowNegative: false,
        rightToLeft: true,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string): void;
}>();

const displayValue = ref('');
const hiddenValue = ref('');

const sourceValue = computed(() => props.modelValue ?? props.defaultValue ?? '');

watch(
    sourceValue,
    (value) => {
        const state = buildDecimalStateFromStoredValue(
            String(value ?? ''),
            props.decimals,
            props.allowNegative,
        );
        hiddenValue.value = state.hidden;
        displayValue.value = state.display;
    },
    { immediate: true },
);

function buildDecimalStateFromStoredValue(
    raw: string,
    decimals: number,
    allowNegative: boolean,
): { hidden: string; display: string } {
    if (raw.trim() === '') {
        return { hidden: '', display: '' };
    }

    const parsed = Number(raw.replace(/\s+/g, '').replace(',', '.'));

    if (!Number.isFinite(parsed)) {
        return { hidden: '', display: '' };
    }

    const safeDecimals = Math.max(decimals, 0);
    const absolute = Math.abs(parsed).toFixed(safeDecimals);
    const hidden = parsed < 0 && allowNegative ? `-${absolute}` : absolute;

    return {
        hidden,
        display: formatDisplayFromHidden(hidden, safeDecimals),
    };
}

function buildDecimalStateFromRaw(
    raw: string,
    decimals: number,
    allowNegative: boolean,
): { hidden: string; display: string } {
    if (raw.trim() === '') {
        return { hidden: '', display: '' };
    }

    const hasNegativeSignal = allowNegative && raw.trim().startsWith('-');
    const digitsOnly = raw.replace(/\D/g, '');

    if (props.rightToLeft) {
        return buildRightToLeftState(digitsOnly, decimals, hasNegativeSignal);
    }

    return buildStandardState(raw, decimals, hasNegativeSignal);
}

function buildRightToLeftState(
    digitsOnly: string,
    decimals: number,
    isNegative: boolean,
): { hidden: string; display: string } {
    if (digitsOnly === '') {
        return { hidden: '', display: '' };
    }

    const safeDecimals = Math.max(decimals, 0);
    const padded = digitsOnly.padStart(safeDecimals + 1, '0');
    const integerDigitsRaw = safeDecimals > 0 ? padded.slice(0, -safeDecimals) : padded;
    const decimalDigits = safeDecimals > 0 ? padded.slice(-safeDecimals) : '';
    const integerDigits = integerDigitsRaw.replace(/^0+(?=\d)/, '');
    const signed = isNegative ? '-' : '';
    const hidden = safeDecimals > 0 ? `${signed}${integerDigits}.${decimalDigits}` : `${signed}${integerDigits}`;
    const display = formatDisplayFromHidden(hidden, safeDecimals);

    return { hidden, display };
}

function buildStandardState(
    raw: string,
    decimals: number,
    isNegative: boolean,
): { hidden: string; display: string } {
    const input = raw.replace(/\s+/g, '');
    const commaIndex = input.lastIndexOf(',');
    const dotIndex = input.lastIndexOf('.');
    const separatorIndex = Math.max(commaIndex, dotIndex);
    const hasSeparator = separatorIndex >= 0;

    const integerRaw = hasSeparator ? input.slice(0, separatorIndex) : input;
    const decimalRaw = hasSeparator ? input.slice(separatorIndex + 1) : '';
    let integerDigits = integerRaw.replace(/\D/g, '');
    const decimalDigits = decimalRaw.replace(/\D/g, '').slice(0, Math.max(decimals, 0));

    const hasAnyDigit = integerDigits !== '' || decimalDigits !== '';

    if (!hasAnyDigit) {
        return { hidden: '', display: '' };
    }

    if (integerDigits === '') {
        integerDigits = '0';
    }

    const normalizedInteger = integerDigits.replace(/^0+(?=\d)/, '');
    const signed = isNegative ? '-' : '';
    const hidden = decimalDigits !== '' ? `${signed}${normalizedInteger}.${decimalDigits}` : `${signed}${normalizedInteger}`;
    const display = hasSeparator
        ? formatDisplayFromHidden(hidden, Math.max(decimals, 0))
        : `${signed}${normalizedInteger.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;

    return { hidden, display };
}

function formatDisplayFromHidden(hidden: string, decimals: number): string {
    if (hidden === '') {
        return '';
    }

    const isNegative = hidden.startsWith('-');
    const unsigned = isNegative ? hidden.slice(1) : hidden;
    const [integerPartRaw = '0', decimalPartRaw = ''] = unsigned.split('.');
    const integerPart = integerPartRaw.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const fixedDecimals = Math.max(decimals, 0);
    const decimalPart = decimalPartRaw.padEnd(fixedDecimals, '0').slice(0, fixedDecimals);
    const signed = isNegative ? '-' : '';

    return fixedDecimals > 0 ? `${signed}${integerPart},${decimalPart}` : `${signed}${integerPart}`;
}

function handleInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const state = buildDecimalStateFromRaw(target.value, props.decimals, props.allowNegative);

    hiddenValue.value = state.hidden;
    displayValue.value = state.display;
    emits('update:modelValue', state.hidden);
}

function handleBlur(): void {
    if (hiddenValue.value === '') {
        return;
    }

    const parsed = Number(hiddenValue.value);

    if (!Number.isFinite(parsed)) {
        return;
    }

    const fixed = parsed.toFixed(props.decimals);
    hiddenValue.value = fixed;
    displayValue.value = formatDisplayFromHidden(fixed, props.decimals);
    emits('update:modelValue', fixed);
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
            inputmode="decimal"
            :required="required"
            :placeholder="placeholder"
            :disabled="disabled"
            class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
            @input="handleInput"
            @blur="handleBlur"
        />

        <input type="hidden" :name="name" :value="hiddenValue" />

        <slot name="help">
            <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>
        </slot>

        <InputError :message="error" />

        <slot name="after" />
    </div>
</template>
