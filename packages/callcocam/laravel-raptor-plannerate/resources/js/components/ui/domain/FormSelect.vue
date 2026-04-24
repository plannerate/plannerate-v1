<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export interface SelectOption {
    value: string | number;
    label: string;
    description?: string;
    disabled?: boolean;
    group?: string;
}

interface Props {
    /** Label do campo */
    label: string;
    /** Valor selecionado (v-model) */
    modelValue?: string | number | null;
    /** Opções locais — usadas quando `searchUrl` não é definido */
    options?: SelectOption[];
    /** URL para busca remota. Quando definido, ativa o modo API */
    searchUrl?: string;
    /** Nome do query param de busca (default: 'q') */
    searchParam?: string;
    /** Debounce em ms para busca remota (default: 300) */
    searchDebounce?: number;
    /** Mínimo de caracteres para acionar busca remota (default: 1) */
    searchMinChars?: number;
    placeholder?: string;
    searchPlaceholder?: string;
    /** Texto exibido quando não há opções */
    emptyText?: string;
    /** Mensagem de erro — ativa o estado de erro quando presente */
    error?: string;
    /** Texto auxiliar abaixo do campo (oculto quando há erro) */
    hint?: string;
    /** Texto de ajuda permanente, sempre visível abaixo do hint/erro */
    helpText?: string;
    required?: boolean;
    disabled?: boolean;
    loading?: boolean;
    /** Exibe botão de limpar quando há seleção */
    clearable?: boolean;
    /** Abre a seleção em Dialog em vez de dropdown inline */
    modalMode?: boolean;
    /** Título do Dialog (default: usa o label) */
    modalTitle?: string;
    /** Texto do botão de ação inline ao lado do campo */
    actionLabel?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    options: () => [],
    searchParam: 'q',
    searchDebounce: 300,
    searchMinChars: 1,
    placeholder: 'Selecione...',
    searchPlaceholder: 'Buscar...',
    emptyText: 'Nenhuma opção encontrada',
    error: undefined,
    hint: undefined,
    helpText: undefined,
    required: false,
    disabled: false,
    loading: false,
    clearable: true,
    modalMode: false,
    modalTitle: undefined,
    actionLabel: undefined,
    class: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
    /** Emitido a cada mudança no campo de busca */
    'search': [query: string];
    /** Emitido ao selecionar uma opção */
    'select': [option: SelectOption];
    /** Emitido ao limpar a seleção */
    'clear': [];
    /** Emitido ao clicar no botão de ação */
    'action': [];
    'open': [];
    'close': [];
}>();

// ─── Acessibilidade ───────────────────────────────────────────────────────────

const id = useId();
const hintId = computed(() => `${id}-hint`);
const errorId = computed(() => `${id}-error`);

// ─── Estado interno ────────────────────────────────────────────────────────────

const isOpen = ref(false);
const searchQuery = ref('');
const remoteOptions = ref<SelectOption[]>([]);
const isSearching = ref(false);
const searchError = ref<string | null>(null);
const dropdownRef = ref<HTMLElement | null>(null);
const triggerRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// ─── Posicionamento do dropdown (Teleport) ────────────────────────────────────

const dropdownStyle = ref<Record<string, string>>({});

function updateDropdownPosition() {
    const trigger = triggerRef.value;
    if (!trigger) return;
    const rect = trigger.getBoundingClientRect();
    dropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        zIndex: '9999',
    };
}

// ─── Computadas ───────────────────────────────────────────────────────────────

const filteredOptions = computed<SelectOption[]>(() => {
    if (props.searchUrl) {
        return remoteOptions.value;
    }
    if (!searchQuery.value.trim()) {
        return props.options ?? [];
    }
    const q = searchQuery.value.toLowerCase();
    return (props.options ?? []).filter((opt) => opt.label.toLowerCase().includes(q));
});

const groupedOptions = computed(() => {
    const groups = new Map<string, SelectOption[]>();
    for (const opt of filteredOptions.value) {
        const key = opt.group ?? '';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key)!.push(opt);
    }
    return groups;
});

const hasGroups = computed(() => filteredOptions.value.some((o) => o.group));

const selectedOption = computed<SelectOption | undefined>(() => {
    if (props.modelValue === null || props.modelValue === undefined) return undefined;
    const all = [...(props.options ?? []), ...remoteOptions.value];
    return all.find((o) => o.value === props.modelValue);
});

const displayLabel = computed(() => selectedOption.value?.label ?? '');
const isEmpty = computed(() => !isSearching.value && filteredOptions.value.length === 0);
const modalTitle = computed(() => props.modalTitle ?? props.label);

