<template>
    <div class="category-selector relative">
        <div v-if="isLoading" 
            class="absolute inset-0 bg-white/50 dark:bg-black/50 z-10 flex items-center justify-center rounded-md">
            <div class="flex items-center justify-center space-x-2">
                <div class="w-4 h-4 border-2 border-t-2 border-gray-200 rounded-full animate-spin dark:border-gray-600 border-t-primary"></div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Carregando...</span>
            </div>
        </div>
        
        <div v-if="breadcrumbPath"
            class="text-xs p-2 mb-3 bg-gray-50 dark:bg-white/10 rounded border border-dashed border-gray-300 dark:border-gray-700">
            <span class="font-medium text-gray-600 dark:text-gray-400">Seleção:</span>
            <span class="ml-1 font-semibold text-gray-800 dark:text-gray-200">{{ breadcrumbPath }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div v-for="(level, index) in levels" 
                :key="level.key" 
                class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-2">
                    <Label :for="`category-${level.key}`" class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        {{ level.label }}
                        <span v-if="required && index === 0" class="text-red-500">*</span>
                    </Label>
                    <button
                        v-if="selections[level.key]"
                        type="button"
                        class="text-[11px] text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50"
                        :disabled="disabled || levelLoading[level.key]"
                        @click="clearSelection(index)">
                        Limpar
                    </button>
                </div>
                <Select 
                    :modelValue="selections[level.key]"
                    @update:modelValue="(val: any ) => handleSelection(index, val)"
                    :disabled="disabled || levelLoading[level.key] || (index > 0 && !selections[levels[index - 1].key])" 
                    class="z-[1200]">
                    <SelectTrigger class="h-8 text-xs border border-gray-300 dark:border-gray-600 rounded px-2 w-full dark:bg-input/30">
                        <SelectValue :placeholder="`Selecione ${level.label.toLowerCase()}`" />
                    </SelectTrigger>
                    <SelectContent class="z-[1200]">
                        <SelectItem 
                            v-for="option in levelOptions[level.key]" 
                            :key="option.id"
                            :value="option.id">
                            {{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <span v-if="levelErrors[level.key]" 
                    class="text-xs text-red-500 dark:text-red-400">{{ levelErrors[level.key] }}</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from '@/components/ui/select'

interface Category {
    id: string
    name: string
}

interface Props {
    modelValue?: string | null
    required?: boolean
    disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    required: false,
    disabled: false
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void
}>()

const levels = [
    { key: 'segmento_varejista', label: 'Segmento Varejista' },
    { key: 'departamento', label: 'Departamento' },
    { key: 'subdepartamento', label: 'Subdepartamento' },
    { key: 'categoria', label: 'Categoria' },
    { key: 'subcategoria', label: 'Subcategoria' },
    { key: 'segmento', label: 'Segmento' },
    { key: 'subsegmento', label: 'Subsegmento' }
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

onMounted(() => {
    loadOptions(0)
})

watch(() => props.modelValue, (newVal) => {
    if (newVal && newVal !== getDeepestSelection()) {
        resetSelections()

        if (newVal) {
            loadCascadeForValue(newVal)
        }
    }
}, { immediate: true })

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
            url = '/api/editor/categories'
        } else {
            const parentLevel = levels[levelIndex - 1]
            const parentId = selections.value[parentLevel.key]
            
            if (!parentId) {
                levelOptions.value[currentLevel.key] = []
                levelLoading.value[currentLevel.key] = false

                return []
            }
            
            url = `/api/editor/${parentId}/categories`
        }

        const cachedOptions = optionsCache.value.get(url)

        if (cachedOptions) {
            const clonedOptions = [...cachedOptions]
            levelOptions.value[currentLevel.key] = clonedOptions

            if (clonedOptions.length === 0) {
                levelErrors.value[currentLevel.key] = 'Nenhuma opção disponível'
            }

            return clonedOptions
        }

        const response = await fetch(url)
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        const data = await response.json()
        const children = data.children || data.data || []

        if (Array.isArray(children)) {
            const normalizedChildren = children as Category[]
            optionsCache.value.set(url, [...normalizedChildren])
            levelOptions.value[currentLevel.key] = normalizedChildren

            if (normalizedChildren.length === 0) {
                levelErrors.value[currentLevel.key] = 'Nenhuma opção disponível'
            }

            return normalizedChildren
        } else {
            optionsCache.value.set(url, [])
            levelOptions.value[currentLevel.key] = []
            levelErrors.value[currentLevel.key] = 'Formato de resposta inválido'

            return []
        }
    } catch (error) {
        console.error(`Erro ao carregar opções para ${currentLevel.label}:`, error)
        levelOptions.value[currentLevel.key] = []
        levelErrors.value[currentLevel.key] = 'Erro ao carregar opções'

        return []
    } finally {
        levelLoading.value[currentLevel.key] = false
    }
}

async function loadCascadeForValue(categoryId: string): Promise<void> {
    try {
        let hierarchy = hierarchyCache.value.get(categoryId)

        if (!hierarchy) {
            const response = await fetch(`/api/editor/${categoryId}/categories`)

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const data = await response.json()
            const normalizedHierarchy: any[] = Array.isArray(data.hierarchy) ? data.hierarchy : []
            hierarchy = normalizedHierarchy
            hierarchyCache.value.set(categoryId, [...normalizedHierarchy])
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
            // para evitar o usuário ter que "selecionar de novo" apenas para abrir opções.
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