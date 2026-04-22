<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

const CASCADE_LEVELS = 7;

type Option = { id: string; name: string };

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        error?: string;
        inputName?: string;
        disabled?: boolean;
    }>(),
    {
        error: '',
        inputName: 'category_id',
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const { t } = useT();

const selections = ref<string[]>(Array.from({ length: CASCADE_LEVELS }, () => ''));
const options = ref<Option[][]>(Array.from({ length: CASCADE_LEVELS }, () => []));
const loadError = ref(false);

const selectClass =
    'flex h-9 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 py-2 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50';

function levelLabel(index: number): string {
    const key = `app.tenant.categories.cascade.level_${index + 1}` as const;

    return t(key);
}

function xsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

async function jsonFetch(url: string): Promise<unknown> {
    const res = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });

    if (! res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    return res.json();
}

async function loadChildren(parentId: string | null): Promise<Option[]> {
    const u = new URL('/categories/cascade/children', window.location.origin);
    if (parentId) {
        u.searchParams.set('parent_id', parentId);
    }

    const data = (await jsonFetch(u.toString())) as Option[];

    return Array.isArray(data) ? data : [];
}

async function loadPath(categoryId: string): Promise<Option[]> {
    const u = new URL('/categories/cascade/path', window.location.origin);
    u.searchParams.set('id', categoryId);
    const data = (await jsonFetch(u.toString())) as { path?: Option[] };

    return Array.isArray(data.path) ? data.path : [];
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
    selections.value = Array.from({ length: CASCADE_LEVELS }, () => '');
    options.value = Array.from({ length: CASCADE_LEVELS }, () => []);

    options.value[0] = await loadChildren(null);

    if (! id) {
        emitLeaf();

        return;
    }

    try {
        const path = await loadPath(id);
        for (let i = 0; i < path.length && i < CASCADE_LEVELS; i++) {
            selections.value[i] = path[i].id;
        }

        for (let i = 0; i < path.length && i + 1 < CASCADE_LEVELS; i++) {
            options.value[i + 1] = await loadChildren(path[i].id);
        }
    } catch {
        loadError.value = true;
    }

    emitLeaf();
}

function emitLeaf(): void {
    emit('update:modelValue', leafCategoryId.value);
}

async function onLevelChange(level: number, value: string): Promise<void> {
    selections.value[level] = value;
    for (let j = level + 1; j < CASCADE_LEVELS; j++) {
        selections.value[j] = '';
        options.value[j] = [];
    }

    if (value !== '' && level + 1 < CASCADE_LEVELS) {
        try {
            options.value[level + 1] = await loadChildren(value);
            loadError.value = false;
        } catch {
            loadError.value = true;
        }
    }

    emitLeaf();
}

async function clearFrom(level: number): Promise<void> {
    for (let j = level; j < CASCADE_LEVELS; j++) {
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

    emitLeaf();
}

onMounted(async () => {
    try {
        loadError.value = false;
        await hydrateFromModel();
    } catch {
        loadError.value = true;
    }
});

watch(
    () => props.modelValue,
    async (next, prev) => {
        if (next === prev) {
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

        <!-- Linha 1: níveis 1–3 -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div v-for="level in [0, 1, 2]" :key="`cascade-${level}`" class="flex flex-col gap-y-1">
                <Label :for="`category_cascade_${level}`" class="text-xs font-medium">{{ levelLabel(level) }}</Label>
                <div class="flex items-stretch gap-1">
                    <select
                        :id="`category_cascade_${level}`"
                        :class="selectClass"
                        :disabled="isLevelDisabled(level)"
                        :value="selections[level]"
                        @change="onLevelChange(level, ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">{{ t('app.tenant.categories.cascade.placeholder') }}</option>
                        <option v-for="opt in options[level]" :key="opt.id" :value="opt.id">
                            {{ opt.name }}
                        </option>
                    </select>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-9 shrink-0"
                        :disabled="disabled || selections[level] === ''"
                        :aria-label="t('app.tenant.categories.cascade.clear')"
                        @click="clearFrom(level)"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Linha 2: níveis 4–5 (4 mais largo) -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div v-for="(level, idx) in [3, 4]" :key="`cascade-${level}`" class="flex flex-col gap-y-1" :class="idx === 0 ? 'md:col-span-7' : 'md:col-span-5'">
                <Label :for="`category_cascade_${level}`" class="text-xs font-medium">{{ levelLabel(level) }}</Label>
                <div class="flex items-stretch gap-1">
                    <select
                        :id="`category_cascade_${level}`"
                        :class="selectClass"
                        :disabled="isLevelDisabled(level)"
                        :value="selections[level]"
                        @change="onLevelChange(level, ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">{{ t('app.tenant.categories.cascade.placeholder') }}</option>
                        <option v-for="opt in options[level]" :key="opt.id" :value="opt.id">
                            {{ opt.name }}
                        </option>
                    </select>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-9 shrink-0"
                        :disabled="disabled || selections[level] === ''"
                        :aria-label="t('app.tenant.categories.cascade.clear')"
                        @click="clearFrom(level)"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Linha 3: níveis 6–7 -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div v-for="(level, idx) in [5, 6]" :key="`cascade-${level}`" class="flex flex-col gap-y-1" :class="idx === 0 ? 'md:col-span-7' : 'md:col-span-5'">
                <Label :for="`category_cascade_${level}`" class="text-xs font-medium">{{ levelLabel(level) }}</Label>
                <div class="flex items-stretch gap-1">
                    <select
                        :id="`category_cascade_${level}`"
                        :class="selectClass"
                        :disabled="isLevelDisabled(level)"
                        :value="selections[level]"
                        @change="onLevelChange(level, ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">{{ t('app.tenant.categories.cascade.placeholder') }}</option>
                        <option v-for="opt in options[level]" :key="opt.id" :value="opt.id">
                            {{ opt.name }}
                        </option>
                    </select>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-9 shrink-0"
                        :disabled="disabled || selections[level] === ''"
                        :aria-label="t('app.tenant.categories.cascade.clear')"
                        @click="clearFrom(level)"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <InputError :message="error" />
    </div>
</template>
