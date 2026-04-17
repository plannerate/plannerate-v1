<script setup lang="ts">
import KanbanFilters from '@/components/kanban/KanbanFilters.vue';
import type { KanbanFilterConfig } from '@/types/workflow';

interface Props {
    title?: string;
    description?: string;
    filterConfigs?: KanbanFilterConfig[];
    showPlanogramFilter?: boolean;
    showFilters?: boolean;
}

withDefaults(defineProps<Props>(), {
    title: 'Kanban - Workflow de Planogramas',
    description: 'Gerencie o fluxo de trabalho das góndolas',
    filterConfigs: () => [],
    showPlanogramFilter: true,
    showFilters: true,
});

const emit = defineEmits<{
    (e: 'apply', filters: Record<string, any>): void;
    (e: 'clear'): void;
}>();
</script>

<template>
    <div class="bg-card">
        <!-- Título e ações -->
        <div
            v-if="title || description"
            class="mb-2 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between items-center w-full"
        >
            <div class="space-y-1">
                <h1 v-if="title" class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <div class="flex items-center gap-2 sm:justify-end h-full">
                <slot name="actions" />
            </div>
        </div>

        <!-- Painel de filtros -->
        <KanbanFilters
            v-if="showFilters"
            :filter-configs="filterConfigs"
            :show-planogram-filter="showPlanogramFilter"
            @apply="emit('apply', $event)"
            @clear="emit('clear')"
        >
            <template v-if="$slots['extra-filters']" #extra-filters="slotProps">
                <slot name="extra-filters" v-bind="slotProps" />
            </template>
        </KanbanFilters>
    </div>
</template>
