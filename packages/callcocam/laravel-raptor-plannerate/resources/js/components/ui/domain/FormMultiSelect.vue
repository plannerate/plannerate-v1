<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export interface MultiSelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
    group?: string;
}

interface Props {
    /** Label do campo */
    label: string;
    /** Valores selecionados (v-model) */
    modelValue?: (string | number)[];
    /** Opções locais — usadas quando `searchUrl` não é definido */
    options?: MultiSelectOption[];
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
    /** Texto de ajuda permanente abaixo do hint/erro */
    helpText?: string;
    required?: boolean;
    disabled?: boolean;
    loading?: boolean;
    /** Abre a seleção em um Dialog em vez de dropdown inline */
    modalMode?: boolean;
    /** Título do Dialog (default: usa o label) */
    modalTitle?: string;
    /** Exibe botão de limpar tudo */
    clearable?: boolean;
    /** Máximo de chips exibidos antes de mostrar badge de contagem */
    maxDisplayedChips?: number;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: () => [],
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
    modalMode: false,
    modalTitle: undefined,
    clearable: true,
    maxDisplayedChips: 3,
    class: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [value: (string | number)[]];
    /** Emitido a cada mudança no campo de busca */
    'search': [query: string];
    /** Emitido ao selecionar uma opção */
    'select': [option: MultiSelectOption];
    /** Emitido ao remover uma opção */
    'remove': [option: MultiSelectOption];
    /** Emitido ao limpar tudo */
    'clear': [];
    'open': [];
    'close': [];
}>();

// ─── Estado interno ────────────────────────────────────────────────────────────

const isOpen = ref(false);
const searchQuery = ref('');
const remoteOptions = ref<MultiSelectOption[]>([]);
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

const selectedValues = computed(() => props.modelValue ?? []);

const filteredOptions = computed<MultiSelectOption[]>(() => {
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
    const groups = new Map<string, MultiSelectOption[]>();
    for (const opt of filteredOptions.value) {
        const key = opt.group ?? '';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key)!.push(opt);
    }
    return groups;
});

const hasGroups = computed(() => filteredOptions.value.some((o) => o.group));

const selectedOptions = computed<MultiSelectOption[]>(() => {
    const all = [...(props.options ?? []), ...remoteOptions.value];
    return selectedValues.value.map(
        (v) => all.find((o) => o.value === v) ?? { value: v, label: String(v) },
    );
});

const displayedChips = computed(() => selectedOptions.value.slice(0, props.maxDisplayedChips));
const extraCount = computed(() => Math.max(0, selectedOptions.value.length - props.maxDisplayedChips));
const isEmpty = computed(() => !isSearching.value && filteredOptions.value.length === 0);
const modalTitle = computed(() => props.modalTitle ?? props.label);

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

function isSelected(value: string | number): boolean {
    return selectedValues.value.includes(value);
}

function toggleOption(option: MultiSelectOption) {
    if (option.disabled) return;
    const current = [...selectedValues.value];
    const idx = current.indexOf(option.value);
    if (idx >= 0) {
        current.splice(idx, 1);
        emit('remove', option);
    } else {
        current.push(option.value);
        emit('select', option);
    }
    emit('update:modelValue', current);
}

function removeChip(option: MultiSelectOption, e: Event) {
    e.stopPropagation();
    emit('update:modelValue', selectedValues.value.filter((v) => v !== option.value));
    emit('remove', option);
}

