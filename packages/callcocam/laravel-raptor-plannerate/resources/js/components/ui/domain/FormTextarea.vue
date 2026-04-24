<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue';

interface Props {
    /** Label exibido acima do campo */
    label: string;
    /** Mensagem de erro — ativa o estado de erro quando presente */
    error?: string;
    /** Campo obrigatório — exibe asterisco no label */
    required?: boolean;
    /** Valor controlado via v-model */
    modelValue?: string | null;
    /** Texto de placeholder */
    placeholder?: string;
    /** Número fixo de linhas (ignorado quando autoResize=true) */
    rows?: number;
    /** Limite máximo de linhas no auto-resize */
    maxRows?: number;
    /** Cresce automaticamente com o conteúdo */
    autoResize?: boolean;
    /** Limite máximo de caracteres — exibe contador */
    maxlength?: number;
    /** Desabilita o campo */
    disabled?: boolean;
    /** Campo somente leitura */
    readonly?: boolean;
    /** Texto auxiliar abaixo do campo (oculto quando há erro) */
    hint?: string;
    /** Texto de ajuda permanente — sempre visível abaixo do hint/erro */
    helpText?: string;
    /** Texto do botão de ação lateral */
    actionLabel?: string;
    /** Classe CSS adicional no wrapper externo */
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    rows: 3,
    maxRows: 12,
    autoResize: false,
    required: false,
    disabled: false,
    readonly: false,
    error: undefined,
    modelValue: undefined,
    placeholder: undefined,
    hint: undefined,
    helpText: undefined,
    maxlength: undefined,
    actionLabel: undefined,
    class: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
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
    else if (props.hint || hasHintSlot.value) ids.push(hintId.value);
    return ids.join(' ') || undefined;
});

// ─── Estado interno ───────────────────────────────────────────────────────────

const isFocused = ref(false);
const textareaRef = ref<HTMLTextAreaElement | null>(null);

const charCount = computed(() => (props.modelValue ?? '').length);
const isOverLimit = computed(() => props.maxlength !== undefined && charCount.value > props.maxlength);

const slots = defineSlots<{
    suffix?(): unknown;
    action?(): unknown;
    hint?(): unknown;
    'error-icon'?(): unknown;
}>();

const hasHintSlot = computed(() => !!slots.hint);

// ─── Auto-resize ──────────────────────────────────────────────────────────────

function resize() {
    const el = textareaRef.value;
    if (!el || !props.autoResize) return;

    el.style.height = 'auto';
    const lineHeight = parseInt(getComputedStyle(el).lineHeight) || 20;
    const paddingY = parseInt(getComputedStyle(el).paddingTop) + parseInt(getComputedStyle(el).paddingBottom);
    const maxHeight = props.maxRows * lineHeight + paddingY;

    el.style.height = `${Math.min(el.scrollHeight, maxHeight)}px`;
    el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
}

watch(() => props.modelValue, () => {
    nextTick(resize);
});

// ─── Classes computadas ───────────────────────────────────────────────────────

const wrapperClasses = computed(() => [
    'relative rounded-lg border transition-all',
    props.error
        ? 'border-destructive bg-destructive/5'
        : 'border-border bg-background hover:border-ring/50',
    isFocused.value && !props.error
        ? 'ring-1 ring-primary border-primary'
        : '',
    isFocused.value && props.error
        ? 'ring-1 ring-destructive'
        : '',
    props.disabled || props.readonly
        ? 'opacity-50 cursor-not-allowed'
        : '',
]);

const labelClasses = computed(() => [
    'block text-xs font-mono uppercase tracking-wider font-medium',
    props.error ? 'text-destructive' : 'text-muted-foreground',
    props.disabled ? 'opacity-60' : '',
]);

// ─── Handlers ─────────────────────────────────────────────────────────────────

function onFocus(e: FocusEvent) {
    isFocused.value = true;
    emit('focus', e);
}

function onBlur(e: FocusEvent) {
    isFocused.value = false;
    emit('blur', e);
}

