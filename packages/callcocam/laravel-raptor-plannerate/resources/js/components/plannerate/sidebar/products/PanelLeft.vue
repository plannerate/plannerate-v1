<template>
    <Transition enter-active-class="transition-transform duration-300 ease-out" enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0" leave-active-class="transition-transform duration-300 ease-in"
        leave-from-class="translate-x-0" leave-to-class="-translate-x-full">
        <div v-if="open" class="relative z-20 flex h-full w-full sm:w-80 2xl:w-96 flex-col border-r border-border bg-background"
            data-products-panel>
            <!-- Header -->


            <!-- Filters Section - Fixed -->
            <Collapsible :open="isOpen" class="border-b  border-border bg-background flex-col">
                <CollapsibleTrigger @click.stop="isOpen = !isOpen"
                    class="w-full px-4 py-2 text-left font-medium flex items-center justify-between cursor-pointer">
                    <span>{{ t('plannerate.sidebar.products.filters_and_search') }}</span>
                    <button class="  z-10 cursor-pointer rounded-full p-1 transition-colors hover:bg-accent"
                        @click="emit('close')" type="button">
                        <X class="size-4 text-foreground" />
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent class="border-t border-border bg-background">
                    <div class="relative mt-2 shrink-0 space-y-4 border-b border-border p-4">
                        <!-- Search -->
                        <ProductSearch v-model="searchQuery" :disabled="isLoading" />

                        <!-- Divider -->
                        <div class="border-t border-border" />

                        <Popover>
                            <PopoverTrigger as-child>
                                <button
                                    class="w-full px-3 py-2 text-left border border-input rounded-md hover:bg-accent transition-colors">
                                    <span class="block text-[11px] uppercase tracking-wide text-muted-foreground">
                                        {{ t('plannerate.sidebar.products.market_classification') }}
                                    </span>
                                    <span v-if="isLoadingCategoryPath"
                                        class="mt-0.5 block text-sm text-muted-foreground">
                                        {{ t('plannerate.sidebar.products.loading') }}
                                    </span>
                                    <template v-else-if="categoryPath.length">
                                        <span v-if="categoryAncestors.length"
                                            class="mt-0.5 block text-[11px] leading-snug text-muted-foreground">
                                            {{ categoryAncestors.join(' / ') }}
                                        </span>
                                        <span class="block text-sm font-medium leading-snug text-foreground">
                                            {{ categoryLeaf }}
                                        </span>
                                    </template>
                                    <span v-else class="mt-0.5 block text-sm text-muted-foreground">
                                        {{ t('plannerate.sidebar.products.all_categories') }}
                                    </span>
                                </button>
                            </PopoverTrigger>
                            <PopoverContent align="start" class="w-full md:max-w-7xl   z-[1000]">
                                <div class="grid grid-cols-1 gap-8">
                                    <!-- Coluna Esquerda -->
                                    <div class="flex flex-col gap-2">
                                        <h3 class="text-sm font-semibold text-foreground">{{ t('plannerate.sidebar.products.market_level') }}</h3>
                                        <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.products.market_level_help') }}</p>
                                    </div>
                                    <!-- Coluna Direita -->
                                    <div class="flex flex-col gap-4 z-[1500]">
                                        <Category :modelValue="filters.category"
                                            @update:modelValue="(val) => updateFilters({ ...filters, category: val ?? '' })" />
                                    </div>
                                </div>
                            </PopoverContent>
                        </Popover>
                        <!-- Filters -->
                        <ProductFilters :filters="filters" @update:filters="updateFilters" :disabled="isLoading" />

                        <!-- Stats -->
                        <ProductStats :total="total" :used-count="usedCount" />
                    </div>
                </CollapsibleContent>
            </Collapsible>

            <!-- Content - Scrollable -->
            <div ref="scrollContainer" class="flex-1 overflow-y-auto p-4" @scroll="handleScroll">
                <!-- Products List -->
                <ProductList :products="products" :is-loading="isLoading" :is-loading-more="isLoadingMore"
                    :show-end-message="currentPage >= lastPage && products.length > 0
                        " :search-query="searchQuery" />
            </div>
        </div>
    </Transition>
