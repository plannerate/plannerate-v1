<script setup lang="ts">
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';

withDefaults(defineProps<{
    rows?: number;
    columns: number;
}>(), {
    rows: 6,
});
</script>

<template>
    <template v-for="row in rows" :key="`skeleton-row-${row}`">
        <tr class="border-t border-sidebar-border/60 dark:border-sidebar-border">
            <td
                v-for="column in columns"
                :key="`skeleton-cell-${row}-${column}`"
                class="px-4 py-3"
            >
                <Skeleton
                    class="h-4"
                    :class="column === columns ? 'ml-auto w-10' : column === 1 ? 'w-44' : 'w-28'"
                />
            </td>
        </tr>
    </template>
</template>
