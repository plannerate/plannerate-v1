import { computed, ref } from 'vue';

/**
 * Composable para gerenciar filtros, busca e ordenação em listas de análise
 */
export function useAnalysisFilters<T extends Record<string, any>>(
    results: () => T[],
    options: {
        searchFields: (keyof T)[];
        defaultSortKey: keyof T;
        defaultSortDirection?: 'asc' | 'desc';
    }
) {
    // Estado de filtros
    const searchQuery = ref('');
    const filterByClass = ref<'all' | 'A' | 'B' | 'C'>('all');

    // Configuração de ordenação
    const sortConfig = ref<{
        key: keyof T;
        direction: 'asc' | 'desc';
    }>({
        key: options.defaultSortKey,
        direction: options.defaultSortDirection || 'desc',
    });

    // Flag para prevenir múltiplas chamadas simultâneas
    let isSorting = false;

    /**
     * Estatísticas por classificação
     */
    const classStats = computed(() => {
        const items = results();
        const total = items.length;
        const classA = items.filter((r: any) => r.classificacao === 'A').length;
        const classB = items.filter((r: any) => r.classificacao === 'B').length;
        const classC = items.filter((r: any) => r.classificacao === 'C').length;

        return {
            total,
            classA,
            classB,
            classC,
        };
    });

    /**
     * Resultados filtrados e ordenados
     */
    const filteredResults = computed(() => {
        let filtered = [...results()];

        // Filtro de busca
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase();
            filtered = filtered.filter((item) =>
                options.searchFields.some((field) => {
                    const value = item[field];

                    return value && String(value).toLowerCase().includes(query);
                })
            );
        }

        // Filtro por classe
        if (filterByClass.value !== 'all') {
            filtered = filtered.filter(
                (item: any) => item.classificacao === filterByClass.value
            );
        }

        // Ordenação
        filtered.sort((a, b) => {
            const aVal = a[sortConfig.value.key];
            const bVal = b[sortConfig.value.key];

            if (typeof aVal === 'number' && typeof bVal === 'number') {
                return sortConfig.value.direction === 'asc' ? aVal - bVal : bVal - aVal;
            }

            if (typeof aVal === 'string' && typeof bVal === 'string') {
                return sortConfig.value.direction === 'asc'
                    ? aVal.localeCompare(bVal)
                    : bVal.localeCompare(aVal);
            }

            return 0;
        });

        return filtered;
    });

    /**
     * Manipula a ordenação por coluna
     */
    const handleSort = (key: string, validKeys: (keyof T)[]) => {
        // Previne múltiplas chamadas simultâneas
        if (isSorting || !key) {
return;
}

        isSorting = true;

        // Valida se a chave é válida
        if (!validKeys.includes(key as keyof T)) {
            isSorting = false;

            return;
        }

        const typedKey = key as keyof T;

        // Compara usando string para garantir que funcione
        const currentKey = String(sortConfig.value.key);
        const newKey = String(typedKey);

        // Determina nova direção
        let newDirection: 'asc' | 'desc' = 'desc';

        if (currentKey === newKey) {
            newDirection = sortConfig.value.direction === 'asc' ? 'desc' : 'asc';
        }

        // Atualiza o sortConfig de forma imutável
        sortConfig.value = {
            key: typedKey,
            direction: newDirection,
        };

        // Libera o flag após um pequeno delay
        setTimeout(() => {
            isSorting = false;
        }, 100);
    };

    /**
     * Retorna a variante do badge baseado na classificação
     */
    const getClassBadgeVariant = (classificacao: 'A' | 'B' | 'C') => {
        switch (classificacao) {
            case 'A':
                return 'default';
            case 'B':
                return 'secondary';
            case 'C':
                return 'outline';
            default:
                return 'outline';
        }
    };

    /**
     * Retorna a classe CSS para a linha baseado na classificação
     */
    const getClassRowClass = (classificacao: 'A' | 'B' | 'C', hasAlert?: boolean) => {
        if (hasAlert) {
            return 'bg-yellow-100 dark:bg-yellow-900/50';
        }

        switch (classificacao) {
            case 'A':
                return 'bg-blue-100 dark:bg-blue-900/50';
            case 'B':
                return 'bg-yellow-100 dark:bg-yellow-900/50';
            case 'C':
                return '';
            default:
                return '';
        }
    };

    return {
        // Estado
        searchQuery,
        filterByClass,
        sortConfig,

        // Computeds
        classStats,
        filteredResults,

        // Métodos
        handleSort,
        getClassBadgeVariant,
        getClassRowClass,
    };
}

