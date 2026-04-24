<script setup lang="ts">
import { computed, ref, useId } from 'vue';

// ─── Tipos ────────────────────────────────────────────────────────────────────

/**
 * Presets disponíveis:
 * - cpf         → 123.456.789-01
 * - cnpj        → 12.345.678/0001-90
 * - phone       → (11) 99999-9999 / (11) 3333-4444 (dinâmico)
 * - cep         → 12345-678
 * - date        → 31/12/2024
 * - time        → 23:59
 * - datetime    → 31/12/2024 23:59
 * - rg          → 12.345.678-X
 * - plate       → ABC-1234 / ABC-1D23 (Mercosul, dinâmico)
 *
 * Ou passe um padrão customizado:
 * - # = dígito
 * - A = letra (maiúscula)
 * - * = alfanumérico (maiúsculo)
 * - qualquer outro char = literal (ex: '.', '-', '/', ' ')
 */
type MaskPreset = 'cpf' | 'cnpj' | 'phone' | 'cep' | 'date' | 'time' | 'datetime' | 'rg' | 'plate';

interface Props {
    /** Label do campo */
    label: string;
    /** Valor mascarado via v-model (ex: "123.456.789-01") */
    modelValue?: string | null;
    /**
     * Preset de máscara ou padrão customizado.
     * Padrão: # = dígito, A = letra, * = alfanumérico
     */
    mask: MaskPreset | string;
    /** Placeholder customizado — gerado automaticamente da máscara se não informado */
    placeholder?: string;
    error?: string;
    hint?: string;
    helpText?: string;
    required?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    clearable?: boolean;
    actionLabel?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: undefined,
    placeholder: undefined,
    error: undefined,
    hint: undefined,
    helpText: undefined,
    required: false,
    disabled: false,
    readonly: false,
    clearable: true,
    actionLabel: undefined,
    class: undefined,
});

