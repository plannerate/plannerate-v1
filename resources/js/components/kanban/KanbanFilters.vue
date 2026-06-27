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

        <!-- Concluídos ficam ocultos por padrão; marcar exibe-os (lifecycle_status=completed). -->
        <div class="flex flex-col justify-end gap-1">
            <label
                for="kanban-show-completed"
                class="flex h-9 cursor-pointer items-center gap-2 rounded-lg border border-input bg-background px-3 text-sm text-foreground"
            >
                <input
                    id="kanban-show-completed"
                    type="checkbox"
                    name="lifecycle_status"
                    value="completed"
                    :checked="filters.lifecycle_status === 'completed'"
                    class="size-4 rounded border-input text-primary focus:ring-primary/30"
                />
                {{ t('app.kanban.filters.show_completed') }}
            </label>
        </div>
    </ListFiltersBar>
</template>
