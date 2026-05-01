<script setup lang="ts">
import { ref } from 'vue';
import ListFiltersBar from '@/components/ListFiltersBar.vue';
import ListPagination from '@/components/ListPagination.vue';
import { Badge } from '@/components/ui/badge';
import type { Paginator } from '@/types';

const filtersBarRef = ref<InstanceType<typeof ListFiltersBar> | null>(null);

defineExpose({
    submitForm: () => filtersBarRef.value?.submitForm(),
});

withDefaults(
    defineProps<{
        action: string;
        clearHref: string;
        searchName?: string;
        searchValue?: string;
        searchPlaceholder?: string;
        filterLabel?: string;
        clearLabel?: string;
        meta: Omit<Paginator<unknown>, 'data'>;
        label?: string;
        maxWidth?: string;
        perPageOptions?: number[];
        showTrashedFilter?: boolean;
        trashedValue?: 'without' | 'only' | 'with';
    }>(),
    {
        searchName: 'search',
        searchValue: '',
        maxWidth: 'md:max-w-7xl',
        perPageOptions: () => [10, 25, 50, 100],
        showTrashedFilter: true,
        trashedValue: 'without',
    },
);
</script>

<template>
    <div :class="['mx-auto w-full space-y-4', maxWidth]">
        <div class="flex items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">Total: {{ meta.total }}</Badge>
                <Badge v-if="meta.last_page > 1" variant="secondary">
                    {{ meta.current_page }} / {{ meta.last_page }}
                </Badge>
            </div>
            <slot name="action" />
        </div>

        <ListFiltersBar
            ref="filtersBarRef"
            :action="action"
            :clear-href="clearHref"
            :search-name="searchName"
            :search-value="searchValue"
            :search-placeholder="searchPlaceholder"
            :filter-label="filterLabel"
            :clear-label="clearLabel"
            :per-page="meta.per_page"
            :per-page-options="perPageOptions"
            :show-trashed-filter="showTrashedFilter"
            :trashed-value="trashedValue"
        >
            <slot name="filters" />
        </ListFiltersBar>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <slot />
        </div>

        <ListPagination :meta="meta" :label="label" />
    </div>
</template>
