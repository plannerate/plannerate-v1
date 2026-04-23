<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ListFiltersBar from '@/components/ListFiltersBar.vue';
import ListPagination from '@/components/ListPagination.vue';
import type { Paginator } from '@/types';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        action: string;
        clearHref: string;
        searchName?: string;
        searchValue?: string;
        searchPlaceholder?: string;
        filterLabel?: string;
        clearLabel?: string;
        meta: Omit<Paginator<unknown>, 'data'>;
        label?: string;
    }>(),
    {
        searchName: 'search',
        searchValue: '',
    },
);
</script>

<template>
    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading :title="title" :description="description" />
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
