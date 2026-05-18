<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        modelValue: string;
        searchUrl?: string | null;
        queryParam?: string;
        placeholder?: string;
        hint?: string;
        required?: boolean;
        disabled?: boolean;
        debounceMs?: number;
        loadingText?: string;
        emptyText?: string;
    }>(),
    {
        searchUrl: null,
        queryParam: 'search',
        placeholder: '',
        hint: '',
        required: false,
        disabled: false,
        debounceMs: 250,
        loadingText: 'Buscando...',
        emptyText: 'Nenhum resultado encontrado.',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    select: [value: string];
}>();

const options = ref<string[]>([]);
const loading = ref(false);
const open = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let abortController: AbortController | null = null;

const hasSearch = computed(
    () => typeof props.searchUrl === 'string' && props.searchUrl.trim() !== '',
);

const optionsId = computed(() => `${props.id}-options`);

function clearSearch(): void {
    if (searchTimeout !== null) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }

    abortController?.abort();
    abortController = null;
}

async function fetchOptions(search: string): Promise<void> {
    if (!hasSearch.value || props.searchUrl === null) {
        options.value = [];

        return;
    }

    abortController?.abort();
    const controller = new AbortController();
    abortController = controller;
    loading.value = true;

    try {
        const url = new URL(props.searchUrl, window.location.origin);

        if (search !== '') {
            url.searchParams.set(props.queryParam, search);
        }

        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            options.value = [];

            return;
        }

        const payload = (await response.json()) as { data?: unknown };
        options.value = Array.isArray(payload.data)
            ? payload.data.filter(
                  (value): value is string =>
                      typeof value === 'string' && value.trim() !== '',
              )
            : [];
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        options.value = [];
    } finally {
        if (abortController === controller && !controller.signal.aborted) {
            loading.value = false;
        }
    }
}

function scheduleSearch(search: string): void {
    if (!hasSearch.value || props.disabled) {
        return;
    }

    if (searchTimeout !== null) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        void fetchOptions(search.trim());
    }, props.debounceMs);
}

function updateValue(value: string | number): void {
    emit('update:modelValue', String(value));
}

function selectOption(option: string): void {
    emit('update:modelValue', option);
    emit('select', option);
    open.value = false;
}

function closeOptions(): void {
    window.setTimeout(() => {
        open.value = false;
    }, 120);
}

watch(
    () => props.modelValue,
    (value) => {
        scheduleSearch(value);
    },
);

watch(open, (isOpen) => {
    if (isOpen) {
        scheduleSearch(props.modelValue);
    }
});

onBeforeUnmount(() => {
    clearSearch();
});
</script>

<template>
    <div class="grid gap-1.5">
        <Label :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <div class="relative">
            <Input
                :id="id"
                :model-value="modelValue"
                autocomplete="off"
                role="combobox"
                :aria-expanded="open"
                :aria-controls="hasSearch ? optionsId : undefined"
                :disabled="disabled"
                :placeholder="placeholder"
                @update:model-value="updateValue"
                @focus="
                    open = true;
                    scheduleSearch(modelValue);
                "
                @blur="closeOptions"
            />

            <div
                v-if="hasSearch && open"
                :id="optionsId"
                class="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                role="listbox"
            >
                <div
                    v-if="loading"
                    class="px-3 py-2 text-sm text-muted-foreground"
                >
                    {{ loadingText }}
                </div>

                <button
                    v-for="option in options"
                    :key="option"
                    type="button"
                    class="flex w-full items-center rounded-sm px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                    role="option"
                    :aria-selected="option === modelValue"
                    @mousedown.prevent="selectOption(option)"
                >
                    {{ option }}
                </button>

                <div
                    v-if="!loading && options.length === 0"
                    class="px-3 py-2 text-sm text-muted-foreground"
                >
                    {{ emptyText }}
                </div>
            </div>
        </div>

        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