const describedBy = computed(() => {
    const ids = [];
    if (props.hint && !props.error) ids.push(hintId.value);
    if (props.error) ids.push(errorId.value);
    return ids.join(' ') || undefined;
});

// ─── Métodos ──────────────────────────────────────────────────────────────────

function open() {
    if (props.disabled || props.loading) return;
    updateDropdownPosition();
    isOpen.value = true;
    emit('open');
    nextTick(() => searchInputRef.value?.focus());
}

function close() {
    isOpen.value = false;
    searchQuery.value = '';
    remoteOptions.value = [];
    searchError.value = null;
    emit('close');
}

function toggle() {
    isOpen.value ? close() : open();
}

function selectOption(option: SelectOption) {
    if (option.disabled) return;
    emit('update:modelValue', option.value);
    emit('select', option);
    close();
}

function clearSelection(e?: Event) {
    e?.stopPropagation();
    emit('update:modelValue', null);
    emit('clear');
}

function clearSearch() {
    searchQuery.value = '';
    remoteOptions.value = [];
    searchError.value = null;
}

// ─── Busca remota ─────────────────────────────────────────────────────────────

async function performSearch(q: string) {
    if (!props.searchUrl) return;
    if (q.length < props.searchMinChars) {
        remoteOptions.value = [];
        return;
    }
    isSearching.value = true;
    searchError.value = null;
    try {
        const url = new URL(props.searchUrl, window.location.origin);
        url.searchParams.set(props.searchParam, q);
        const res = await fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data: unknown = await res.json();
        remoteOptions.value = Array.isArray(data)
            ? (data as SelectOption[])
            : ((data as { data: SelectOption[] }).data ?? []);
    } catch {
        searchError.value = 'Erro ao buscar opções';
        remoteOptions.value = [];
    } finally {
        isSearching.value = false;
    }
}

function onSearchInput(e: Event) {
    const q = (e.target as HTMLInputElement).value;
    searchQuery.value = q;
    emit('search', q);
    if (props.searchUrl) {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => performSearch(q), props.searchDebounce);
    }
}

// ─── Click outside / teclado ─────────────────────────────────────────────────

function onClickOutside(e: MouseEvent) {
    if (!isOpen.value || props.modalMode) return;
    const target = e.target as Node;
    if (!dropdownRef.value?.contains(target) && !triggerRef.value?.contains(target)) {
        close();
    }
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && isOpen.value) close();
}

onMounted(() => {
    document.addEventListener('mousedown', onClickOutside);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('scroll', updateDropdownPosition, true);
    window.addEventListener('resize', updateDropdownPosition);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onClickOutside);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('scroll', updateDropdownPosition, true);
    window.removeEventListener('resize', updateDropdownPosition);
    if (debounceTimer) clearTimeout(debounceTimer);
});
</script>

