import { computed, toValue, type ComputedRef, type MaybeRefOrGetter } from 'vue';
import type { Paginator } from '@/types';

type DeferredPaginatorState<T> = {
    meta: ComputedRef<Omit<Paginator<T>, 'data'>>;
    rows: ComputedRef<T[]>;
    loading: ComputedRef<boolean>;
};

export function useDeferredPaginator<T>(
    paginator: MaybeRefOrGetter<Paginator<T> | undefined>,
    defaultPerPage = 10,
): DeferredPaginatorState<T> {
    const fallbackMeta: Omit<Paginator<T>, 'data'> = {
        links: [],
        from: null,
        to: null,
        total: 0,
        current_page: 1,
        last_page: 1,
        per_page: defaultPerPage,
    };

    const loading = computed(() => toValue(paginator) === undefined);
    const rows = computed(() => toValue(paginator)?.data ?? []);
    const meta = computed(() => toValue(paginator) ?? fallbackMeta);

    return { meta, rows, loading };
}