const emit = defineEmits<{
    /** Valor mascarado (ex: "123.456.789-01") */
    'update:modelValue': [value: string];
    /** Apenas os caracteres de entrada, sem literais da máscara */
    'raw': [value: string];
    /** Emitido quando o campo atingiu o comprimento máximo da máscara */
    'complete': [];
    'focus': [event: FocusEvent];
    'blur': [event: FocusEvent];
    'clear': [];
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
const inputRef = ref<HTMLInputElement | null>(null);

// ─── Engine de máscara ────────────────────────────────────────────────────────

/** Caracteres que representam posições de entrada na máscara */
const INPUT_CHARS = new Set(['#', 'A', '*']);

/** Resolve o padrão da máscara para o valor raw atual */
function resolvePattern(raw: string): string {
    switch (props.mask as MaskPreset) {
        case 'cpf':
            return '###.###.###-##';
        case 'cnpj':
            return '##.###.###/####-##';
        case 'phone': {
            const digits = raw.replace(/\D/g, '');
            return digits.length <= 10
                ? '(##) ####-####'
                : '(##) #####-####';
        }
        case 'cep':
            return '#####-###';
        case 'date':
            return '##/##/####';
        case 'time':
            return '##:##';
        case 'datetime':
            return '##/##/#### ##:##';
        case 'rg':
            return '##.###.###-*';
        case 'plate': {
            // Detecta Mercosul: 4ª posição (index 3) é dígito, 5ª (index 4) é letra
            const clean = raw.replace(/[^a-zA-Z0-9]/g, '');
            if (clean.length >= 5 && /[a-zA-Z]/.test(clean[4])) {
                return 'AAA-#A##'; // Mercosul: ABC-1D23
            }
            return 'AAA-####'; // Antigo: ABC-1234
        }
        default:
            return props.mask; // Custom pattern
    }
}

/** Extrai apenas os caracteres de entrada válidos do valor mascarado */
function extractRaw(masked: string, pattern: string): string {
    let raw = '';
    let patternIndex = 0;

    for (let i = 0; i < masked.length && patternIndex < pattern.length; i++) {
        const patternChar = pattern[patternIndex];
        const char = masked[i];

        if (INPUT_CHARS.has(patternChar)) {
            // Posição de entrada: aceita o char se válido
            if (isValidForPosition(char, patternChar)) {
                raw += char.toUpperCase();
                patternIndex++;
            } else if (/[^a-zA-Z0-9]/.test(char)) {
                // Char inválido (literal inesperado) — pula sem avançar pattern
            } else {
                patternIndex++;
                i--; // tenta próxima posição do padrão com o mesmo char
            }
        } else {
            // Literal no padrão
            if (char === patternChar) {
                patternIndex++;
            } else {
                patternIndex++; // pula literal do padrão
                i--; // retenta com mesmo char
            }
        }
    }

    return raw;
}

function isValidForPosition(char: string, patternChar: string): boolean {
    switch (patternChar) {
        case '#': return /\d/.test(char);
        case 'A': return /[a-zA-Z]/.test(char);
        case '*': return /[a-zA-Z0-9]/.test(char);
        default: return false;
    }
}

/** Aplica a máscara ao raw e retorna o valor formatado */
function applyMask(raw: string, pattern: string): string {
    let result = '';
    let rawIndex = 0;

    for (let maskIndex = 0; maskIndex < pattern.length && rawIndex < raw.length; maskIndex++) {
        const maskChar = pattern[maskIndex];

        if (INPUT_CHARS.has(maskChar)) {
            const char = raw[rawIndex];
            if (isValidForPosition(char, maskChar)) {
                result += maskChar === '#' ? char : char.toUpperCase();
                rawIndex++;
            } else {
                rawIndex++;
                maskIndex--; // retenta com próximo raw char
            }
        } else {
            // Insere literal automaticamente
            result += maskChar;
        }
    }

    return result;
}

/** Gera o placeholder a partir da máscara */
const autoPlaceholder = computed(() => {
    if (props.placeholder) return props.placeholder;
    const pattern = resolvePattern('');
    return pattern
        .replace(/#/g, '0')
        .replace(/A/g, 'A')
        .replace(/\*/g, 'X');
});

const maxLength = computed(() => resolvePattern('').length);

const hasValue = computed(() => {
    const v = props.modelValue;
    return v !== null && v !== undefined && v !== '';
});

// ─── Handler de input ─────────────────────────────────────────────────────────

function onInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const rawDirty = target.value;

    // Resolve padrão baseado no input atual (para máscaras dinâmicas)
    const pattern = resolvePattern(rawDirty);
    const raw = extractRaw(rawDirty, pattern);
    const masked = applyMask(raw, pattern);

    // Atualiza o input diretamente para controlar o valor exato
    target.value = masked;

    // Posiciona cursor ao final do conteúdo digitado
    const pos = masked.length;
    target.setSelectionRange(pos, pos);

    emit('update:modelValue', masked);
    emit('raw', raw);

    if (masked.length === pattern.length) {
        emit('complete');
    }
}

function onKeydown(e: KeyboardEvent) {
    // Permite: Tab, Backspace, Delete, setas, Ctrl+A/C/V/X
    if (
        e.key === 'Tab' ||
        e.key === 'Backspace' ||
        e.key === 'Delete' ||
        e.key === 'ArrowLeft' ||
        e.key === 'ArrowRight' ||
        e.key === 'Home' ||
        e.key === 'End' ||
        (e.ctrlKey || e.metaKey)
    ) return;

    const pattern = resolvePattern(props.modelValue ?? '');

    // Bloqueia caracteres inválidos baseado na próxima posição livre
    const currentRaw = extractRaw(props.modelValue ?? '', pattern);
    let nextPos = 0;
    for (let i = 0; i < pattern.length; i++) {
        if (INPUT_CHARS.has(pattern[i])) {
            if (nextPos === currentRaw.length) {
                if (!isValidForPosition(e.key, pattern[i])) {
                    e.preventDefault();
                }
                return;
            }
            nextPos++;
        }
    }
    // Máscara cheia
    e.preventDefault();
}

function onClear() {
    emit('update:modelValue', '');
    emit('raw', '');
    emit('clear');
    inputRef.value?.focus();
}

function onFocus(e: FocusEvent) {
    isFocused.value = true;
    emit('focus', e);
}

function onBlur(e: FocusEvent) {
    isFocused.value = false;
    emit('blur', e);
}

// ─── Valor de exibição ────────────────────────────────────────────────────────

/** Ao receber modelValue inicial (ex: "12345678901"), aplica a máscara */
const displayValue = computed(() => {
    const val = props.modelValue ?? '';
    if (!val) return '';
    const pattern = resolvePattern(val);
    // Se já está mascarado (tem literais), retorna como está
    if (val.length === pattern.length) return val;
    // Senão, aplica a máscara
    const raw = val.replace(/[^a-zA-Z0-9]/g, '');
    return applyMask(raw, pattern);
});

// ─── Classes ──────────────────────────────────────────────────────────────────

const wrapperClasses = computed(() => [
    'flex items-center rounded-lg border transition-all px-3',
    props.error
        ? 'border-destructive bg-destructive/5'
        : 'border-border bg-background hover:border-ring/50',
    isFocused.value && !props.error ? 'ring-1 ring-primary border-primary' : '',
    isFocused.value && props.error ? 'ring-1 ring-destructive' : '',
    props.disabled ? 'opacity-50 cursor-not-allowed' : '',
]);

const labelClasses = computed(() => [
    'block text-xs font-mono uppercase tracking-wider font-medium',
    props.error ? 'text-destructive' : 'text-muted-foreground',
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
            <div :class="[...wrapperClasses, 'flex-1']">
                <!-- Slot prefix -->
                <div v-if="$slots.prefix" class="shrink-0 flex items-center mr-2 text-muted-foreground">
                    <slot name="prefix" />
                </div>

                <input
                    :id="id"
                    ref="inputRef"
                    :value="displayValue"
                    :placeholder="autoPlaceholder"
                    :maxlength="maxLength"
                    :disabled="disabled"
                    :readonly="readonly"
                    :aria-invalid="!!error"
                    :aria-describedby="describedBy"
                    :aria-required="required"
                    inputmode="text"
                    autocomplete="off"
                    spellcheck="false"
                    class="flex-1 min-w-0 bg-transparent py-2.5 text-sm text-foreground outline-none placeholder:text-muted-foreground/50 disabled:cursor-not-allowed font-mono tracking-wider"
                    @input="onInput"
                    @keydown="onKeydown"
                    @focus="onFocus"
                    @blur="onBlur"
                />

                <!-- Suffix: estados + ações -->
                <div class="shrink-0 flex items-center gap-1 ml-2 text-muted-foreground">
                    <span
                        v-if="error"
                        class="material-symbols-outlined text-[18px] text-destructive"
                        aria-hidden="true"
                    >error</span>

                    <button
                        v-else-if="clearable && hasValue && !disabled && !readonly"
                        type="button"
                        class="material-symbols-outlined text-[18px] hover:text-destructive transition-colors"
                        aria-label="Limpar campo"
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

        <!-- Erro -->
        <p v-if="error" :id="errorId" role="alert" class="text-xs text-destructive flex items-center gap-1">
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
