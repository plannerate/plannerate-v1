import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type FilterRecord = Record<string, string | number | boolean | null | undefined>;

interface UsePageFiltersOptions {
    /** Milissegundos de debounce para campos de texto. Padrão: 400 */
    debounce?: number;
    /** Preserva scroll ao aplicar filtros. Padrão: true */
    preserveScroll?: boolean;
    /** Preserva estado do Inertia ao aplicar filtros. Padrão: true */
    preserveState?: boolean;
}

/**
 * Sincroniza filtros de listagem com a URL via Inertia router.
 *
 * @example
 * const { filters, setFilter, resetFilters, activeFilterCount } = usePageFilters({
 *   search: '',
 *   status: null,
 * })
 */
export function usePageFilters<T extends FilterRecord>(
    defaults: T,
    options: UsePageFiltersOptions = {},
) {
    const { debounce: debounceMs = 400, preserveScroll = true, preserveState = true } = options;

    const filters = reactive<T>({ ...defaults });

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    function applyFilters(): void {
        // Remove entradas com valor nulo/undefined/string vazia para limpar a URL
        const params: FilterRecord = {};
        for (const key in filters) {
            const value = filters[key];
            if (value !== null && value !== undefined && value !== '') {
                params[key] = value;
            }
        }

        router.get(window.location.pathname, params, {
            preserveState,
            preserveScroll,
            replace: true,
        });
    }

    function applyWithDebounce(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(applyFilters, debounceMs);
    }

    function setFilter<K extends keyof T>(key: K, value: T[K]): void {
        (filters as T)[key] = value;
        applyWithDebounce();
    }

    function resetFilters(): void {
        Object.assign(filters, defaults);
        applyFilters();
    }

    /** Número de filtros ativos (diferentes dos valores padrão) */
    const activeFilterCount = computed<number>(() => {
        return Object.keys(filters).filter((key) => {
            const k = key as keyof T;
            return filters[k] !== defaults[k] && filters[k] !== null && filters[k] !== '';
        }).length;
    });

    return {
        filters,
        setFilter,
        applyFilters,
        resetFilters,
        activeFilterCount,
    };
}
