<script setup lang="ts">
import { computed } from 'vue';
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';
import ListFiltersBar from '@/components/ListFiltersBar.vue';
import { useT } from '@/composables/useT';

const props = defineProps<{
    planograms: Array<{ id: string; name: string; store: string | null; store_id: string | null }>;
    stores: Array<{ id: string; name: string }>;
    users: Array<{ id: string; name: string }>;
    filters: {
        planogram_id?: string;
        store_id?: string;
        gondola_search?: string;
        execution_status?: string;
        current_responsible_id?: string;
        lifecycle_status?: string;
    };
    onlyOverdue: boolean;
    showCompleted: boolean;
}>();

const emit = defineEmits<{
    'update:onlyOverdue': [value: boolean];
    'update:showCompleted': [value: boolean];
}>();

const { t } = useT();

const kanbanUrl = computed(() =>
    WorkflowKanbanController.index.url().replace(/^\/\/[^/]+/, ''),
);

const filteredPlanograms = computed(() => {
    if (!props.filters.store_id) {
        return props.planograms;
    }

    return props.planograms.filter((planogram) => planogram.store_id === props.filters.store_id);
});
</script>

<template>
    <ListFiltersBar
        :action="kanbanUrl"
        :clear-href="kanbanUrl"
        :search-value="filters.gondola_search ?? ''"
        search-name="gondola_search"
        :search-placeholder="t('app.kanban.filters.search_gondola')"
        :filter-label="t('app.kanban.filters.submit')"
        :clear-label="t('app.kanban.filters.clear')"
        :show-trashed-filter="false"
    >
        <div class="flex flex-col gap-1">
            <label for="kanban-store" class="text-xs font-medium text-foreground">
                {{ t('app.kanban.filters.store') }}
            </label>
            <select
                id="kanban-store"
                name="store_id"
                :value="filters.store_id ?? ''"
                class="h-9 min-w-36 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.kanban.filters.all_stores') }}</option>
                <option v-for="store in stores" :key="store.id" :value="store.id">
                    {{ store.name }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="kanban-planogram" class="text-xs font-medium text-foreground">
                {{ t('app.kanban.filters.planogram') }}
            </label>
            <select
                id="kanban-planogram"
                name="planogram_id"
                :value="filters.planogram_id ?? ''"
                class="h-9 min-w-56 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.kanban.filters.select_planogram') }}</option>
                <option v-for="planogram in filteredPlanograms" :key="planogram.id" :value="planogram.id">
                    {{ planogram.name }}{{ planogram.store ? ` - ${planogram.store}` : '' }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="kanban-execution-status" class="text-xs font-medium text-foreground">
                {{ t('app.kanban.filters.execution_status') }}
            </label>
            <select
                id="kanban-execution-status"
                name="execution_status"
                :value="filters.execution_status ?? ''"
                class="h-9 min-w-44 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.kanban.filters.all_statuses') }}</option>
                <option value="pending">{{ t('app.kanban.executions.status.pending') }}</option>
                <option value="active">{{ t('app.kanban.executions.status.active') }}</option>
                <option value="paused">{{ t('app.kanban.executions.status.paused') }}</option>
                <option value="completed">{{ t('app.kanban.executions.status.completed') }}</option>
                <option value="cancelled">{{ t('app.kanban.executions.status.cancelled') }}</option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="kanban-lifecycle-status" class="text-xs font-medium text-foreground">
                {{ t('app.kanban.filters.lifecycle_status') }}
            </label>
            <select
                id="kanban-lifecycle-status"
                name="lifecycle_status"
                :value="filters.lifecycle_status ?? ''"
                class="h-9 min-w-44 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.kanban.filters.all_lifecycles') }}</option>
                <option value="in_progress">{{ t('app.kanban.filters.lifecycle.in_progress') }}</option>
                <option value="completed">{{ t('app.kanban.filters.lifecycle.completed') }}</option>
                <option value="periodic_review">{{ t('app.kanban.filters.lifecycle.periodic_review') }}</option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="kanban-responsible" class="text-xs font-medium text-foreground">
                {{ t('app.kanban.filters.responsible') }}
            </label>
            <select
                id="kanban-responsible"
                name="current_responsible_id"
                :value="filters.current_responsible_id ?? ''"
                class="h-9 min-w-52 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">{{ t('app.kanban.filters.all_responsibles') }}</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                    {{ user.name }}
                </option>
            </select>
        </div>

        <details class="min-w-44 rounded-lg border border-input bg-background px-3 py-2">
            <summary class="cursor-pointer text-xs font-medium text-foreground">
                {{ t('app.kanban.filters.display_options') }}
            </summary>
            <div class="mt-2 flex flex-col gap-1.5">
                <label class="flex cursor-pointer items-center gap-2 text-xs text-foreground">
                    <input
                        type="checkbox"
                        :checked="onlyOverdue"
                        class="h-4 w-4 rounded border-input"
                        @change="emit('update:onlyOverdue', ($event.target as HTMLInputElement).checked)"
                    />
                    {{ t('app.kanban.filters.only_overdue') }}
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-xs text-foreground">
                    <input
                        type="checkbox"
                        :checked="showCompleted"
                        class="h-4 w-4 rounded border-input"
                        @change="emit('update:showCompleted', ($event.target as HTMLInputElement).checked)"
                    />
                    {{ t('app.kanban.filters.show_completed') }}
                </label>
            </div>
        </details>
    </ListFiltersBar>
</template>
