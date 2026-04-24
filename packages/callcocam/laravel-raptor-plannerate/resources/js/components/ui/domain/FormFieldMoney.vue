<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';

// ─── Configuração de moedas ───────────────────────────────────────────────────

interface CurrencyConfig {
    locale: string;
    code: string;
    symbol: string;
    /** Casas decimais (0 para CLP, PYG) */
    decimals: number;
}

const CURRENCIES: Record<string, CurrencyConfig> = {
    BRL: { locale: 'pt-BR', code: 'BRL', symbol: 'R$',  decimals: 2 },
    USD: { locale: 'en-US', code: 'USD', symbol: '$',   decimals: 2 },
    EUR: { locale: 'de-DE', code: 'EUR', symbol: '€',   decimals: 2 },
    GBP: { locale: 'en-GB', code: 'GBP', symbol: '£',   decimals: 2 },
    ARS: { locale: 'es-AR', code: 'ARS', symbol: '$',   decimals: 2 },
    MXN: { locale: 'es-MX', code: 'MXN', symbol: '$',   decimals: 2 },
    CLP: { locale: 'es-CL', code: 'CLP', symbol: '$',   decimals: 0 },
    PYG: { locale: 'es-PY', code: 'PYG', symbol: '₲',   decimals: 0 },
    UYU: { locale: 'es-UY', code: 'UYU', symbol: '$U',  decimals: 2 },
    BOB: { locale: 'es-BO', code: 'BOB', symbol: 'Bs',  decimals: 2 },
    COP: { locale: 'es-CO', code: 'COP', symbol: '$',   decimals: 2 },
    PEN: { locale: 'es-PE', code: 'PEN', symbol: 'S/',  decimals: 2 },
    VEF: { locale: 'es-VE', code: 'VEF', symbol: 'Bs',  decimals: 2 },
};

export type CurrencyCode = keyof typeof CURRENCIES;

// ─── Props ────────────────────────────────────────────────────────────────────

interface Props {
    /** Label do campo */
    label: string;
    /**
     * Valor numérico em unidade maior (ex: 1500.50 para R$ 1.500,50).
     * null = campo vazio.
     */
    modelValue?: number | null;
    /** Código da moeda (default: BRL) */
    currency?: CurrencyCode;
    /** Exibe o símbolo da moeda como prefixo (default: true) */
    showSymbol?: boolean;
    /** Permite valores negativos */
    allowNegative?: boolean;
    /** Valor mínimo permitido */
    min?: number;
    /** Valor máximo permitido */
    max?: number;
    error?: string;
    hint?: string;
    helpText?: string;
    required?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    actionLabel?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    currency: 'BRL',
    showSymbol: true,
    allowNegative: false,
    min: undefined,
    max: undefined,
    error: undefined,
    hint: undefined,
    helpText: undefined,
    required: false,
    disabled: false,
    readonly: false,
    actionLabel: undefined,
    class: undefined,
});

const emit = defineEmits<{
    /** Valor numérico em unidade maior (ex: 1500.50) ou null */
    'update:modelValue': [value: number | null];
    'focus': [event: FocusEvent];
    'blur': [event: FocusEvent];
    'action': [];
}>();

// ─── Acessibilidade ───────────────────────────────────────────────────────────

const id = useId();
const hintId = computed(() => `${id}-hint`);
const errorId = computed(() => `${id}-error`);

const describedBy = computed(() => {
    const ids: string[] = [];
    if (props.error) ids.push(errorId.value);
    else if (props.hint) ids.push(hintId.value);
    return ids.join(' ') || undefined;
});

// ─── Estado interno ───────────────────────────────────────────────────────────

const isFocused = ref(false);
const isNegative = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

/** Valor em "centavos" (unidade mínima da moeda) */
const internalUnits = ref(0);

const currencyConfig = computed((): CurrencyConfig => {
    return CURRENCIES[props.currency] ?? CURRENCIES['BRL'];
});

const multiplier = computed(() => Math.pow(10, currencyConfig.value.decimals));

// ─── Sincronização com modelValue ─────────────────────────────────────────────

watch(
    () => props.modelValue,
    (val) => {
        if (val === null || val === undefined) {
            internalUnits.value = 0;
            isNegative.value = false;
        } else {
            isNegative.value = val < 0;
            internalUnits.value = Math.round(Math.abs(val) * multiplier.value);
        }
    },
    { immediate: true },
);

// ─── Formatação ───────────────────────────────────────────────────────────────

function formatUnits(units: number, negative: boolean): string {
    const cfg = currencyConfig.value;
    const value = units / multiplier.value;
    const formatted = new Intl.NumberFormat(cfg.locale, {
        minimumFractionDigits: cfg.decimals,
        maximumFractionDigits: cfg.decimals,
    }).format(value);
    return negative && units > 0 ? `-${formatted}` : formatted;
}

