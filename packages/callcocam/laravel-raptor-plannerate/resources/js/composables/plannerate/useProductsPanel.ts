import { onMounted, reactive, ref, watch  } from 'vue';
import type {Ref} from 'vue';
import type { Category, Product } from '@/types/planogram';
import { products as productsRoute } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/GondolaController';

interface UseProductsPanelOptions {
    gondolaId: string;
    category?: Category;
    planogramId: string;
    subdomain: string;
    scrollContainer: Ref<HTMLElement | null>;
}

export interface ProductFilters {
    showUsed: boolean;
    /** true = apenas com dimensões, false = todos */
    hasDimensions: boolean;
    category: string;
}

const STORAGE_KEY_FILTERS = 'plannerate_product_filters';
const isBrowser = typeof window !== 'undefined';

let didMount = false;

export function useProductsPanel(options: UseProductsPanelOptions) {
    const products = ref<Product[]>([]);
    const searchQuery = ref('');
    const filters = reactive<ProductFilters>({
        showUsed: false,
        hasDimensions: true,
        category: options?.category?.id || '',
    }); 
    const isLoading = ref(false);
    const isLoadingMore = ref(false);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const total = ref(0);
    const usedCount = ref(0);

    const loadProducts = async () => {
        if (isLoading.value || isLoadingMore.value) {
return;
}

        if (currentPage.value === 1) {
            isLoading.value = true;
        } else {
            isLoadingMore.value = true;
        }

        try {
            const params = new URLSearchParams({
                page: currentPage.value.toString(),
                per_page: '15',
                search: searchQuery.value,
                show_used: filters.showUsed.toString(),
                with_dimensions: filters.hasDimensions.toString(),
                category: filters.category.toString(),
            });

            const url = productsRoute.url({ subdomain: options.subdomain, planogram: options.planogramId, gondola: options.gondolaId });
            const response = await fetch(
                `${url}?${params}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
throw new Error('Failed to load products');
}

            const data = await response.json();

            if (currentPage.value === 1) {
                products.value = data.products;
            } else {
                products.value.push(...data.products);
            }

            lastPage.value = data.pagination.last_page;
            total.value = data.pagination.total;
            usedCount.value = data.used_count;
        } catch (error) {
            console.error('Error loading products:', error);
        } finally {
            isLoading.value = false;
            isLoadingMore.value = false;
        }
    };

    const resetAndLoad = () => {
        products.value = [];
        currentPage.value = 1;
        loadProducts();
    };

    /**
     * Recarrega a lista mantendo a página atual
     * Útil para atualizar produtos após mudanças sem perder o scroll
     */
    const reloadCurrentPage = async () => {
        const savedPage = currentPage.value;
        currentPage.value = 1;
        products.value = [];
        
        // Recarrega até a página atual
        for (let page = 1; page <= savedPage; page++) {
            currentPage.value = page;
            await loadProducts();
        }
    };

    const handleScroll = () => {
        if (
            !options.scrollContainer.value ||
            isLoadingMore.value ||
            currentPage.value >= lastPage.value
        ) {
            return;
        }

        const { scrollTop, scrollHeight, clientHeight } =
            options.scrollContainer.value;

        // Carregar mais quando estiver a 100px do final
        if (scrollHeight - scrollTop - clientHeight < 100) {
            currentPage.value++;
            loadProducts();
        }
    };

    onMounted(() => {
        // Carrega filtros do localStorage (migra withDimensions -> hasDimensions se existir)
        const stored = isBrowser
            ? window.localStorage.getItem(STORAGE_KEY_FILTERS)
            : null;

        if (stored) {
            try {
                const parsed = JSON.parse(stored) as Record<string, unknown>;

                if (parsed.withDimensions !== undefined && parsed.hasDimensions === undefined) {
                    parsed.hasDimensions = parsed.withDimensions === 'published';
                    delete parsed.withDimensions;
                }

                Object.assign(filters, parsed);
            } catch (e) {
                console.error('Erro ao carregar filtros:', e);
            }
        }

        didMount = true;
        loadProducts();
    });

    // Função para atualizar filtros
    const updateFilters = (newFilters: ProductFilters) => {
        Object.assign(filters, newFilters);

        if (isBrowser) {
            window.localStorage.setItem(
                STORAGE_KEY_FILTERS,
                JSON.stringify(filters),
            );
        }

        resetAndLoad();
    };

    // Observa mudanças nos filtros
    watch(
        () => ({ ...filters }),
        () => {
            if (didMount) {
                if (isBrowser) {
                    window.localStorage.setItem(
                        STORAGE_KEY_FILTERS,
                        JSON.stringify(filters),
                    );
                }
            }
        },
        { deep: true },
    );

    // Debounce para o search (500ms)
    let searchTimeout: ReturnType<typeof setTimeout> | null = null;
    watch(searchQuery, () => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(() => {
            resetAndLoad();
        }, 500);
    });

    /**
     * Remove produto da lista após ser adicionado à shelf
     * Atualiza contador de produtos usados
     */
    const removeUsedProduct = (productId: string) => {
        const index = products.value.findIndex((p) => p.id === productId);

        if (index !== -1) {
            products.value.splice(index, 1);
            total.value--;
            usedCount.value++;
        }
    };

    /**
     * Atualiza dimensões de um produto na lista (quando editado no editor)
     * Mantém a lista sincronizada com as mudanças feitas no produto
     */
    const updateProductDimensions = (
        productId: string,
        dimensions: Partial<Pick<Product, 'width' | 'height' | 'depth'>>,
    ) => {
        const product = products.value.find((p) => p.id === productId);

        if (product) {
            Object.assign(product, dimensions);
            const w = product.width ?? 0;
            const h = product.height ?? 0;
            const d = product.depth ?? 0;
            product.has_dimensions = w > 0 && h > 0 && d > 0;
        }
    };

    return {
        // State
        products,
        searchQuery,
        filters,
        isLoading,
        isLoadingMore,
        currentPage,
        lastPage,
        total,
        usedCount,

        // Methods
        loadProducts,
        resetAndLoad,
        reloadCurrentPage,
        handleScroll,
        removeUsedProduct,
        updateFilters,
        updateProductDimensions,
    };
}
