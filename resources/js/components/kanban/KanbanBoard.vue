<script setup lang="ts">
import KanbanColumn from '@/components/kanban/KanbanColumn.vue';
import type { BoardColumn } from '@/components/kanban/types';

defineProps<{
    board: BoardColumn[];
}>();
</script>

<template>
    <div class="flex-1 overflow-x-auto overflow-y-hidden">
        <div class="flex h-full gap-3 px-4 py-3" style="min-width: max-content">
            <KanbanColumn v-for="column in board" :key="column.step.id" :column="column" />
        </div>
    </div>
</template>
