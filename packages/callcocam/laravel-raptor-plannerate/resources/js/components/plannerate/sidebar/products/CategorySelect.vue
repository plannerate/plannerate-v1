<template>
    <div class="category-selector relative">
        <div v-if="isLoading"
            class="absolute inset-0 bg-white/50 dark:bg-black/50 z-10 flex items-center justify-center rounded-md">
            <div class="flex items-center justify-center space-x-2">
                <div
                    class="w-4 h-4 border-2 border-t-2 border-gray-200 rounded-full animate-spin dark:border-gray-600 border-t-primary">
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('plannerate.sidebar.products.loading')
                    }}</span>
            </div>
        </div>

        <!-- Indicador de categoria-base travada (só no modo ancorado) -->
        <div v-if="rootCategoryId && rootLevelIndex >= 0 && rootBreadcrumb"
            class="mb-3 flex items-start gap-2 rounded border border-amber-200 bg-amber-50 p-2 text-xs dark:border-amber-800 dark:bg-amber-950">
            <Lock class="mt-0.5 size-3 shrink-0 text-amber-600 dark:text-amber-400" />
            <div>
                <span class="text-amber-700 dark:text-amber-300">{{ t('plannerate.sidebar.products.root_locked') }}: </span>
                <span class="font-semibold text-amber-800 dark:text-amber-200">{{ rootBreadcrumb }}</span>
                <p class="mt-0.5 text-amber-600 dark:text-amber-400">{{ t('plannerate.sidebar.products.root_locked_hint') }}</p>
            </div>
        </div>

        <div v-if="breadcrumbPath"
            class="text-xs p-2 mb-3 bg-gray-50 dark:bg-white/10 rounded border border-dashed border-gray-300 dark:border-gray-700">
            <span class="font-medium text-gray-600 dark:text-gray-400">{{ t('plannerate.sidebar.products.selection')
                }}</span>
            <span class="ml-1 font-semibold text-gray-800 dark:text-gray-200">{{ breadcrumbPath }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <template v-for="(level, index) in levels" :key="level.key">
                <div v-if="!rootCategoryId || rootLevelIndex < 0 || index > rootLevelIndex" class="flex flex-col gap-1">
                    <div class="flex items-center justify-between gap-2">
                        <Label :for="`category-${level.key}`" class="text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ t(`plannerate.sidebar.products.levels.${level.key}`) }}
                            <span v-if="required && index === 0" class="text-red-500">*</span>
                        </Label>
                        <button v-if="selections[level.key]" type="button"
                            class="text-[11px] text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50"
                            :disabled="disabled || levelLoading[level.key]" @click="clearSelection(index)">
                            {{ t('plannerate.sidebar.products.clear') }}
                        </button>
                    </div>
                    <Select :modelValue="selections[level.key]"
                        @update:modelValue="(val: any) => handleSelection(index, val)"
                        :disabled="disabled || levelLoading[level.key] || (index > 0 && !selections[levels[index - 1].key])"
                        class="z-1200">
                        <SelectTrigger
                            class="h-8 text-xs border border-gray-300 dark:border-gray-600 rounded px-2 w-full dark:bg-input/30">
                            <SelectValue
                                :placeholder="t('plannerate.sidebar.products.select_level', { level: t(`plannerate.sidebar.products.levels.${level.key}`).toLowerCase() })" />
                        </SelectTrigger>
                        <SelectContent class="z-1200">
                            <SelectItem v-for="option in levelOptions[level.key]" :key="option.id" :value="option.id">
                                {{ option.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <span v-if="levelErrors[level.key]" class="text-xs text-red-500 dark:text-red-400">{{
                        levelErrors[level.key] }}</span>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Lock } from 'lucide-vue-next'
import editorCategories from '@/routes/api/editor/categories'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from '@/components/ui/select'
import { useT } from '@/composables/useT'
import { wayfinderPath } from '../../../../libs/wayfinderPath'

interface Category {
    id: string
    name: string
}

interface CategoryResponse {
    children?: Category[]
    data?: Category[]
    hierarchy?: Category[]
}

interface Props {
    modelValue?: string | null
    required?: boolean
    disabled?: boolean
    /** Quando definido, ancora a cascata nesta categoria: só é possível navegar para descendentes. */
    rootCategoryId?: string | null
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    required: false,
    disabled: false,
    rootCategoryId: null,
})
const { t } = useT()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void
}>()

const levels = [
    { key: 'segmento_varejista' },
    { key: 'departamento' },
    { key: 'subdepartamento' },
    { key: 'categoria' },
    { key: 'subcategoria' },
    { key: 'segmento' },
    { key: 'subsegmento' }
] as const

type LevelKey = typeof levels[number]['key']

const selections = ref<Record<LevelKey, string | null>>(
    Object.fromEntries(levels.map(l => [l.key, null])) as Record<LevelKey, string | null>
)

