<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { FlowKanbanView } from '@flow';
import type {
  FlowKanbanBoardData,
  FlowKanbanExecution,
  FlowKanbanFilterConfig,
  FlowKanbanGroupConfig,
} from '@flow';

interface Props {
  board: FlowKanbanBoardData;
  groupConfigs?: FlowKanbanGroupConfig[] | null;
  filters?: { data: FlowKanbanFilterConfig[] | null };
  userRoles?: string[];
  currentUserId?: string | null;
  title?: string;
}

withDefaults(defineProps<Props>(), {
  groupConfigs: () => [],
  filters: () => ({ data: null }),
  title: 'Teste Flow Kanban (pacote)',
});

function handleMove(workableId: string, fromStepId: string, toStepId: string) {
  console.log('FlowTest: move', { workableId, fromStepId, toStepId });
}

function handleCardClick(execution: FlowKanbanExecution) {
  console.log('FlowTest: cardClick', execution);
}

function handleFiltersApplied(filters: Record<string, unknown>) {
  console.log('FlowTest: filters-applied', filters);
}

function handleFiltersCleared() {
  console.log('FlowTest: filters-cleared');
}
</script>

<template>
  <AppLayout>
    <Head :title="title" />

    <div class="h-[calc(100vh-8rem)] min-h-[400px] p-4">
      <FlowKanbanView
        :board="board"
        :group-configs="groupConfigs"
        :filters="filters"
        :user-roles="userRoles"
        :current-user-id="currentUserId"
        :title="title"
        description="Página de teste dos componentes Kanban do pacote laravel-raptor-flow."
        :show-filters="true"
        @move="handleMove"
        @card-click="handleCardClick"
        @filters-applied="handleFiltersApplied"
        @filters-cleared="handleFiltersCleared"
      />
    </div>
  </AppLayout>
</template>
