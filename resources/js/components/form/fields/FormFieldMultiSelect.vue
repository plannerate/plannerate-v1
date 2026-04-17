<!--
 * FormFieldMultiSelect - Multi-select field component
 *
 * Features:
 * - Multiple selections with v-model binding
 * - Static options or dynamic API loading
 * - Search functionality with debounce
 * - Autocomplete fields support
 * - Popover UI for better UX
 -->
<template>
    <Field orientation="vertical" :data-invalid="hasError" class="gap-y-1">
        <div class="flex w-full items-center justify-between">
            <FieldLabel v-if="column.label" :for="column.name">
                {{ column.label }}
                <span v-if="column.required" class="text-destructive">*</span>
            </FieldLabel>
        </div>

        <!-- Loading state -->
        <div
            v-if="loading"
            class="flex items-center gap-2 rounded-md border border-input bg-background px-3 py-2"
        >
            <Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
            <span class="text-sm text-muted-foreground">Carregando opções...</span>
        </div>

        <!-- Multi-Select with Popover -->
        <Popover v-else :open="isOpen" @update:open="isOpen = $event">
            <PopoverTrigger as-child>
                <Button
                    variant="outline"
                    :class="[
                        'w-full justify-start text-left font-normal',
                        !selectedValues.length && 'text-muted-foreground',
                    ]"
                >
                    <span v-if="selectedValues.length === 0">
                        {{ column.placeholder || 'Selecionar...' }}
                    </span>
                    <span v-else>
                        {{ selectedValues.length }} selecionado(s)
                    </span>
                    <ChevronDown class="ml-auto h-4 w-4 opacity-50" />
                </Button>
            </PopoverTrigger>

            <PopoverContent class="w-80 p-0">
                <div class="space-y-2 p-3">
                    <!-- Search input if searchable -->
                    <div v-if="column.searchable" class="px-1">
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar..."
                            class="h-8"
                            @input="handleSearch"
                        />
                    </div>

                    <!-- Selected items as badges with remove -->
                    <div
                        v-if="selectedValues.length > 0"
                        class="flex flex-wrap gap-2 rounded-md bg-muted/50 p-2"
                    >
                        <Badge
                            v-for="value in selectedValues"
                            :key="value"
                            variant="secondary"
                            class="gap-1 whitespace-nowrap"
                        >
                            {{ getLabelForValue(value) }}
                            <button
                                type="button"
                                class="ml-1 hover:text-foreground"
                                @click="removeValue(value)"
                                @keydown.enter="removeValue(value)"
                                @keydown.space="removeValue(value)"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                    </div>

                    <!-- Options list -->
                    <div class="max-h-48 overflow-y-auto rounded-md border">
                        <div
                            v-if="availableOptions.length === 0"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            {{
                                searchQuery
                                    ? 'Nenhuma opção encontrada'
                                    : 'Nenhuma opção disponível'
                            }}
                        </div>

                        <button
                            v-for="option in availableOptions"
                            :key="getOptionValue(option)"
                            type="button"
                            class="flex w-full cursor-pointer items-center justify-start gap-2 px-2 py-1.5 text-sm transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:outline-none"
                            @click="toggleValue(option)"
                        >
                            <Checkbox
                                :model-value="isSelected(option)"
                                :disabled="false"
                                class="pointer-events-none"
                            />
                            <span class="flex-1 text-start">{{ getOptionLabel(option) }}</span>
                        </button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>

        <FieldDescription v-if="column.helperText">
            {{ column.helperText }}
        </FieldDescription>

        <FieldError :errors="errorArray" />
    </Field>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ChevronDown, Loader2, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface FormColumn {
    name: string;
    label?: string;
    helperText?: string;
    required?: boolean;
    placeholder?: string;
    searchable?: boolean;
    options?: Record<string, string> | Array<{ id: string; name: string }>;
    apiEndpoint?: string;
    table?: string;
    labelColumn?: string;
    valueColumn?: string;
    autoComplete?: {
        enabled: boolean;
        fields: Array<{
            source: string;
            target: string;
            isFixedValue: boolean;
        }>;
        optionValueKey?: string;
        optionLabelKey?: string;
        returnFullObject?: boolean;
    };
}

interface Props {
    column: FormColumn;
    modelValue?: string[];
    error?: string | string[];
    optionsData?: Record<string, any>;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: () => [],
    error: undefined,
    optionsData: () => ({}),
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string[]): void;
    (
        e: 'autoComplete',
        data: { source: string; target: string; value: any },
    ): void;
}>();

// State
const loading = ref(false);
const searchQuery = ref('');
const localOptions = ref<any[]>([]);
const isOpen = ref(false);

const selectedValues = computed({
    get: () => (Array.isArray(props.modelValue) ? props.modelValue : []),
    set: (value) => emit('update:modelValue', value),
});