</template>
<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, onMounted, provide, ref, useTemplateRef, watch } from 'vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useProductsPanel } from '@/composables/plannerate/products/useProductsPanel';
import { useT } from '@/composables/useT';
import editorCategories from '@/routes/api/editor/categories';
import type { Category as CategoryType } from '@/types/planogram';
import { wayfinderPath } from '../../../../libs/wayfinderPath';
import Category from './CategorySelect.vue';
import ProductFilters from './Filters.vue';
import ProductList from './ProductList.vue';
import ProductSearch from './Search.vue';
import ProductStats from './Stats.vue';

interface Props {
    gondolaId: string;
    planogramId: string;
    subdomain: string;
    category?: CategoryType | null;
}

const props = defineProps<Props>();
const { t } = useT();
const open = defineModel<boolean>('open');
const isOpen = ref(true) 
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'reload-function', fn: () => Promise<void>): void;
    (e: 'remove-used-product', fn: (productId: string) => void): void;
}>();

// Template ref para o scroll container
const scrollContainer = useTemplateRef<HTMLElement>('scrollContainer');

// Composable para gerenciar produtos
const {
    products,
    searchQuery,
    filters,
    updateFilters,
    isLoading,
    isLoadingMore,
    currentPage,
    lastPage,
    total,
    usedCount,
    handleScroll,
    removeUsedProduct,
    reloadCurrentPage,
} = useProductsPanel({
    gondolaId: props.gondolaId,
    planogramId: props.planogramId, 
    category: props.category || null,
    scrollContainer,
});

/**
 * Hierarquia da categoria filtrada, exibida no próprio botão do popover para que
 * dê para saber o que está filtrado sem abrir o seletor.
 *
 * Resolvida aqui — e não lida do CategorySelect — porque aquele componente vive
 * dentro do PopoverContent e é desmontado com o popover fechado, que é justamente
 * quando o caminho precisa aparecer.
 */
const categoryPath = ref<string[]>([]);
const isLoadingCategoryPath = ref(false);
const categoryAncestors = computed(() => categoryPath.value.slice(0, -1));
const categoryLeaf = computed(() => categoryPath.value[categoryPath.value.length - 1] ?? '');

const loadCategoryPath = async (categoryId: string): Promise<void> => {
    if (!categoryId) {
        categoryPath.value = [];

        return;
    }

    isLoadingCategoryPath.value = true;

    try {
        const url = wayfinderPath(
            editorCategories.show.url({ subdomain: props.subdomain, categoryId }),
        );
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            categoryPath.value = [];

            return;
        }

        const data = await response.json();

        categoryPath.value = Array.isArray(data.hierarchy)
            ? data.hierarchy.map((item: { name?: string }) => item.name).filter(Boolean)
            : [];
    } catch {
        categoryPath.value = [];
    } finally {
        isLoadingCategoryPath.value = false;
    }
};

watch(
    () => filters.category,
    (categoryId) => {
        loadCategoryPath(categoryId);
    },
    { immediate: true },
);

// Emite a função de reload para o componente pai no mount
onMounted(() => {
    emit('reload-function', reloadCurrentPage);
    // Emite a função removeUsedProduct para o componente pai fazer provide
    emit('remove-used-product', removeUsedProduct);
});

// Fornece removeUsedProduct para componentes filhos via provide/inject (mantém para Card.vue)
provide('removeUsedProduct', removeUsedProduct);

// Fornece getProduct para buscar produto por ID
const getProduct = (productId: string) => {
    return products.value.find((p) => p.id === productId);
};
provide('getProduct', getProduct);
</script>
