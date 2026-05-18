<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type Option = {
    id: string;
    name: string;
    level_name: string | null;
    nivel: number | null;
};

const DEFAULT_SORTIMENT_ATTRIBUTE_LEVELS = [
    'departamento',
    'categoria',
    'subcategoria',
];
const HIERARCHY_LEVEL_LABELS = [
    'Segmento varejista',
    'Departamento',
    'Subdepartamento',
    'Categoria',
    'Subcategoria',
    'Segmento',
    'Subsegmento',
    'Atributo',
];

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        error?: string;
        inputName?: string;
        disabled?: boolean;
        cascadeLevels?: number;
        levelLabels?: string[];
        cols?: number;
        enableSortimentAttributeHelper?: boolean;
        sortimentAttributeLevelsValue?: string | null;
        sortimentAttributeLevelsInputName?: string;
    }>(),
    {
        error: '',
        inputName: 'category_id',
        disabled: false,
        cascadeLevels: 7,
        cols: 3,
        enableSortimentAttributeHelper: false,
        sortimentAttributeLevelsValue: '',
        sortimentAttributeLevelsInputName: 'sortiment_attribute_levels',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
    'update-sortiment-attribute': [value: string];
}>();

const { t } = useT();

const childrenHttp = useHttp<Record<string, string>, Option[]>();
const pathHttp = useHttp<Record<string, string>, { path?: Option[] }>();

const selections = ref<string[]>(
    Array.from({ length: props.cascadeLevels }, () => ''),
);
const options = ref<Option[][]>(
    Array.from({ length: props.cascadeLevels }, () => []),
);
const loadError = ref(false);

const selectClass =
    'flex h-9 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 py-2 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50';

function levelLabel(index: number): string {
    if (props.levelLabels && props.levelLabels[index]) {
        return props.levelLabels[index];
    }

    return t(`app.tenant.categories.cascade.level_${index + 1}`);
}

async function loadChildren(parentId: string | null): Promise<Option[]> {
    const url = new URL('/categories/cascade/children', window.location.origin);

    if (parentId) {
        url.searchParams.set('parent_id', parentId);
    }

    await childrenHttp.get(url.toString());

    return Array.isArray(childrenHttp.response) ? childrenHttp.response : [];
}

async function loadPath(categoryId: string): Promise<Option[]> {
    const url = new URL('/categories/cascade/path', window.location.origin);
    url.searchParams.set('id', categoryId);

    await pathHttp.get(url.toString());

    return Array.isArray(pathHttp.response?.path)
        ? pathHttp.response!.path!
        : [];
}

const leafCategoryId = computed((): string | null => {
    for (let i = selections.value.length - 1; i >= 0; i--) {
        const v = selections.value[i];

        if (v !== '') {
            return v;
        }
    }

    return null;
});

const selectedCategoryNodes = computed((): Option[] => {
    const nodes: Option[] = [];

    for (let i = 0; i < props.cascadeLevels; i++) {
        const selectedId = selections.value[i];

        if (selectedId === '') {
            break;
        }

        const selectedOption = options.value[i]?.find(
            (opt) => opt.id === selectedId,
        );

        if (!selectedOption) {
            break;
        }

        nodes.push(selectedOption);
    }

    return nodes;
});

const selectedCategoryNames = computed((): string[] => {
    return selectedCategoryNodes.value.map((node) => node.name);
});

const selectedCategoryPath = computed((): string =>
    selectedCategoryNames.value.join(' > '),
);

const sortimentAttributeLevelKeys = ref<string[]>(
    parseSortimentAttributeLevels(props.sortimentAttributeLevelsValue),
);

const sortimentAttributeLevelsInputValue = computed((): string => {
    return sortimentAttributeLevelKeys.value.join(',');
});

const selectedSortimentAttribute = computed((): string => {
    return selectedCategoryNodes.value
        .filter((node, index) =>
            sortimentAttributeLevelKeys.value.includes(levelKey(node, index)),
        )
        .map((node) => node.name)
        .join(' | ');
});

function parseSortimentAttributeLevels(
    value: string | null | undefined,
): string[] {
    const levels = String(value ?? '')
        .split(',')
        .map((level) => normalizeLevelKey(level))
        .filter((level) => level !== '');

    return levels.length > 0
        ? [...new Set(levels)]
        : DEFAULT_SORTIMENT_ATTRIBUTE_LEVELS;
}

function isLevelDisabled(level: number): boolean {
    if (props.disabled) {
        return true;
    }

    if (level === 0) {
        return false;
    }

    return selections.value[level - 1] === '';
}

