<script setup lang="ts">
import KanbanColumn from '@/components/kanban/KanbanColumn.vue';
import type { BoardColumn, Execution } from '@/components/kanban/types';

defineProps<{
    board: BoardColumn[];
    subdomain: string;
    currentUserId: string | null;
    draggingExecutionId: string | null;
    dragOverStepId: string | null;
    busyExecutionId: string | null;
    statusClass: (status: string) => string;
    statusLabel: (status: string) => string;
    formatDate: (iso: string | null) => string;
    isOverdue: (execution: Execution) => boolean;
}>();

const emit = defineEmits<{
    dragstart: [execution: Execution, stepId: string];
    dragover: [stepId: string];
    dragleave: [stepId: string];
    drop: [stepId: string];
    details: [execution: Execution];
    start: [execution: Execution];
    pause: [execution: Execution];
    resume: [execution: Execution];
    complete: [execution: Execution];
    abandon: [execution: Execution];
}>();
</script>

<template>
    <div class="flex h-full min-h-0 flex-1 overflow-x-auto overflow-y-hidden">
        <div class="flex h-full gap-3 px-4 py-3" style="min-width: max-content">
            <KanbanColumn
                v-for="column in board"
                :key="column.step.id"
                :column="column"
                :subdomain="subdomain"
                :current-user-id="currentUserId"
                :is-drag-over="dragOverStepId === column.step.id"
                :dragging-execution-id="draggingExecutionId"
                :busy-execution-id="busyExecutionId"
                :status-class="statusClass"
                :status-label="statusLabel"
                :format-date="formatDate"
                :is-overdue="isOverdue"
                @dragstart="emit('dragstart', $event, column.step.id)"
                @dragover="emit('dragover', $event)"
                @dragleave="emit('dragleave', $event)"
                @drop="emit('drop', $event)"
                @details="emit('details', $event)"
                @start="emit('start', $event)"
                @pause="emit('pause', $event)"
                @resume="emit('resume', $event)"
                @complete="emit('complete', $event)"
                @abandon="emit('abandon', $event)"
            />
        </div>
    </div>
</template>