<template>
    <!-- Campo principal -->
    <div :class="['space-y-1.5', props.class]">
        <!-- Label -->
        <label
            :for="id"
            class="flex items-center gap-1.5 text-xs font-mono uppercase tracking-wider font-medium"
            :class="error ? 'text-error' : 'text-on-surface-variant'"
        >
            <slot name="label">
                {{ label }}
                <span v-if="required" class="text-error ml-0.5" aria-hidden="true">*</span>
            </slot>

            <!-- Hint tooltip (ícone ?) -->
            <span
                v-if="hint && !error"
                class="material-symbols-outlined text-[14px] text-on-surface-variant/60 cursor-help"
                :title="hint"
                aria-hidden="true"
            >help</span>
        </label>

        <!-- Trigger + Action Button -->
        <div class="flex items-start gap-2">
            <!-- Wrapper do trigger com dropdown -->
            <div class="relative flex-1" ref="triggerRef">
                <button
                    :id="id"
                    type="button"
                    :disabled="disabled || loading"
                    :aria-expanded="isOpen"
                    :aria-haspopup="'listbox'"
                    :aria-invalid="!!error"
                    :aria-describedby="describedBy"
                    :class="[
                        'w-full flex items-center gap-2 h-10.5 rounded-lg border bg-surface px-3 text-sm',
                        'outline-none transition-all cursor-pointer text-left',
                        'focus:ring-1 focus:ring-primary focus:border-primary',
                        'disabled:opacity-50 disabled:cursor-not-allowed',
                        error
                            ? 'border-error bg-error/5'
                            : 'border-border hover:border-on-surface-variant/40',
                        isOpen && !error ? 'ring-1 ring-primary border-primary' : '',
                    ]"
                    @click="toggle"
                >
                    <!-- Slot prefix -->
                    <slot name="prefix" />

                    <!-- Valor selecionado ou placeholder -->
                    <span
                        :class="[
                            'flex-1 truncate text-left',
                            displayLabel ? 'text-on-surface' : 'text-on-surface-variant/50',
                        ]"
                    >
                        <slot name="display" :option="selectedOption" :label="displayLabel">
                            {{ displayLabel || placeholder }}
                        </slot>
                    </span>

                    <!-- Loading -->
                    <span
                        v-if="loading"
                        class="material-symbols-outlined text-[18px] text-on-surface-variant animate-spin shrink-0"
                        aria-hidden="true"
                    >progress_activity</span>

                    <!-- Ícone de erro inline -->
                    <span
                        v-else-if="error"
                        class="material-symbols-outlined text-[18px] text-error shrink-0"
                        aria-hidden="true"
                    >error</span>

                    <!-- Botão de limpar -->
                    <button
                        v-else-if="clearable && modelValue !== null && modelValue !== undefined && !disabled"
                        type="button"
                        class="material-symbols-outlined text-[18px] text-on-surface-variant hover:text-error transition-colors shrink-0"
                        aria-label="Limpar seleção"
                        @click="clearSelection"
                    >close</button>

                    <!-- Slot suffix -->
                    <slot name="suffix" />

                    <!-- Chevron -->
                    <span
                        :class="[
                            'material-symbols-outlined text-[18px] shrink-0 transition-transform duration-200',
                            error ? 'text-error' : 'text-on-surface-variant',
                            isOpen ? 'rotate-180' : '',
                        ]"
                        aria-hidden="true"
                    >expand_more</span>
                </button>

                <!-- ── Dropdown via Teleport ───────────────────────────── -->
                <Teleport to="body">
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 -translate-y-1 scale-[0.98]"
                >
                    <div
                        v-if="isOpen && !modalMode"
                        ref="dropdownRef"
                        :style="dropdownStyle"
                        class="rounded-lg border border-border bg-popover text-popover-foreground shadow-lg"
                        role="listbox"
                        :aria-label="label"
                    >
                        <!-- Campo de busca -->
                        <div class="p-2 border-b border-border/40">
                            <div class="flex items-center gap-2 rounded-md border border-border bg-popover px-2.5 py-1.5 focus-within:ring-1 focus-within:ring-primary focus-within:border-primary transition-all">
                                <span
                                    v-if="!isSearching"
                                    class="material-symbols-outlined text-[16px] text-on-surface-variant shrink-0"
                                    aria-hidden="true"
                                >search</span>
                                <span
                                    v-else
                                    class="material-symbols-outlined text-[16px] text-on-surface-variant animate-spin shrink-0"
                                    aria-hidden="true"
                                >progress_activity</span>
                                <input
                                    ref="searchInputRef"
                                    type="text"
                                    :value="searchQuery"
                                    :placeholder="searchPlaceholder"
                                    class="flex-1 bg-transparent text-sm text-on-surface outline-none placeholder:text-on-surface-variant/50"
                                    autocomplete="off"
                                    @input="onSearchInput"
                                />
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="material-symbols-outlined text-[14px] text-on-surface-variant hover:text-on-surface transition-colors"
                                    aria-label="Limpar busca"
                                    @click="clearSearch"
                                >close</button>
                            </div>
                        </div>

                        <!-- Lista de opções -->
                        <div class="max-h-56 overflow-y-auto py-1" role="group">
                            <!-- Buscando -->
                            <slot v-if="isSearching" name="loading">
                                <div class="flex items-center justify-center gap-2 py-6 text-sm text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                                    Buscando...
                                </div>
                            </slot>

                            <!-- Erro de busca -->
                            <div v-else-if="searchError" class="py-4 px-4 text-sm text-error text-center flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">error</span>
                                {{ searchError }}
                            </div>

                            <!-- Vazio -->
                            <slot v-else-if="isEmpty" name="empty">
                                <div class="py-6 text-center text-sm text-on-surface-variant">{{ emptyText }}</div>
                            </slot>

                            <!-- Opções com grupos -->
                            <template v-else-if="hasGroups">
                                <template v-for="[group, opts] in groupedOptions" :key="group">
                                    <div
                                        v-if="group"
                                        class="px-3 pt-2 pb-1 text-xs font-mono uppercase tracking-wider text-on-surface-variant/50"
                                    >{{ group }}</div>
                                    <button
                                        v-for="opt in opts"
                                        :key="opt.value"
                                        type="button"
                                        :disabled="opt.disabled"
                                        :aria-selected="modelValue === opt.value"
                                        role="option"
                                        :class="[
                                            'w-full flex items-start gap-2.5 px-3 py-2 text-sm transition-colors text-left',
                                            opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-primary/5',
                                            modelValue === opt.value
                                                ? 'text-primary bg-primary/5'
                                                : 'text-on-surface',
                                        ]"
                                        @click="selectOption(opt)"
                                    >
                                        <slot name="option" :option="opt" :selected="modelValue === opt.value">
                                            <div class="flex-1 min-w-0">
                                                <div class="truncate">{{ opt.label }}</div>
                                                <div v-if="opt.description" class="text-xs text-on-surface-variant truncate mt-0.5">{{ opt.description }}</div>
                                            </div>
                                            <span
                                                v-if="modelValue === opt.value"
                                                class="material-symbols-outlined text-[16px] text-primary shrink-0 mt-0.5"
                                            >check</span>
                                        </slot>
                                    </button>
                                </template>
                            </template>

                            <!-- Opções planas -->
                            <template v-else>
                                <button
                                    v-for="opt in filteredOptions"
                                    :key="opt.value"
                                    type="button"
                                    :disabled="opt.disabled"
                                    :aria-selected="modelValue === opt.value"
                                    role="option"
                                    :class="[
                                        'w-full flex items-start gap-2.5 px-3 py-2 text-sm transition-colors text-left',
                                        opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-primary/5',
                                        modelValue === opt.value
                                            ? 'text-primary bg-primary/5'
                                            : 'text-on-surface',
                                    ]"
                                    @click="selectOption(opt)"
                                >
                                    <slot name="option" :option="opt" :selected="modelValue === opt.value">
                                        <div class="flex-1 min-w-0">
                                            <div class="truncate">{{ opt.label }}</div>
                                            <div v-if="opt.description" class="text-xs text-on-surface-variant truncate mt-0.5">{{ opt.description }}</div>
                                        </div>
                                        <span
                                            v-if="modelValue === opt.value"
                                            class="material-symbols-outlined text-[16px] text-primary shrink-0 mt-0.5"
                                        >check</span>
                                    </slot>
                                </button>
                            </template>
                        </div>

                        <!-- Slot de ações no rodapé do dropdown -->
                        <div v-if="$slots.actions" class="border-t border-border/40 p-2">
                            <slot name="actions" :close="close" />
                        </div>
                    </div>
                </Transition>
                </Teleport>
            </div>

            <!-- Botão de ação lateral -->
            <button
                v-if="actionLabel || $slots.action"
                type="button"
                class="shrink-0 h-10.5 px-3 rounded-lg border border-border bg-transparent text-sm text-muted-foreground hover:text-on-surface hover:border-on-surface-variant/40 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="disabled"
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
            class="text-xs text-error flex items-center gap-1"
        >
            <slot name="error-icon">
                <span class="material-symbols-outlined text-[14px] leading-none shrink-0" aria-hidden="true">error</span>
            </slot>
            {{ error }}
        </p>

        <!-- Hint (oculto quando há erro) -->
        <div v-else-if="$slots.hint || hint" :id="hintId" class="text-xs text-on-surface-variant">
            <slot name="hint">{{ hint }}</slot>
        </div>

        <!-- Help text permanente -->
        <p v-if="helpText" class="text-xs text-on-surface-variant/60 flex items-start gap-1">
            <span class="material-symbols-outlined text-[14px] leading-none mt-px shrink-0" aria-hidden="true">info</span>
            {{ helpText }}
        </p>
    </div>

    <!-- ── Modal mode ────────────────────────────────────────────────────────── -->
    <Dialog v-if="modalMode" :open="isOpen" @update:open="(v) => { if (!v) close(); }">
        <DialogContent class="max-w-lg p-0 gap-0 overflow-hidden flex flex-col">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/50 shrink-0">
                <DialogTitle class="text-sm font-mono uppercase tracking-wider text-on-surface-variant font-medium">
                    {{ modalTitle }}
                </DialogTitle>
            </DialogHeader>

            <!-- Busca no modal -->
            <div class="px-4 py-3 border-b border-border/40 shrink-0">
                <div class="flex items-center gap-2 rounded-md border border-border bg-popover px-2.5 py-1.5 focus-within:ring-1 focus-within:ring-primary focus-within:border-primary transition-all">
                    <span
                        v-if="!isSearching"
                        class="material-symbols-outlined text-[16px] text-on-surface-variant shrink-0"
                        aria-hidden="true"
                    >search</span>
                    <span
                        v-else
                        class="material-symbols-outlined text-[16px] text-on-surface-variant animate-spin shrink-0"
                        aria-hidden="true"
                    >progress_activity</span>
                    <input
                        ref="searchInputRef"
                        type="text"
                        :value="searchQuery"
                        :placeholder="searchPlaceholder"
                        class="flex-1 bg-transparent text-sm text-on-surface outline-none placeholder:text-on-surface-variant/50"
                        autocomplete="off"
                        @input="onSearchInput"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="material-symbols-outlined text-[14px] text-on-surface-variant hover:text-on-surface transition-colors"
                        aria-label="Limpar busca"
                        @click="clearSearch"
                    >close</button>
                </div>
            </div>

            <!-- Lista do modal -->
            <div class="flex-1 overflow-y-auto max-h-[55vh] py-1" role="listbox" :aria-label="label">
                <slot v-if="isSearching" name="loading">
                    <div class="flex items-center justify-center gap-2 py-10 text-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-[20px] animate-spin">progress_activity</span>
                        Buscando...
                    </div>
                </slot>
                <div v-else-if="searchError" class="py-6 px-4 text-sm text-error text-center flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">error</span>
                    {{ searchError }}
                </div>
                <slot v-else-if="isEmpty" name="empty">
                    <div class="py-10 text-center text-sm text-on-surface-variant">{{ emptyText }}</div>
                </slot>
                <template v-else>
                    <template v-if="hasGroups">
                        <template v-for="[group, opts] in groupedOptions" :key="group">
                            <div
                                v-if="group"
                                class="px-4 pt-2 pb-1 text-xs font-mono uppercase tracking-wider text-on-surface-variant/50"
                            >{{ group }}</div>
                            <button
                                v-for="opt in opts"
                                :key="opt.value"
                                type="button"
                                :disabled="opt.disabled"
                                :aria-selected="modelValue === opt.value"
                                role="option"
                                :class="[
                                    'w-full flex items-start gap-3 px-4 py-3 text-sm transition-colors text-left',
                                    opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-primary/5',
                                    modelValue === opt.value
                                        ? 'text-primary bg-primary/5'
                                        : 'text-on-surface',
                                ]"
                                @click="selectOption(opt)"
                            >
                                <slot name="option" :option="opt" :selected="modelValue === opt.value">
                                    <div class="flex-1 min-w-0">
                                        <div class="truncate">{{ opt.label }}</div>
                                        <div v-if="opt.description" class="text-xs text-on-surface-variant mt-0.5 truncate">{{ opt.description }}</div>
                                    </div>
                                    <span
                                        v-if="modelValue === opt.value"
                                        class="material-symbols-outlined text-[18px] text-primary shrink-0 mt-0.5"
                                    >check</span>
                                </slot>
                            </button>
                        </template>
                    </template>
                    <template v-else>
                        <button
                            v-for="opt in filteredOptions"
                            :key="opt.value"
                            type="button"
                            :disabled="opt.disabled"
                            :aria-selected="modelValue === opt.value"
                            role="option"
                            :class="[
                                'w-full flex items-start gap-3 px-4 py-3 text-sm transition-colors text-left',
                                opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-primary/5',
                                modelValue === opt.value
                                    ? 'text-primary bg-primary/5'
                                    : 'text-on-surface',
                            ]"
                            @click="selectOption(opt)"
                        >
                            <slot name="option" :option="opt" :selected="modelValue === opt.value">
                                <div class="flex-1 min-w-0">
                                    <div class="truncate">{{ opt.label }}</div>
                                    <div v-if="opt.description" class="text-xs text-on-surface-variant mt-0.5 truncate">{{ opt.description }}</div>
                                </div>
                                <span
                                    v-if="modelValue === opt.value"
                                    class="material-symbols-outlined text-[18px] text-primary shrink-0 mt-0.5"
                                >check</span>
                            </slot>
                        </button>
                    </template>
                </template>
            </div>

            <!-- Rodapé do modal -->
            <div class="shrink-0 border-t border-border/50 px-4 py-3 flex items-center justify-between gap-3">
                <div>
                    <button
                        v-if="clearable && modelValue !== null && modelValue !== undefined"
                        type="button"
                        class="text-xs text-error hover:text-error/70 font-mono transition-colors"
                        @click="clearSelection(); close()"
                    >
                        Limpar seleção
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <slot name="actions" :close="close" />
                    <Button variant="ghost" size="sm" @click="close">Fechar</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
