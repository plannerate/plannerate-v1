<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type Option = { id: string; name: string };

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        error?: string;
        inputName?: string;
        disabled?: boolean;
        cascadeLevels?: number;
        levelLabels?: string[];
        cols?: number;
    }>(),
    {
        error: '',
        inputName: 'category_id',
        disabled: false,
        cascadeLevels: 7,
        cols: 3,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const { t } = useT();

const childrenHttp = useHttp<Record<string, string>, Option[]>();
const pathHttp = useHttp<Record<string, string>, { path?: Option[] }>();

const selections = ref<string[]>(Array.from({ length: props.cascadeLevels }, () => ''));
const options = ref<Option[][]>(Array.from({ length: props.cascadeLevels }, () => []));
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

    return Array.isArray(pathHttp.response?.path) ? pathHttp.response!.path! : [];
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

function isLevelDisabled(level: number): boolean {
    if (props.disabled) {
        return true;
    }
    if (level === 0) {
        return false;
    }

    return selections.value[level - 1] === '';
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
            options.value[level] = await loadChildren(selections.value[level - 1]);
        } catch {
            loadError.value = true;
        }
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
</script>

<template>
    <div class="space-y-3">
        <input type="hidden" :name="inputName" :value="leafCategoryId ?? ''" />

        <p v-if="loadError" class="text-xs text-destructive">{{ t('app.tenant.categories.cascade.load_error') }}</p>

        <div class="grid gap-3" :style="{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }">
            <div v-for="level in cascadeLevels" :key="`cascade-${level - 1}`" class="flex flex-col gap-y-1">
                <Label :for="`category_cascade_${level - 1}`" class="text-xs font-medium">{{ levelLabel(level - 1) }}</Label>
                <div class="relative flex items-stretch gap-1">
                    <select
                        :id="`category_cascade_${level - 1}`"
                        :class="selectClass"
                        :disabled="isLevelDisabled(level - 1)"
                        :value="selections[level - 1]"
                        @change="onLevelChange(level - 1, ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">{{ t('app.tenant.categories.cascade.placeholder') }}</option>
                        <option v-for="opt in options[level - 1]" :key="opt.id" :value="opt.id">
                            {{ opt.name }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="absolute right-0 top-1/2 size-9 shrink-0 -translate-y-1/2"
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
    </div>
</template>