function clearAll(e?: Event) {
    e?.stopPropagation();
    emit('update:modelValue', []);
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
            ? (data as MultiSelectOption[])
            : ((data as { data: MultiSelectOption[] }).data ?? []);
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
        <label class="block text-xs font-mono uppercase tracking-wider text-on-surface-variant font-medium">
            {{ label }}
            <span v-if="required" class="text-error ml-0.5">*</span>
        </label>

        <!-- Trigger -->
        <div class="relative" ref="triggerRef">
            <button
                type="button"
                :disabled="disabled || loading"
                :aria-expanded="isOpen"
                :aria-haspopup="true"
                :aria-invalid="!!error"
                :class="[
                    'w-full flex items-center gap-1.5 min-h-[42px] rounded-lg border bg-surface px-3 py-2 text-sm text-on-surface',
                    'outline-none transition-all cursor-pointer text-left',
                    'focus:ring-1 focus:ring-primary focus:border-primary',
                    'disabled:opacity-50 disabled:cursor-not-allowed',
                    error ? 'border-error' : 'border-border',
                    isOpen && !error ? 'ring-1 ring-primary border-primary' : '',
                ]"
                @click="toggle"
            >
                <!-- Slot prefix (ícone, moeda, etc.) -->
                <slot name="prefix" />

                <!-- Chips / Placeholder -->
                <div class="flex-1 flex flex-wrap gap-1 min-w-0">
                    <template v-if="selectedOptions.length > 0">
                        <span
                            v-for="opt in displayedChips"
                            :key="opt.value"
                            class="inline-flex items-center gap-0.5 bg-primary/10 text-primary text-xs rounded-md px-1.5 py-0.5 font-medium max-w-[160px]"
                        >
                            <slot name="selected-item" :option="opt">
                                <span class="truncate">{{ opt.label }}</span>
                            </slot>
                            <button
                                v-if="!disabled"
                                type="button"
                                class="material-symbols-outlined text-[13px] leading-none hover:text-error transition-colors ml-0.5 flex-shrink-0"
                                :aria-label="`Remover ${opt.label}`"
                                @click="removeChip(opt, $event)"
                            >close</button>
                        </span>
                        <span
                            v-if="extraCount > 0"
                            class="inline-flex items-center bg-on-surface-variant/10 text-on-surface-variant text-xs rounded-md px-1.5 py-0.5 font-medium"
                        >
                            +{{ extraCount }}
                        </span>
                    </template>
                    <span v-else class="text-on-surface-variant/50">{{ placeholder }}</span>
                </div>

                <!-- Spinner de loading -->
                <span
                    v-if="loading"
                    class="material-symbols-outlined text-[18px] text-on-surface-variant animate-spin flex-shrink-0"
                    aria-hidden="true"
                >progress_activity</span>

                <!-- Botão de limpar -->
                <button
                    v-else-if="clearable && selectedOptions.length > 0 && !disabled"
                    type="button"
                    class="material-symbols-outlined text-[18px] text-on-surface-variant hover:text-error transition-colors flex-shrink-0"
                    aria-label="Limpar seleção"
                    @click="clearAll"
                >close</button>

                <!-- Slot suffix (ícone, unidade, etc.) -->
                <slot name="suffix" />

                <!-- Chevron -->
                <span
                    :class="[
                        'material-symbols-outlined text-[18px] text-on-surface-variant flex-shrink-0 transition-transform duration-200',
                        isOpen ? 'rotate-180' : '',
                    ]"
                    aria-hidden="true"
                >expand_more</span>
            </button>

            <!-- ── Dropdown via Teleport ───────────────────────────────────── -->
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
                    aria-multiselectable="true"
                >
                    <!-- Campo de busca -->
                    <div class="p-2 border-b border-border/40">
                        <div class="flex items-center gap-2 rounded-md border border-border bg-popover px-2.5 py-1.5 focus-within:ring-1 focus-within:ring-primary focus-within:border-primary transition-all">
                            <span
                                v-if="!isSearching"
                                class="material-symbols-outlined text-[16px] text-on-surface-variant flex-shrink-0"
                                aria-hidden="true"
                            >search</span>
                            <span
                                v-else
                                class="material-symbols-outlined text-[16px] text-on-surface-variant animate-spin flex-shrink-0"
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
                    <div class="max-h-56 overflow-y-auto py-1">
                        <!-- Loading da busca remota -->
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
                                    :aria-selected="isSelected(opt.value)"
                                    role="option"
                                    :class="[
                                        'w-full flex items-center gap-2.5 px-3 py-2 text-sm transition-colors text-left',
                                        'hover:bg-primary/5',
                                        opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer',
                                        isSelected(opt.value) ? 'text-primary' : 'text-on-surface',
                                    ]"
                                    @click="toggleOption(opt)"
                                >
                                    <slot name="option" :option="opt" :selected="isSelected(opt.value)">
                                        <span
                                            :class="[
                                                'flex-shrink-0 w-4 h-4 rounded border flex items-center justify-center transition-colors',
                                                isSelected(opt.value)
                                                    ? 'bg-primary border-primary'
                                                    : 'border-border bg-transparent',
                                            ]"
                                        >
                                            <span
                                                v-if="isSelected(opt.value)"
                                                class="material-symbols-outlined text-[11px] text-on-primary leading-none"
                                            >check</span>
                                        </span>
                                        <span class="truncate">{{ opt.label }}</span>
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
                                :aria-selected="isSelected(opt.value)"
                                role="option"
                                :class="[
                                    'w-full flex items-center gap-2.5 px-3 py-2 text-sm transition-colors text-left',
                                    'hover:bg-primary/5',
                                    opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer',
                                    isSelected(opt.value) ? 'text-primary' : 'text-on-surface',
                                ]"
                                @click="toggleOption(opt)"
                            >
                                <slot name="option" :option="opt" :selected="isSelected(opt.value)">
                                    <span
                                        :class="[
                                            'flex-shrink-0 w-4 h-4 rounded border flex items-center justify-center transition-colors',
                                            isSelected(opt.value)
                                                ? 'bg-primary border-primary'
                                                : 'border-border bg-transparent',
                                        ]"
                                    >
                                        <span
                                            v-if="isSelected(opt.value)"
                                            class="material-symbols-outlined text-[11px] text-on-primary leading-none"
                                        >check</span>
                                    </span>
                                    <span class="truncate">{{ opt.label }}</span>
                                </slot>
                            </button>
                        </template>
                    </div>

                    <!-- Slot de ações customizadas -->
                    <div v-if="$slots.actions" class="border-t border-border/40 p-2">
                        <slot name="actions" :close="close" :selected-values="selectedValues" />
                    </div>

                    <!-- Rodapé com contagem e limpar -->
                    <div
                        v-if="selectedValues.length > 0"
                        class="border-t border-border/40 px-3 py-1.5 flex items-center justify-between"
                    >
                        <span class="text-xs text-on-surface-variant font-mono">
                            {{ selectedValues.length }} selecionado{{ selectedValues.length !== 1 ? 's' : '' }}
                        </span>
                        <button
                            type="button"
                            class="text-xs text-error hover:text-error/70 font-mono transition-colors"
                            @click="clearAll()"
                        >
                            Limpar tudo
                        </button>
                    </div>
                </div>
            </Transition>
            </Teleport>
        </div>

        <!-- Mensagem de erro -->
        <p v-if="error" class="text-xs text-error flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px] leading-none" aria-hidden="true">error</span>
            {{ error }}
        </p>

        <!-- Hint (oculto quando há erro) -->
        <p v-else-if="hint" class="text-xs text-on-surface-variant">{{ hint }}</p>

        <!-- Help text permanente -->
        <p v-if="helpText" class="text-xs text-on-surface-variant/60 flex items-start gap-1">
            <span class="material-symbols-outlined text-[14px] leading-none mt-px flex-shrink-0" aria-hidden="true">info</span>
            {{ helpText }}
        </p>
    </div>

    <!-- ── Modal mode ────────────────────────────────────────────────────────── -->
    <Dialog v-if="modalMode" :open="isOpen" @update:open="(v) => { if (!v) close(); }">
        <DialogContent class="max-w-lg p-0 gap-0 overflow-hidden flex flex-col">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/50 flex-shrink-0">
                <DialogTitle class="text-sm font-mono uppercase tracking-wider text-on-surface-variant font-medium">
                    {{ modalTitle }}
                </DialogTitle>
            </DialogHeader>

            <!-- Busca no modal -->
            <div class="px-4 py-3 border-b border-border/40 flex-shrink-0">
                <div class="flex items-center gap-2 rounded-md border border-border bg-popover px-2.5 py-1.5 focus-within:ring-1 focus-within:ring-primary focus-within:border-primary transition-all">
                    <span
                        v-if="!isSearching"
                        class="material-symbols-outlined text-[16px] text-on-surface-variant flex-shrink-0"
                        aria-hidden="true"
                    >search</span>
                    <span
                        v-else
                        class="material-symbols-outlined text-[16px] text-on-surface-variant animate-spin flex-shrink-0"
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

            <!-- Lista de opções do modal -->
            <div class="flex-1 overflow-y-auto max-h-[55vh] py-1">
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
                                :aria-selected="isSelected(opt.value)"
                                role="option"
                                :class="[
                                    'w-full flex items-center gap-3 px-4 py-3 text-sm transition-colors text-left',
                                    'hover:bg-primary/5',
                                    opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer',
                                    isSelected(opt.value) ? 'text-primary' : 'text-on-surface',
                                ]"
                                @click="toggleOption(opt)"
                            >
                                <slot name="option" :option="opt" :selected="isSelected(opt.value)">
                                    <span
                                        :class="[
                                            'flex-shrink-0 w-4 h-4 rounded border flex items-center justify-center transition-colors',
                                            isSelected(opt.value)
                                                ? 'bg-primary border-primary'
                                                : 'border-border bg-transparent',
                                        ]"
                                    >
                                        <span
                                            v-if="isSelected(opt.value)"
                                            class="material-symbols-outlined text-[11px] text-on-primary leading-none"
                                        >check</span>
                                    </span>
                                    <span class="flex-1 truncate">{{ opt.label }}</span>
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
                            :aria-selected="isSelected(opt.value)"
                            role="option"
                            :class="[
                                'w-full flex items-center gap-3 px-4 py-3 text-sm transition-colors text-left',
                                'hover:bg-primary/5',
                                opt.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer',
                                isSelected(opt.value) ? 'text-primary' : 'text-on-surface',
                            ]"
                            @click="toggleOption(opt)"
                        >
                            <slot name="option" :option="opt" :selected="isSelected(opt.value)">
                                <span
                                    :class="[
                                        'flex-shrink-0 w-4 h-4 rounded border flex items-center justify-center transition-colors',
                                        isSelected(opt.value)
                                            ? 'bg-primary border-primary'
                                            : 'border-border bg-transparent',
                                    ]"
                                >
                                    <span
                                        v-if="isSelected(opt.value)"
                                        class="material-symbols-outlined text-[11px] text-on-primary leading-none"
                                    >check</span>
                                </span>
                                <span class="flex-1 truncate">{{ opt.label }}</span>
                            </slot>
                        </button>
                    </template>
                </template>
            </div>

            <!-- Rodapé do modal -->
            <div class="flex-shrink-0 border-t border-border/50 px-4 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span v-if="selectedValues.length > 0" class="text-xs text-on-surface-variant font-mono">
                        {{ selectedValues.length }} selecionado{{ selectedValues.length !== 1 ? 's' : '' }}
                    </span>
                    <button
                        v-if="selectedValues.length > 0"
                        type="button"
                        class="text-xs text-error hover:text-error/70 font-mono transition-colors"
                        @click="clearAll()"
                    >
                        Limpar
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Slot de ações customizadas no modal -->
                    <slot name="actions" :close="close" :selected-values="selectedValues" />
                    <Button variant="ghost" size="sm" @click="close">Cancelar</Button>
                    <Button
                        size="sm"
                        class="bg-primary text-on-primary hover:brightness-95"
                        @click="close"
                    >
                        Confirmar
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>