<script setup lang="ts">
import { computed, ref, useId } from 'vue';

interface Props {
    /** Label exibido acima do campo */
    label: string;
    /** Mensagem de erro — ativa o estado de erro quando presente */
    error?: string;
    /** Campo obrigatório — exibe asterisco no label */
    required?: boolean;
    /** Valor controlado via v-model */
    modelValue?: string | number | null;
    /** Tipo do input HTML */
    type?: string;
    /** Texto de placeholder */
    placeholder?: string;
    /** Desabilita o campo */
    disabled?: boolean;
    /** Campo somente leitura */
    readonly?: boolean;
    /** Exibe spinner e bloqueia interação */
    loading?: boolean;
    /** Exibe botão X para limpar o valor */
    clearable?: boolean;
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
    type: 'text',
    required: false,
    disabled: false,
    readonly: false,
    loading: false,
    clearable: false,
    error: undefined,
    modelValue: undefined,
    placeholder: undefined,
    hint: undefined,
    helpText: undefined,
    actionLabel: undefined,
    class: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
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
    else if (props.hint || hasHintSlot.value) ids.push(hintId.value);
    return ids.join(' ') || undefined;
});

// ─── Estado interno ───────────────────────────────────────────────────────────

const isFocused = ref(false);
const isPasswordVisible = ref(false);

const isPassword = computed(() => props.type === 'password');
const effectiveType = computed(() => {
    if (isPassword.value) return isPasswordVisible.value ? 'text' : 'password';
    return props.type;
});

const hasValue = computed(() => {
    const v = props.modelValue;
    return v !== null && v !== undefined && v !== '';
});

// ─── Slots ────────────────────────────────────────────────────────────────────

const slots = defineSlots<{
    prefix?(): unknown;
    suffix?(): unknown;
    action?(): unknown;
    hint?(): unknown;
    'error-icon'?(): unknown;
}>();

const hasHintSlot = computed(() => !!slots.hint);

// ─── Classes computadas ───────────────────────────────────────────────────────

const wrapperClasses = computed(() => [
    'flex items-center rounded-lg border transition-all',
    props.error
        ? 'border-destructive bg-destructive/5'
        : 'border-border bg-background hover:border-ring/50',
    isFocused.value && !props.error
        ? 'ring-1 ring-primary border-primary'
        : '',
    isFocused.value && props.error
        ? 'ring-1 ring-destructive'
        : '',
    props.disabled || props.loading
        ? 'opacity-50 cursor-not-allowed'
        : '',
]);

const labelClasses = computed(() => [
    'block text-xs font-mono uppercase tracking-wider font-medium',
    props.error
        ? 'text-destructive'
        : 'text-muted-foreground',
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

function onClear() {
    emit('update:modelValue', '');
    emit('clear');
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

        <!-- Input row (campo + botão de ação lateral) -->
        <div class="flex items-start gap-2">
            <!-- Wrapper com borda -->
            <div :class="[...wrapperClasses, 'flex-1 px-3']">
                <!-- Slot prefix -->
                <div v-if="$slots.prefix" class="shrink-0 flex items-center leading-none mr-2 text-muted-foreground">
                    <slot name="prefix" />
                </div>

                <!-- Input -->
                <input
                    :id="id"
                    :type="effectiveType"
                    :value="modelValue ?? ''"
                    :placeholder="placeholder"
                    :disabled="disabled || loading"
                    :readonly="readonly"
                    :aria-invalid="!!error"
                    :aria-describedby="describedBy"
                    :aria-required="required"
                    class="flex-1 min-w-0 bg-transparent py-2.5 text-sm text-foreground outline-none placeholder:text-muted-foreground/50 disabled:cursor-not-allowed"
                    @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
                    @focus="onFocus"
                    @blur="onBlur"
                />

                <!-- Suffix interno: ícones de estado e ações -->
                <div class="shrink-0 flex items-center gap-1 ml-2 text-muted-foreground">
                    <!-- Spinner de loading -->
                    <span
                        v-if="loading"
                        class="material-symbols-outlined text-[18px] animate-spin"
                        aria-hidden="true"
                    >progress_activity</span>

                    <!-- Ícone de erro -->
                    <span
                        v-else-if="error"
                        class="material-symbols-outlined text-[18px] text-destructive"
                        aria-hidden="true"
                    >error</span>

                    <!-- Toggle visibilidade de senha -->
                    <button
                        v-else-if="isPassword && !disabled && !loading"
                        type="button"
                        class="material-symbols-outlined text-[18px] hover:text-foreground transition-colors"
                        :aria-label="isPasswordVisible ? 'Ocultar senha' : 'Mostrar senha'"
                        @click="isPasswordVisible = !isPasswordVisible"
                    >{{ isPasswordVisible ? 'visibility_off' : 'visibility' }}</button>

                    <!-- Botão de limpar -->
                    <button
                        v-else-if="clearable && hasValue && !disabled && !readonly && !loading"
                        type="button"
                        class="material-symbols-outlined text-[18px] hover:text-destructive transition-colors"
                        aria-label="Limpar campo"
                        @click="onClear"
                    >close</button>

                    <!-- Slot suffix customizado -->
                    <slot name="suffix" />
                </div>
            </div>

            <!-- Botão de ação lateral -->
            <button
                v-if="actionLabel || $slots.action"
                type="button"
                :disabled="disabled || loading"
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