function onInput(e: Event) {
    const value = (e.target as HTMLTextAreaElement).value;
    emit('update:modelValue', value);
    if (props.autoResize) nextTick(resize);
}
</script>

<template>
    <div :class="['space-y-1.5', props.class]">
        <!-- Label -->
        <label :for="id" :class="labelClasses">
            {{ label }}
            <span v-if="required" class="text-destructive ml-0.5" aria-hidden="true">*</span>

            <!-- Hint tooltip no label -->
            <span
                v-if="hint && !error"
                class="material-symbols-outlined text-[13px] text-muted-foreground/60 cursor-help ml-0.5 align-middle"
                :title="hint"
                aria-hidden="true"
            >help</span>
        </label>

        <!-- Textarea row (campo + botão lateral) -->
        <div class="flex items-start gap-2">
            <!-- Wrapper com borda -->
            <div :class="[...wrapperClasses, 'flex-1']">
                <textarea
                    :id="id"
                    ref="textareaRef"
                    :value="modelValue ?? ''"
                    :rows="autoResize ? undefined : rows"
                    :placeholder="placeholder"
                    :disabled="disabled"
                    :readonly="readonly"
                    :maxlength="maxlength"
                    :aria-invalid="!!error"
                    :aria-describedby="describedBy"
                    :aria-required="required"
                    :class="[
                        'w-full bg-transparent px-3 py-2.5 text-sm text-foreground outline-none',
                        'placeholder:text-muted-foreground/50 transition-all',
                        autoResize ? 'resize-none overflow-hidden' : 'resize-none',
                        disabled || readonly ? 'cursor-not-allowed' : '',
                    ]"
                    @input="onInput"
                    @focus="onFocus"
                    @blur="onBlur"
                />

                <!-- Rodapé interno: suffix slot + contador de caracteres -->
                <div
                    v-if="$slots.suffix || maxlength !== undefined"
                    class="flex items-center justify-between px-3 pb-2 gap-2"
                >
                    <!-- Slot suffix (ícones, ações) -->
                    <div class="flex items-center gap-1 text-muted-foreground">
                        <slot name="suffix" />
                    </div>

                    <!-- Contador de caracteres -->
                    <span
                        v-if="maxlength !== undefined"
                        :class="[
                            'text-xs font-mono tabular-nums ml-auto transition-colors',
                            isOverLimit
                                ? 'text-destructive font-medium'
                                : charCount > maxlength * 0.85
                                    ? 'text-amber-500'
                                    : 'text-muted-foreground/60',
                        ]"
                        :aria-live="isOverLimit ? 'assertive' : 'polite'"
                    >{{ charCount }}/{{ maxlength }}</span>
                </div>

                <!-- Ícone de erro / loading interno -->
                <div
                    v-if="error || $slots['error-icon']"
                    class="absolute top-2.5 right-2.5 text-destructive"
                    aria-hidden="true"
                >
                    <slot name="error-icon">
                        <span class="material-symbols-outlined text-[16px]">error</span>
                    </slot>
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

        <!-- Mensagem de erro -->
        <p
            v-if="error"
            :id="errorId"
            role="alert"
            class="text-xs text-destructive flex items-center gap-1"
        >
            <slot name="error-icon">
                <span class="material-symbols-outlined text-[14px] leading-none shrink-0" aria-hidden="true">error</span>
            </slot>
            {{ error }}
        </p>

        <!-- Hint (oculto quando há erro) -->
        <div
            v-else-if="$slots.hint || hint"
            :id="hintId"
            class="text-xs text-muted-foreground"
        >
            <slot name="hint">{{ hint }}</slot>
        </div>

        <!-- Help text permanente -->
        <p v-if="helpText" class="text-xs text-muted-foreground/60 flex items-start gap-1">
            <span class="material-symbols-outlined text-[13px] leading-none mt-px shrink-0" aria-hidden="true">info</span>
            {{ helpText }}
        </p>
    </div>
</template>