function normalizeLevelKey(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

function displayLevelName(node: Option, index: number): string {
    return (
        node.level_name || HIERARCHY_LEVEL_LABELS[index] || levelLabel(index)
    );
}

function levelKey(node: Option, index: number): string {
    return normalizeLevelKey(displayLevelName(node, index));
}

function isSortimentLevelSelected(node: Option, index: number): boolean {
    return sortimentAttributeLevelKeys.value.includes(levelKey(node, index));
}

function toggleSortimentLevel(node: Option, index: number): void {
    const key = levelKey(node, index);

    if (key === '') {
        return;
    }

    if (sortimentAttributeLevelKeys.value.includes(key)) {
        sortimentAttributeLevelKeys.value =
            sortimentAttributeLevelKeys.value.filter((level) => level !== key);
    } else {
        sortimentAttributeLevelKeys.value = [
            ...sortimentAttributeLevelKeys.value,
            key,
        ];
    }

    emit('update-sortiment-attribute', selectedSortimentAttribute.value);
}

async function hydrateFromModel(): Promise<void> {
    const id = props.modelValue;
    selections.value = Array.from({ length: props.cascadeLevels }, () => '');
    options.value = Array.from({ length: props.cascadeLevels }, () => []);

    options.value[0] = await loadChildren(null);

    if (!id) {
        return;
    }

    try {
        const path = await loadPath(id);

        for (let i = 0; i < path.length && i < props.cascadeLevels; i++) {
            selections.value[i] = path[i].id;
        }

        for (let i = 0; i < path.length && i + 1 < props.cascadeLevels; i++) {
            options.value[i + 1] = await loadChildren(path[i].id);
        }
    } catch {
        loadError.value = true;
    }
}

async function onLevelChange(level: number, value: string): Promise<void> {
    selections.value[level] = value;

    for (let j = level + 1; j < props.cascadeLevels; j++) {
        selections.value[j] = '';
        options.value[j] = [];
    }

    if (value !== '' && level + 1 < props.cascadeLevels) {
        try {
            options.value[level + 1] = await loadChildren(value);
            loadError.value = false;
        } catch {
            loadError.value = true;
        }
    }

    if (props.enableSortimentAttributeHelper) {
        emit('update-sortiment-attribute', selectedSortimentAttribute.value);
    }
}

async function clearFrom(level: number): Promise<void> {
    for (let j = level; j < props.cascadeLevels; j++) {
        selections.value[j] = '';

        if (j > level) {
            options.value[j] = [];
        }
    }

    if (level === 0) {
        try {
            options.value[0] = await loadChildren(null);
        } catch {
            loadError.value = true;
        }
    } else if (selections.value[level - 1] !== '') {
        try {
            options.value[level] = await loadChildren(
                selections.value[level - 1],
            );
        } catch {
            loadError.value = true;
        }
    }

    if (props.enableSortimentAttributeHelper) {
        emit('update-sortiment-attribute', selectedSortimentAttribute.value);
    }
}

onMounted(async () => {
    try {
        loadError.value = false;
        await hydrateFromModel();
    } catch {
        loadError.value = true;
    }
});

// Emite sempre que o leaf muda internamente (seleção ou limpeza)
watch(leafCategoryId, (value) => {
    emit('update:modelValue', value);
});

// Re-hidrata apenas quando o pai altera o valor externamente
watch(
    () => props.modelValue,
    async (next) => {
        if (next === leafCategoryId.value) {
            return;
        }

        try {
            await hydrateFromModel();
        } catch {
            loadError.value = true;
        }
    },
);

watch(
    () => props.sortimentAttributeLevelsValue,
    (value) => {
        sortimentAttributeLevelKeys.value =
            parseSortimentAttributeLevels(value);
    },
);
</script>

<template>
    <div class="space-y-3">
        <input type="hidden" :name="inputName" :value="leafCategoryId ?? ''" />
        <input
            v-if="enableSortimentAttributeHelper"
            type="hidden"
            :name="sortimentAttributeLevelsInputName"
            :value="sortimentAttributeLevelsInputValue"
        />

        <p v-if="loadError" class="text-xs text-destructive">
            {{ t('app.tenant.categories.cascade.load_error') }}
        </p>

        <div
            class="grid gap-3"
            :style="{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }"
        >
            <div
                v-for="level in cascadeLevels"
                :key="`cascade-${level - 1}`"
                class="flex flex-col gap-y-1"
            >
                <Label
                    :for="`category_cascade_${level - 1}`"
                    class="text-xs font-medium"
                    >{{ levelLabel(level - 1) }}</Label
                >
                <div class="relative flex items-stretch gap-1">
                    <select
                        :id="`category_cascade_${level - 1}`"
                        :class="selectClass"
                        :disabled="isLevelDisabled(level - 1)"
                        :value="selections[level - 1]"
                        @change="
                            onLevelChange(
                                level - 1,
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">
                            {{ t('app.tenant.categories.cascade.placeholder') }}
                        </option>
                        <option
                            v-for="opt in options[level - 1]"
                            :key="opt.id"
                            :value="opt.id"
                        >
                            {{ opt.name }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="absolute top-1/2 right-0 size-9 shrink-0 -translate-y-1/2"
                        :disabled="disabled || selections[level - 1] === ''"
                        :aria-label="t('app.tenant.categories.cascade.clear')"
                        @click="clearFrom(level - 1)"
                    >
                        <X class="size-3 opacity-50" />
                    </button>
                </div>
            </div>
        </div>

        <InputError :message="error" />

        <div
            v-if="enableSortimentAttributeHelper && selectedCategoryPath"
            class="flex flex-wrap gap-1.5"
        >
            <button
                v-for="(node, index) in selectedCategoryNodes"
                :key="node.id"
                type="button"
                class="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-1 text-left text-xs transition disabled:cursor-not-allowed disabled:opacity-50"
                :class="
                    isSortimentLevelSelected(node, index)
                        ? 'border-primary/40 bg-primary/10 text-primary hover:border-destructive/40 hover:bg-destructive/10 hover:text-destructive'
                        : 'border-border bg-background text-muted-foreground hover:bg-muted/70 hover:text-foreground'
                "
                :disabled="disabled"
                :aria-pressed="isSortimentLevelSelected(node, index)"
                :aria-label="`${isSortimentLevelSelected(node, index) ? 'Remover' : 'Adicionar'} ${displayLevelName(node, index)} do sortimento`"
                @click="toggleSortimentLevel(node, index)"
            >
                <X
                    v-if="isSortimentLevelSelected(node, index)"
                    class="size-3 shrink-0"
                />
                <span class="truncate"
                    >{{ displayLevelName(node, index) }}: {{ node.name }}</span
                >
            </button>
        </div>
    </div>
</template>
