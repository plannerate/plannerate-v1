<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useT } from '@/composables/useT';

type Option = { id: string; name: string; level_name: string | null; nivel: number | null };

/**
 * Cache module-level: chave = parentId, valor = filhos.
 * Persiste enquanto o SPA estiver vivo — evita refetch a cada abertura do editor.
 */
const _cache = new Map<string, Option[]>();

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        /** category_id da categoria base configurada no template */
        templateCategoryId: string;
        /** Nome legível da categoria base (para o badge fixo) */
        templateCategoryName: string;
        /** Quantos níveis de sub-seleção exibir abaixo da base (default 3) */
        cascadeLevels?: number;
    }>(),
    { cascadeLevels: 3 },
);

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>();

const { t } = useT();

const selections = ref<string[]>(Array.from({ length: props.cascadeLevels }, () => ''));
const options = ref<Option[][]>(Array.from({ length: props.cascadeLevels }, () => []));
const loading = ref(false);

const childrenHttp = useHttp<Record<string, string>, Option[]>();
const pathHttp = useHttp<Record<string, string>, { path?: Option[] }>();

async function fetchChildren(parentId: string): Promise<Option[]> {
    if (_cache.has(parentId)) {
        return _cache.get(parentId)!;
    }

    const url = new URL('/categories/cascade/children', window.location.origin);
    url.searchParams.set('parent_id', parentId);
    await childrenHttp.get(url.toString());

    const result = Array.isArray(childrenHttp.response) ? childrenHttp.response : [];
    _cache.set(parentId, result);

    return result;
}

async function fetchPath(categoryId: string): Promise<Option[]> {
    const url = new URL('/categories/cascade/path', window.location.origin);
    url.searchParams.set('id', categoryId);
    await pathHttp.get(url.toString());

    return Array.isArray(pathHttp.response?.path) ? pathHttp.response!.path! : [];
}

/**
 * Categoria folha selecionada:
 * - subcategoria mais profunda selecionada, ou
 * - a própria categoria base do template (quando nada é selecionado abaixo)
 */
const leafCategoryId = computed((): string => {
    for (let i = selections.value.length - 1; i >= 0; i--) {
        if (selections.value[i] !== '') {
return selections.value[i];
}
    }

    return props.templateCategoryId;
});

watch(leafCategoryId, (value) => emit('update:modelValue', value));

async function hydrate(): Promise<void> {
    loading.value = true;
    selections.value = Array.from({ length: props.cascadeLevels }, () => '');
    options.value = Array.from({ length: props.cascadeLevels }, () => []);

    // Primeiro nível: filhos da categoria base — carregados uma única vez (cache)
    options.value[0] = await fetchChildren(props.templateCategoryId);

    const currentValue = props.modelValue;

    // Sem valor ou valor é a própria base → nada a pré-selecionar
    if (!currentValue || currentValue === props.templateCategoryId) {
        loading.value = false;

        return;
    }

    try {
        // Descobre o caminho completo da categoria atual para pré-selecionar os selects
        const fullPath = await fetchPath(currentValue);
        const baseIdx = fullPath.findIndex((n) => n.id === props.templateCategoryId);

        if (baseIdx === -1) {
            loading.value = false;

            return;
        }

        // Sub-caminho: apenas os nós abaixo da categoria base
        const subPath = fullPath.slice(baseIdx + 1);

        for (let i = 0; i < subPath.length && i < props.cascadeLevels; i++) {
            selections.value[i] = subPath[i].id;
        }

        // Carrega opções dos níveis intermediários (também cacheados)
        for (let i = 0; i < subPath.length - 1 && i + 1 < props.cascadeLevels; i++) {
            options.value[i + 1] = await fetchChildren(subPath[i].id);
        }
    } catch {
        // silencia — usuário pode re-selecionar manualmente
    }

    loading.value = false;
}

async function onLevelChange(level: number, value: string): Promise<void> {
    selections.value[level] = value;

    for (let j = level + 1; j < props.cascadeLevels; j++) {
        selections.value[j] = '';
        options.value[j] = [];
    }

    if (value !== '' && level + 1 < props.cascadeLevels) {
        options.value[level + 1] = await fetchChildren(value);
    }
}

function clearLevel(level: number): void {
    selections.value[level] = '';

    for (let j = level + 1; j < props.cascadeLevels; j++) {
        selections.value[j] = '';
        options.value[j] = [];
    }
}

const isLevelDisabled = (level: number): boolean =>
    loading.value || (level > 0 && options.value[level].length === 0);

onMounted(hydrate);

watch(
    () => props.modelValue,
    async (next) => {
        if (next === leafCategoryId.value) {
return;
}

        await hydrate();
    },
);
</script>

<template>
    <div class="space-y-2">
        <!-- Badge da categoria base (fixa — vem do template) -->
        <div class="flex items-center gap-2 flex-wrap">
            <span
                class="inline-flex items-center rounded-md bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary ring-1 ring-inset ring-primary/20"
            >
                {{ templateCategoryName }}
            </span>
            <span class="text-xs text-muted-foreground">{{ t('planogram-templates.category_select.base_template_hint') }}</span>
        </div>

        <!-- Sem filhos: apenas a categoria base está disponível -->
        <p v-if="!loading && options[0].length === 0" class="text-xs text-muted-foreground italic">
            {{ t('planogram-templates.category_select.no_subcategories') }}
        </p>

        <!-- Cascade de subcategorias -->
        <div
            v-else
            class="grid gap-2"
            :style="{ gridTemplateColumns: `repeat(${Math.min(cascadeLevels, 3)}, minmax(0, 1fr))` }"
        >
            <div
                v-for="level in cascadeLevels"
                :key="`slot-cat-${level - 1}`"
                class="relative flex items-stretch gap-1"
            >
                <select
                    :disabled="isLevelDisabled(level - 1)"
                    :value="selections[level - 1]"
                    class="flex h-9 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 py-2 pr-8 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
                    @change="onLevelChange(level - 1, ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">{{ t('planogram-templates.category_select.level_placeholder', { n: String(level) }) }}</option>
                    <option v-for="opt in options[level - 1]" :key="opt.id" :value="opt.id">
                        {{ opt.name }}
                    </option>
                </select>
                <button
                    v-if="selections[level - 1] !== ''"
                    type="button"
                    class="absolute top-1/2 right-0 flex size-9 items-center justify-center -translate-y-1/2"
                    @click="clearLevel(level - 1)"
                >
                    <X class="size-3 opacity-50" />
                </button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            {{ t('planogram-templates.category_select.no_selection_hint') }}
        </p>
    </div>
</template>
