import { computed, ref } from 'vue';

/**
 * Gerencia seleção de linhas em listagens para bulk actions.
 *
 * @param items  Array reativo de itens da listagem
 * @param key    Campo usado como identificador único. Padrão: 'id'
 *
 * @example
 * const { selected, toggle, toggleAll, clear, isSelected, hasSelection } = useSelection(products)
 */
export function useSelection<T extends Record<string, unknown>>(
    items: T[] | (() => T[]),
    key: keyof T = 'id' as keyof T,
) {
    const selectedKeys = ref<Set<unknown>>(new Set());

    function getItems(): T[] {
        return typeof items === 'function' ? items() : items;
    }

    function toggle(item: T): void {
        const k = item[key];
        if (selectedKeys.value.has(k)) {
            selectedKeys.value.delete(k);
        } else {
            selectedKeys.value.add(k);
        }
    }

    function toggleAll(): void {
        const all = getItems();
        if (selectedKeys.value.size === all.length) {
            selectedKeys.value.clear();
        } else {
            for (const item of all) {
                selectedKeys.value.add(item[key]);
            }
        }
    }

    function clear(): void {
        selectedKeys.value.clear();
    }

    function isSelected(item: T): boolean {
        return selectedKeys.value.has(item[key]);
    }

    /** Itens atualmente selecionados */
    const selected = computed<T[]>(() =>
        getItems().filter((item) => selectedKeys.value.has(item[key])),
    );

    /** true quando há pelo menos um item selecionado */
    const hasSelection = computed<boolean>(() => selectedKeys.value.size > 0);

    /** true quando todos os itens da página estão selecionados */
    const allSelected = computed<boolean>(() => {
        const all = getItems();
        return all.length > 0 && selectedKeys.value.size === all.length;
    });

    /** true quando há seleção parcial (indeterminate) */
    const indeterminate = computed<boolean>(
        () => hasSelection.value && !allSelected.value,
    );

    return {
        selected,
        hasSelection,
        allSelected,
        indeterminate,
        toggle,
        toggleAll,
        clear,
        isSelected,
    };
}
