<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ListFiltersBar from '@/components/ListFiltersBar.vue';
import ListPagination from '@/components/ListPagination.vue';
import { Badge } from '@/components/ui/badge';
import type { Paginator } from '@/types';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        showHeading?: boolean;
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
        showHeading: false,
        searchName: 'search',
        searchValue: '',
        maxWidth: 'md:max-w-7xl',
    },
); 
</script>

<template>
    <div  :class="['mx-auto w-full overflow-hidden rounded border border-border/50 bg-card p-4', maxWidth]">
        <div class="flex items-start justify-between gap-4">
            <div class="space-y-2">
                <Heading v-if="showHeading" :title="title" :description="description" />
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">Total: {{ meta.total }}</Badge>
                    <Badge v-if="meta.last_page > 1" variant="secondary">
                        Pagina {{ meta.current_page }} de {{ meta.last_page }} {{  maxWidth }}
                    </Badge>
                </div>
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