const availableOptions = computed(() => {
    let options = localOptions.value;

    if (searchQuery.value && props.column.searchable) {
        const query = searchQuery.value.toLowerCase();
        options = options.filter((opt) => {
            const label = getOptionLabel(opt).toLowerCase();
            return label.includes(query);
        });
    }

    return options;
});

const hasError = computed(() => {
    return (
        props.error &&
        (Array.isArray(props.error) ? props.error.length > 0 : true)
    );
});

const errorArray = computed(() => {
    if (!props.error) return [];
    const errors = Array.isArray(props.error) ? props.error : [props.error];
    return errors.map((error) =>
        typeof error === 'string' ? { message: error } : error
    );
});

// Methods
const getOptionValue = (option: any): string => {
    if (typeof option === 'string' || typeof option === 'number') {
        return String(option);
    }

    // Try 'value' key first (new format)
    if ('value' in option) {
        return String(option.value);
    }

    // Fallback to configured key
    const valueKey =
        props.column.autoComplete?.optionValueKey ||
        props.column.valueColumn ||
        'id';
    return String(option[valueKey] ?? option.id);
};

const getOptionLabel = (option: any): string => {
    if (typeof option === 'string') return option;
    if (typeof option === 'number') return String(option);

    // Try 'label' key first (new format)
    if ('label' in option && option.label) {
        return String(option.label);
    }

    // Fallback to configured key
    const labelKey =
        props.column.autoComplete?.optionLabelKey ||
        props.column.labelColumn ||
        'name';
    return String(option[labelKey] ?? option.name ?? option.id);
};

const getLabelForValue = (value: string): string => {
    const option = localOptions.value.find(
        (opt) => getOptionValue(opt) === value,
    );
    return option ? getOptionLabel(option) : value;
};

const isSelected = (option: any): boolean => {
    const value = getOptionValue(option);
    return selectedValues.value.includes(value);
};

const toggleValue = (option: any) => {
    const value = getOptionValue(option);

    if (isSelected(option)) {
        selectedValues.value = selectedValues.value.filter((v) => v !== value);
    } else {
        selectedValues.value = [...selectedValues.value, value];

        // Trigger autoComplete if configured
        if (
            props.column.autoComplete?.enabled &&
            props.column.autoComplete.fields.length > 0
        ) {
            const optionData = props.optionsData[value] || option;
            props.column.autoComplete.fields.forEach((field) => {
                emit('autoComplete', {
                    source: field.source,
                    target: field.target,
                    value: optionData[field.source] ?? optionData,
                });
            });
        }
    }
};

const removeValue = (value: string) => {
    selectedValues.value = selectedValues.value.filter((v) => v !== value);
};

const handleSearch = (() => {
    let timeout: ReturnType<typeof setTimeout> | null = null;
    return () => {
        if (timeout) clearTimeout(timeout);
        if (!props.column.searchable) return;

        timeout = setTimeout(() => {
            if (props.column.apiEndpoint && searchQuery.value.length > 0) {
                loadFromApi();
            }
        }, 300);
    };
})();

const loadOptions = async () => {
    loading.value = true;

    try {
        if (props.column.options) {
            // Use provided options
            const opts = props.column.options;
            if (Array.isArray(opts)) {
                localOptions.value = opts;
            } else {
                // Convert object to array
                localOptions.value = Object.entries(opts).map(
                    ([value, label]) => ({
                        id: value,
                        name: label,
                    }),
                );
            }
        } else if (props.column.apiEndpoint) {
            await loadFromApi();
        } else if (props.column.table) {
            await loadFromTable();
        }
    } finally {
        loading.value = false;
    }
};

const loadFromApi = async () => {
    if (!props.column.apiEndpoint) return;

    try {
        const url = new URL(props.column.apiEndpoint, window.location.origin);
        if (searchQuery.value) {
            url.searchParams.append('search', searchQuery.value);
        }

        const response = await fetch(url.toString());
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        localOptions.value = Array.isArray(data)
            ? data
            : data.data || data.options || [];
    } catch (error) {
        console.error('Error loading options from API:', error);
        localOptions.value = [];
    }
};

const loadFromTable = async () => {
    if (!props.column.table) return;

    try {
        // This would typically use a route helper or similar
        // For now, we'll assume an API endpoint format
        const endpoint = `/api/${props.column.table}`;
        const response = await fetch(endpoint);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        localOptions.value = Array.isArray(data) ? data : data.data || [];
    } catch (error) {
        console.error('Error loading options from table:', error);
        localOptions.value = [];
    }
};

// Lifecycle
onMounted(() => {
    loadOptions();
});

// Watch for changes to apiEndpoint to reload
watch(
    () => props.column.apiEndpoint,
    () => {
        if (props.column.apiEndpoint) {
            loadOptions();
        }
    },
);
</script>
