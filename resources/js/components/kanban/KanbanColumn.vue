<script setup lang="ts">
import { Kanban } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import KanbanCard from '@/components/kanban/KanbanCard.vue';
import type { BoardColumn, Execution } from '@/components/kanban/types';
import { useT } from '@/composables/useT';

const props = defineProps<{
    column: BoardColumn;
    subdomain: string;
    currentUserId: string | null;
    isDragOver: boolean;
    draggingExecutionId: string | null;
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

const columnSearch = ref('');
const { t } = useT();

const visibleExecutions = computed(() => {
    const search = columnSearch.value.toLowerCase().trim();

    if (!search) {
        return props.column.executions;
    }

    return props.column.executions.filter((execution) =>
        (execution.gondola_name ?? '').toLowerCase().includes(search),
    );
});

const topColor = computed(() => props.column.step.color ?? '#64748b');
</script>

<template>
    <div
        class="flex h-full max-h-full w-72 shrink-0 flex-col overflow-hidden rounded-lg border bg-card transition-all"
        :class="{ 'ring-2 ring-primary/30': isDragOver }"
        :style="{ borderTopWidth: '3px', borderTopColor: topColor }"
        @dragover.prevent="emit('dragover', column.step.id)"
        @dragleave="emit('dragleave', column.step.id)"
        @drop.prevent="emit('drop', column.step.id)"
    >
        <div class="sticky top-0 z-10 space-y-2 rounded-t-lg border-b bg-card p-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <h3 class="truncate font-semibold text-foreground">
                        {{ column.step.name }}
                    </h3>
                    <p v-if="column.step.description" class="truncate text-xs text-muted-foreground">
                        {{ column.step.description }}
                    </p>
                </div>
                <span class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                    {{ column.executions.length }}
                </span>
            </div>

            <input
                v-model="columnSearch"
                type="text"
                :placeholder="t('app.kanban.filters.search_gondola_short')"
                class="h-8 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary/60 focus:ring-1 focus:ring-primary/20"
            />
        </div>

        <div class="kanban-scrollbar flex-1 space-y-2 overflow-y-auto p-2 pr-1">
            <template v-if="visibleExecutions.length > 0">
                <KanbanCard
                    v-for="execution in visibleExecutions"
                    :key="execution.id"
                    :execution="execution"
                    :subdomain="subdomain"
                    :current-user-id="currentUserId"
                    :is-dragging="draggingExecutionId === execution.id"
                    :is-busy="busyExecutionId === execution.id"
                    :status-class="statusClass(execution.status)"
                    :status-label="statusLabel(execution.status)"
                    :formatted-sla-date="formatDate(execution.sla_date)"
                    :is-overdue="isOverdue(execution)"
                    @dragstart="emit('dragstart', $event, column.step.id)"
                    @details="emit('details', $event)"
                    @start="emit('start', $event)"
                    @pause="emit('pause', $event)"
                    @resume="emit('resume', $event)"
                    @complete="emit('complete', $event)"
                    @abandon="emit('abandon', $event)"
                />
            </template>

            <div
                v-else
                class="flex h-24 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground"
            >
                <Kanban class="size-5 opacity-30" />
                <span>{{ t('app.kanban.column.empty') }}</span>
            </div>
        </div>
    </div>
</template>