const levelOptions = ref<Record<LevelKey, Category[]>>(
    Object.fromEntries(levels.map(l => [l.key, []])) as unknown as Record<LevelKey, Category[]>
)

const levelLoading = ref<Record<LevelKey, boolean>>(
    Object.fromEntries(levels.map(l => [l.key, false])) as Record<LevelKey, boolean>
)

const levelErrors = ref<Record<LevelKey, string>>(
    Object.fromEntries(levels.map(l => [l.key, ''])) as Record<LevelKey, string>
)

const optionsCache = ref<Map<string, Category[]>>(new Map())
const hierarchyCache = ref<Map<string, any[]>>(new Map())

/** Índice do nível onde rootCategoryId está fixado. -1 = não ancorado ou não carregado ainda. */
const rootLevelIndex = ref<number>(-1)

/** Hierarquia completa da categoria-base (do topo até rootCategoryId), para exibir o breadcrumb travado. */
const storedRootHierarchy = ref<Category[]>([])

const subdomain = computed(() => window.location.hostname.split('.')[0] || '')

function getCategoriesUrl(categoryId: string | null = null): string {
    if (categoryId) {
        return wayfinderPath(editorCategories.show.url({
            subdomain: subdomain.value,
            categoryId,
        }))
    }
    return wayfinderPath(editorCategories.index.url(subdomain.value))
}

async function fetchCategories(url: string): Promise<Response> {
    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
}

const isLoading = computed(() =>
    Object.values(levelLoading.value).some(loading => loading)
)

const breadcrumbPath = computed(() => {
    return levels
        .map(level => {
            const selectedId = selections.value[level.key]

            if (!selectedId) {
                return null
            }

            const option = levelOptions.value[level.key]?.find(opt => opt.id === selectedId)

            return option?.name
        })
        .filter(Boolean)
        .join(' / ')
})

/** Texto do caminho travado (do topo até rootCategoryId). */
const rootBreadcrumb = computed((): string | null => {
    if (!props.rootCategoryId || rootLevelIndex.value === -1) return null
    return storedRootHierarchy.value
        .map(item => item.name)
        .filter(Boolean)
        .join(' / ')
})

onMounted(() => {
    if (props.rootCategoryId) {
        initWithRoot()
    } else {
        loadOptions(0)
        if (props.modelValue) {
            loadCascadeForValue(props.modelValue)
        }
    }
})

/**
 * Inicializa a cascata ancorada na rootCategoryId.
 * Carrega a hierarquia da raiz, determina o nível dela e pré-carrega os filhos imediatos.
 * Se modelValue aponta para uma subcategoria abaixo da raiz, carrega também essa cascata.
 */
async function initWithRoot(): Promise<void> {
    if (!props.rootCategoryId) return

    await loadCascadeForValue(props.rootCategoryId)

    // Detecta em qual nível a rootCategoryId ficou após o carregamento
    for (let i = levels.length - 1; i >= 0; i--) {
        if (selections.value[levels[i].key] === props.rootCategoryId) {
            rootLevelIndex.value = i
            break
        }
    }

    // Se modelValue aponta para uma subcategoria diferente da raiz, carrega sua cascata
    const initialValue = props.modelValue
    if (initialValue && initialValue !== props.rootCategoryId) {
        await loadCascadeForValue(initialValue)
    }
}

watch(() => props.modelValue, async (newVal, oldVal) => {
    if (newVal === oldVal || newVal === getDeepestSelection()) return

    if (props.rootCategoryId && rootLevelIndex.value >= 0) {
        // Modo ancorado: só reseta abaixo da raiz
        resetBelowRoot()
        if (newVal && newVal !== props.rootCategoryId) {
            await loadCascadeForValue(newVal)
        }
        return
    }

    if (!props.rootCategoryId) {
        // Modo normal: reset completo
        resetSelections()
        if (newVal) {
            await loadCascadeForValue(newVal)
        }
    }
    // Se rootCategoryId está definido mas initWithRoot ainda não terminou: ignorar (o initWithRoot cuidará)
})

watch(() => getDeepestSelection(), (newVal) => {
    emit('update:modelValue', newVal)
})

function getDeepestSelection(): string | null {
    for (let i = levels.length - 1; i >= 0; i--) {
        const selected = selections.value[levels[i].key]

        if (selected) {
            return selected
        }
    }

    return null
}

function resetSelections() {
    levels.forEach(level => {
        selections.value[level.key] = null
        levelOptions.value[level.key] = []
    })
}

/**
 * Reseta apenas os níveis abaixo da rootCategoryId, preservando os níveis travados.
 */
function resetBelowRoot(): void {
    const startFrom = rootLevelIndex.value + 1
    for (let i = startFrom; i < levels.length; i++) {
        selections.value[levels[i].key] = null
        levelOptions.value[levels[i].key] = []
        levelErrors.value[levels[i].key] = ''
    }
}