const displayValue = computed(() => formatUnits(internalUnits.value, isNegative.value));

const isOverMax = computed(() => {
    if (props.max === undefined) return false;
    return (internalUnits.value / multiplier.value) > props.max;
});

const isUnderMin = computed(() => {
    if (props.min === undefined) return false;
    return internalUnits.value > 0 && (internalUnits.value / multiplier.value) < props.min;
});

// ─── Emissão de valor ─────────────────────────────────────────────────────────

function emitValue() {
    if (internalUnits.value === 0) {
        emit('update:modelValue', null);
        return;
    }
    const value = internalUnits.value / multiplier.value;
    emit('update:modelValue', isNegative.value ? -value : value);
}

// ─── Controle de input (estilo ATM) ──────────────────────────────────────────
// Dígitos entram da direita: 0 → 0,05 → 0,50 → 5,00 → 50,00 etc.

function onKeydown(e: KeyboardEvent) {
    // Permite navegação e atalhos de teclado
    if (e.ctrlKey || e.metaKey) return;
    if (['Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;

    e.preventDefault();

    if (e.key >= '0' && e.key <= '9') {
        const digit = parseInt(e.key);
        const next = internalUnits.value * 10 + digit;
        // Verifica limite máximo
        if (props.max !== undefined && next / multiplier.value > props.max) return;
        internalUnits.value = next;
        emitValue();
        return;
    }

    if (e.key === 'Backspace') {
        internalUnits.value = Math.floor(internalUnits.value / 10);
        emitValue();
        return;
    }

    if (e.key === 'Delete' || e.key === 'Escape') {
        internalUnits.value = 0;
        isNegative.value = false;
        emitValue();
        return;
    }

    if (e.key === '-' && props.allowNegative) {
        if (internalUnits.value > 0) {
            isNegative.value = !isNegative.value;
            emitValue();
        }
        return;
    }
}

function onFocus(e: FocusEvent) {
    isFocused.value = true;
    emit('focus', e);
    // Seleciona tudo para indicar que o campo está ativo
    nextTick(() => inputRef.value?.select());
}

function onBlur(e: FocusEvent) {
    isFocused.value = false;
    // Aplica min se necessário
    if (props.min !== undefined && internalUnits.value > 0) {
        const current = internalUnits.value / multiplier.value;
        if (current < props.min) {
            internalUnits.value = Math.round(props.min * multiplier.value);
            emitValue();
        }
    }
    emit('blur', e);
}

function onClear() {
    internalUnits.value = 0;
    isNegative.value = false;
    emitValue();
    inputRef.value?.focus();
}

// nextTick import
function nextTick(fn: () => void) {
    Promise.resolve().then(fn);
}

// ─── Classes ──────────────────────────────────────────────────────────────────

const wrapperClasses = computed(() => [
    'flex items-center rounded-lg border transition-all',
    props.error || isOverMax.value || isUnderMin.value
        ? 'border-destructive bg-destructive/5'
        : 'border-border bg-background hover:border-ring/50',
    isFocused.value && !props.error && !isOverMax.value
        ? 'ring-1 ring-primary border-primary'
        : '',
    isFocused.value && (props.error || isOverMax.value)
        ? 'ring-1 ring-destructive'
        : '',
    props.disabled ? 'opacity-50 cursor-not-allowed' : '',
]);

const labelClasses = computed(() => [
    'block text-xs font-mono uppercase tracking-wider font-medium',
    props.error || isOverMax.value || isUnderMin.value
        ? 'text-destructive'
        : 'text-muted-foreground',
]);

const valueClasses = computed(() => [
    'flex-1 min-w-0 bg-transparent py-2.5 text-sm outline-none text-right font-mono tabular-nums caret-transparent',
    'disabled:cursor-not-allowed select-none',
    isNegative.value && internalUnits.value > 0 ? 'text-destructive' : 'text-foreground',
]);
</script>

<template>
    <div :class="['space-y-1.5', props.class]">
        <!-- Label -->
        <label :for="id" :class="labelClasses">
            {{ label }}
            <span v-if="required" class="text-destructive ml-0.5" aria-hidden="true">*</span>
            <span
                v-if="hint && !error"
                class="material-symbols-outlined text-[13px] text-muted-foreground/60 cursor-help ml-0.5 align-middle"
                :title="hint"
                aria-hidden="true"
            >help</span>
        </label>

        <!-- Input row -->
        <div class="flex items-start gap-2">
            <div :class="[...wrapperClasses, 'flex-1 px-3']">
                <!-- Símbolo da moeda (prefix) -->
                <div class="shrink-0 flex items-center mr-2">
                    <slot name="prefix">
                        <span
                            v-if="showSymbol"
                            :class="[
                                'text-sm font-mono font-medium select-none',
                                isNegative && internalUnits > 0
                                    ? 'text-destructive'
                                    : isFocused
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                            ]"
                        >{{ currencyConfig.symbol }}</span>
                    </slot>
                </div>

                <!-- Input invisível para foco/teclado -->
                <input
                    :id="id"
                    ref="inputRef"
                    :value="displayValue"
                    :disabled="disabled"
                    :readonly="readonly"
                    :aria-invalid="!!error || isOverMax || isUnderMin"
                    :aria-describedby="describedBy"
                    :aria-required="required"
                    :aria-label="`${label}: ${currencyConfig.symbol} ${displayValue}`"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    spellcheck="false"
                    :class="valueClasses"
                    @keydown="onKeydown"
                    @focus="onFocus"
                    @blur="onBlur"
                />

                <!-- Suffix: estados + ações -->
                <div class="shrink-0 flex items-center gap-1 ml-2 text-muted-foreground">
                    <!-- Ícone de erro -->
                    <span
                        v-if="error || isOverMax || isUnderMin"
                        class="material-symbols-outlined text-[18px] text-destructive"
                        aria-hidden="true"
                    >error</span>

                    <!-- Sinal negativo (quando allowNegative) -->
                    <button
                        v-else-if="allowNegative && internalUnits > 0 && !disabled && !readonly"
                        type="button"
                        :class="[
                            'material-symbols-outlined text-[18px] transition-colors',
                            isNegative ? 'text-destructive' : 'hover:text-foreground',
                        ]"
                        :aria-label="isNegative ? 'Tornar positivo' : 'Tornar negativo'"
                        :title="isNegative ? 'Tornar positivo' : 'Tornar negativo'"
                        @click="isNegative = !isNegative; emitValue()"
                    >{{ isNegative ? 'remove' : 'add' }}</button>

                    <!-- Limpar -->
                    <button
                        v-if="internalUnits > 0 && !disabled && !readonly"
                        type="button"
                        class="material-symbols-outlined text-[18px] hover:text-destructive transition-colors"
                        aria-label="Limpar valor"
                        @click="onClear"
                    >close</button>

                    <slot name="suffix" />
                </div>
            </div>

            <!-- Botão de ação lateral -->
            <button
                v-if="actionLabel || $slots.action"
                type="button"
                :disabled="disabled"
                class="shrink-0 h-10 px-3 rounded-lg border border-border bg-background text-sm text-muted-foreground hover:text-foreground hover:border-ring/50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                @click="emit('action')"
            >
                <slot name="action">{{ actionLabel }}</slot>
            </button>
        </div>

        <!-- Limites min/max -->
        <p
            v-if="isOverMax"
            role="alert"
            class="text-xs text-destructive flex items-center gap-1"
        >
            <span class="material-symbols-outlined text-[14px] leading-none shrink-0" aria-hidden="true">error</span>
            Valor máximo: {{ currencyConfig.symbol }} {{ new Intl.NumberFormat(currencyConfig.locale, { minimumFractionDigits: currencyConfig.decimals }).format(max!) }}
        </p>
        <p
            v-else-if="isUnderMin && !isFocused"
            role="alert"
            class="text-xs text-amber-500 flex items-center gap-1"
        >
            <span class="material-symbols-outlined text-[14px] leading-none shrink-0" aria-hidden="true">warning</span>
            Valor mínimo: {{ currencyConfig.symbol }} {{ new Intl.NumberFormat(currencyConfig.locale, { minimumFractionDigits: currencyConfig.decimals }).format(min!) }}
        </p>

        <!-- Erro customizado -->
        <p v-else-if="error" :id="errorId" role="alert" class="text-xs text-destructive flex items-center gap-1">
            <slot name="error-icon">
                <span class="material-symbols-outlined text-[14px] leading-none shrink-0" aria-hidden="true">error</span>
            </slot>
            {{ error }}
        </p>

        <!-- Hint -->
        <div v-else-if="$slots.hint || hint" :id="hintId" class="text-xs text-muted-foreground">
            <slot name="hint">{{ hint }}</slot>
        </div>

        <!-- Help text -->
        <p v-if="helpText" class="text-xs text-muted-foreground/60 flex items-start gap-1">
            <span class="material-symbols-outlined text-[13px] leading-none mt-px shrink-0" aria-hidden="true">info</span>
            {{ helpText }}
        </p>
    </div>
</template>
