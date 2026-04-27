<script setup lang="ts">
import { computed } from 'vue';
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';
import ListFiltersBar from '@/components/ListFiltersBar.vue';

const props = defineProps<{
    subdomain: string;
    planograms: Array<{ id: string; name: string; store: string | null; store_id: string | null }>;
    stores: Array<{ id: string; name: string }>;
    filters: { planogram_id?: string; store_id?: string; gondola_search?: string };
    onlyOverdue: boolean;
    showCompleted: boolean;
}>();

const emit = defineEmits<{
    'update:onlyOverdue': [value: boolean];
    'update:showCompleted': [value: boolean];
}>();

const kanbanUrl = computed(() =>
    WorkflowKanbanController.index.url(props.subdomain).replace(/^\/\/[^/]+/, ''),
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
        search-placeholder="Buscar gondola..."
        filter-label="Filtrar"
        clear-label="Limpar"
    >
        <div class="flex flex-col gap-1">
            <label for="kanban-store" class="text-xs font-medium text-foreground">Loja</label>
            <select
                id="kanban-store"
                name="store_id"
                :value="filters.store_id ?? ''"
                class="h-9 min-w-36 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">Todas</option>
                <option v-for="store in stores" :key="store.id" :value="store.id">
                    {{ store.name }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="kanban-planogram" class="text-xs font-medium text-foreground">Planograma</label>
            <select
                id="kanban-planogram"
                name="planogram_id"
                :value="filters.planogram_id ?? ''"
                class="h-9 min-w-56 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">Selecione um planograma</option>
                <option v-for="planogram in filteredPlanograms" :key="planogram.id" :value="planogram.id">
                    {{ planogram.name }}{{ planogram.store ? ` - ${planogram.store}` : '' }}
                </option>
            </select>
        </div>

        <div class="flex flex-col justify-end gap-1.5 pb-0.5">
            <label class="flex cursor-pointer items-center gap-2 text-xs text-foreground">
                <input
                    type="checkbox"
                    :checked="onlyOverdue"
                    class="h-4 w-4 rounded border-input"
                    @change="emit('update:onlyOverdue', ($event.target as HTMLInputElement).checked)"
                />
                Apenas atrasadas
            </label>
            <label class="flex cursor-pointer items-center gap-2 text-xs text-foreground">
                <input
                    type="checkbox"
                    :checked="showCompleted"
                    class="h-4 w-4 rounded border-input"
                    @change="emit('update:showCompleted', ($event.target as HTMLInputElement).checked)"
                />
                Mostrar concluidas
            </label>
        </div>
    </ListFiltersBar>
</template>