async function handleSelection(levelIndex: number, value: string | null): Promise<void> {
    const currentLevel = levels[levelIndex]
    selections.value[currentLevel.key] = value

    for (let i = levelIndex + 1; i < levels.length; i++) {
        const childLevel = levels[i]
        selections.value[childLevel.key] = null
        levelOptions.value[childLevel.key] = []
    }

    if (value && levelIndex < levels.length - 1) {
        await loadOptions(levelIndex + 1)
    }
}

async function loadOptions(levelIndex: number): Promise<Category[]> {
    if (levelIndex < 0 || levelIndex >= levels.length) {
        return []
    }

    const currentLevel = levels[levelIndex]
    levelLoading.value[currentLevel.key] = true
    levelErrors.value[currentLevel.key] = ''

    try {
        let url: string

        if (levelIndex === 0) {
            url = getCategoriesUrl()
        } else {
            const parentLevel = levels[levelIndex - 1]
            const parentId = selections.value[parentLevel.key]

            if (!parentId) {
                levelOptions.value[currentLevel.key] = []
                levelLoading.value[currentLevel.key] = false

                return []
            }

            url = getCategoriesUrl(parentId)
        }

        const cachedOptions = optionsCache.value.get(url)

        if (cachedOptions) {
            const clonedOptions = [...cachedOptions]
            levelOptions.value[currentLevel.key] = clonedOptions

            if (clonedOptions.length === 0) {
                levelErrors.value[currentLevel.key] = t('plannerate.sidebar.products.errors.no_options')
            }

            return clonedOptions
        }

        const response = await fetchCategories(url)

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        const data = await response.json() as CategoryResponse
        const children = data.children || data.data || []

        if (Array.isArray(children)) {
            const normalizedChildren = children as Category[]
            optionsCache.value.set(url, [...normalizedChildren])
            levelOptions.value[currentLevel.key] = normalizedChildren

            if (normalizedChildren.length === 0) {
                levelErrors.value[currentLevel.key] = t('plannerate.sidebar.products.errors.no_options')
            }

            return normalizedChildren
        } else {
            optionsCache.value.set(url, [])
            levelOptions.value[currentLevel.key] = []
            levelErrors.value[currentLevel.key] = t('plannerate.sidebar.products.errors.invalid_response')

            return []
        }
    } catch (error) {
        levelOptions.value[currentLevel.key] = []
        levelErrors.value[currentLevel.key] = t('plannerate.sidebar.products.errors.load_options')

        return []
    } finally {
        levelLoading.value[currentLevel.key] = false
    }
}

async function loadCascadeForValue(categoryId: string): Promise<void> {
    try {
        let hierarchy = hierarchyCache.value.get(categoryId)

        if (!hierarchy) {
            const response = await fetchCategories(getCategoriesUrl(categoryId))

            if (response.status === 404) {
                hierarchyCache.value.delete(categoryId)
                emit('update:modelValue', null)
                resetSelections()
                await loadOptions(0)

                return
            }

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const data = await response.json() as CategoryResponse
            const normalizedHierarchy: any[] = Array.isArray(data.hierarchy) ? data.hierarchy : []
            hierarchy = normalizedHierarchy
            hierarchyCache.value.set(categoryId, [...normalizedHierarchy])
        }

        // Armazena a hierarquia da categoria-base para exibir o breadcrumb travado
        if (props.rootCategoryId && categoryId === props.rootCategoryId) {
            storedRootHierarchy.value = [...(hierarchy as Category[])]
        }

        if (Array.isArray(hierarchy) && hierarchy.length > 0) {
            const cascade = hierarchy.map((cat: any) => cat.id)

            for (let i = 0; i < cascade.length && i < levels.length; i++) {
                selections.value[levels[i].key] = cascade[i]

                if (i < cascade.length - 1) {
                    await loadOptions(i + 1)
                }
            }

            // Precarrega também o próximo nível após o último item selecionado
            const deepestSelectedIndex = Math.min(cascade.length - 1, levels.length - 1)
            const nextLevelIndex = deepestSelectedIndex + 1

            if (nextLevelIndex < levels.length) {
                await loadOptions(nextLevelIndex)
            }
        }
    } catch (error) {
        console.error('Erro ao carregar cascata de categorias:', error)
    }
}

function clearSelection(levelIndex: number): void {
    // Não permitir limpar níveis travados pela raiz
    if (props.rootCategoryId && levelIndex <= rootLevelIndex.value) return

    const currentLevel = levels[levelIndex]
    selections.value[currentLevel.key] = null

    for (let i = levelIndex + 1; i < levels.length; i++) {
        const childLevel = levels[i]
        selections.value[childLevel.key] = null
        levelOptions.value[childLevel.key] = []
        levelErrors.value[childLevel.key] = ''
    }
}
</script>
