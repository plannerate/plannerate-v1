<script setup lang="ts">
import ListFiltersBar from '@/components/ListFiltersBar.vue';
import ListPagination from '@/components/ListPagination.vue';
import { Badge } from '@/components/ui/badge';
import type { Paginator } from '@/types';

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
    }>(),
    {
        searchName: 'search',
        searchValue: '',
        maxWidth: 'md:max-w-7xl',
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
            :action="action"
            :clear-href="clearHref"
            :search-name="searchName"
            :search-value="searchValue"
            :search-placeholder="searchPlaceholder"
            :filter-label="filterLabel"
            :clear-label="clearLabel"
        >
            <slot name="filters" />
        </ListFiltersBar>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <slot />
        </div>

        <ListPagination :meta="meta" :label="label" />
    </div>
</template>
