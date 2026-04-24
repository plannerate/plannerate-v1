<template>
    <div class="flex flex-col gap-2 w-full">
        <Popover v-model:open="isOpen">
            <PopoverTrigger as-child>
                <Button variant="outline" class="w-full justify-start">
                    <Filter class="mr-2 size-4" />
                    <span class="truncate">
                        {{ selectedCategoryName || 'Selecione uma categoria' }}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-80 p-0" align="start">
                <div class="flex flex-col">
                    <!-- Breadcrumb da hierarquia -->
                    <div v-if="hierarchy.length > 0 || currentCategory" class="border-b border-border p-3">
                        <div class="flex flex-wrap items-center gap-1 text-xs">
                            <button
                                v-for="(cat) in hierarchy"
                                :key="cat.id"
                                @click="navigateToCategory(cat.id)"
                                class="text-muted-foreground hover:text-foreground transition-colors"
                            >
                                {{ cat.name }}
                            </button>
                            <span v-if="hierarchy.length > 0 && currentCategory" class="text-muted-foreground">/</span>
                            <span v-if="currentCategory" class="font-medium text-foreground">
                                {{ currentCategory.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Botão para voltar ao nível anterior -->
                    <div v-if="currentCategory?.category_id" class="border-b border-border p-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="w-full justify-start"
                            @click="navigateToCategory(currentCategory.category_id)"
                        >
                            <ChevronLeft class="mr-2 size-4" />
                            Voltar
                        </Button>
                    </div>

                    <!-- Lista de categorias filhas -->
                    <div class="max-h-64 overflow-y-auto p-2">
                        <div v-if="isLoading" class="flex items-center justify-center p-4">
                            <div class="text-sm text-muted-foreground">Carregando...</div>
                        </div>
                        <div v-else-if="children.length === 0" class="flex items-center justify-center p-4">
                            <div class="text-sm text-muted-foreground">Nenhuma subcategoria encontrada</div>
                        </div>
                        <div v-else class="space-y-1">
                            <button
                                v-for="child in children"
                                :key="child.id"
                                @click="selectCategory(child)"
                                class="w-full flex items-center justify-between rounded-md px-3 py-2 text-sm hover:bg-accent transition-colors text-left"
                                :class="{ 
                                    'bg-accent font-medium': category === child.id,
                                    'text-muted-foreground': category !== child.id
                                }"
                            >
                                <span>{{ child.name }}</span>
                                <div class="flex items-center gap-2">
                                    <span v-if="category === child.id" class="text-xs text-primary">✓</span>
                                    <ChevronRight v-if="child.has_children" class="size-4 text-muted-foreground" />
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Botão para limpar seleção -->
                    <div v-if="category" class="border-t border-border p-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            @click="clearSelection"
                        >
                            Limpar seleção
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>

<script setup lang="ts">
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Filter, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';

interface CategoryItem {
    id: string;
    name: string;
    slug?: string;
    level_name?: string;
    hierarchy_position?: number;
    category_id?: string;
    has_children?: boolean;
}

interface Props {
    current: any | null; // Aceita qualquer objeto de categoria (pode ter estrutura diferente)
}

const props = withDefaults(defineProps<Props>(), {
    current: null,
});

const emit = defineEmits<{
    (e: 'update:filters', filters: { category: string }): void;
}>();

interface CategoryResponse {
    current: CategoryItem | null;
    hierarchy: CategoryItem[];
    children: CategoryItem[];
}

const category = defineModel<string>('category');
const isOpen = ref(false);
const isLoading = ref(false);
const currentCategory = ref<CategoryItem | null>(props.current);
const hierarchy = ref<CategoryItem[]>([]);
const children = ref<CategoryItem[]>([]);

const selectedCategoryName = computed(() => {
    // Primeiro, tenta usar a categoria selecionada (v-model)
    if (category.value) {
        // Verifica se é a categoria atual carregada
        if (currentCategory.value?.id === category.value) {
            return currentCategory.value.name;
        }
        // Busca na hierarquia ou filhos
        const found = hierarchy.value.find(c => c.id === category.value) ||
                      children.value.find(c => c.id === category.value);
        if (found) return found.name;
    }
    
    // Se não há categoria selecionada no v-model, mas há uma categoria inicial (props.current)
    // Isso acontece quando o componente é montado com uma categoria inicial
    if (props.current?.name) {
        return props.current.name;
    }
    
    // Se há currentCategory mas não há category.value, usa o currentCategory
    if (currentCategory.value?.name) {
        return currentCategory.value.name;
    }
    
    return null;
});

async function loadCategories(categoryId: string | null = null) {
    isLoading.value = true;
    try {
        const url = categoryId 
            ? `/api/editor/${categoryId}/categories`
            : '/api/editor/categories';
        const response = await axios.get<CategoryResponse>(url);
        
        currentCategory.value = response.data.current; 
        hierarchy.value = response.data.hierarchy || [];
        children.value = response.data.children || [];
    } catch (error) {
        console.error('Erro ao carregar categorias:', error);
        toast.error('Erro ao carregar categorias');
        children.value = [];
        hierarchy.value = [];
        currentCategory.value = null;
    } finally {
        isLoading.value = false;
    }
}

function navigateToCategory(categoryId: string | null) {
    loadCategories(categoryId);
}

function selectCategory(cat: CategoryItem) {
    // Atualiza a categoria selecionada
    category.value = cat.id;
    
    // Emite evento para atualizar filtros
    emit('update:filters', { category: cat.id });
    
    // Se a categoria tem filhos, navega para ela (mostra os filhos)
    if (cat.has_children) {
        navigateToCategory(cat.id);
    } else {
        // Se não tem filhos, fecha o popover (categoria final selecionada)
        isOpen.value = false;
    }
}

function clearSelection() {
    category.value = '';
    // Emite evento para limpar filtro de categoria
    emit('update:filters', { category: '' });
    loadCategories(null);
    isOpen.value = false;
}

// Carrega categorias quando o componente é montado
onMounted(() => {
    // Se há uma categoria inicial (props.current), define como selecionada e carrega hierarquia
    if (props.current?.id) {
        // Define a categoria como selecionada se ainda não estiver
        if (!category.value) {
            category.value = props.current.id;
        }
        // Carrega a hierarquia da categoria inicial
        loadCategories(props.current.id);
    } else if (category.value) {
        // Se já há uma categoria selecionada, carrega sua hierarquia
        loadCategories(category.value);
    } else {
        // Caso contrário, carrega as categorias raiz
        loadCategories(null);
    }
});

// Observa mudanças na prop current para atualizar quando necessário
watch(() => props.current, (newCurrent) => {
    if (newCurrent?.id) {
        // Se há uma categoria atual e não há categoria selecionada, define
        if (!category.value || category.value !== newCurrent.id) {
            category.value = newCurrent.id;
        }
        // Se a categoria atual mudou, recarrega a hierarquia
        if (newCurrent.id !== currentCategory.value?.id) {
            loadCategories(newCurrent.id);
        }
    }
}, { immediate: true });

// Observa mudanças na categoria selecionada para atualizar a hierarquia e emitir evento
watch(category, async (newCategoryId, oldCategoryId) => {
    // Emite evento quando a categoria muda (exceto na inicialização)
    if (oldCategoryId !== undefined && newCategoryId !== oldCategoryId) {
        emit('update:filters', { category: newCategoryId || '' });
    }
    
    if (newCategoryId && newCategoryId !== currentCategory.value?.id) {
        await loadCategories(newCategoryId);
    } else if (!newCategoryId) {
        await loadCategories(null);
    }
}, { immediate: false });

// Observa quando o popover é aberto para recarregar se necessário
watch(isOpen, (open) => {
    if (open) {
        if (category.value) {
            loadCategories(category.value);
        } else {
            loadCategories(null);
        }
    }
});
</script>