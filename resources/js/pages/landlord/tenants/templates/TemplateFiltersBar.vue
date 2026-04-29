<script setup lang="ts">
import WorkflowTemplateController from '@/actions/App/Http/Controllers/Landlord/WorkflowTemplateController';
import ListFiltersBar from '@/components/ListFiltersBar.vue';

defineProps<{
    tenantId: string;
    filters: { search: string; status: string };
    filterLabel: string;
    clearLabel: string;
    total: number;
}>();

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'draft', label: 'Rascunho' },
    { value: 'published', label: 'Publicado' },
];
</script>

<template>
    <ListFiltersBar
        :action="WorkflowTemplateController.index.url(tenantId).replace(/^\/\/[^/]+/, '')"
        :clear-href="WorkflowTemplateController.index.url(tenantId).replace(/^\/\/[^/]+/, '')"
        search-name="search"
        :search-value="filters.search"
        placeholder="Buscar por nome..."
        :filter-label="filterLabel"
        :clear-label="clearLabel"
        :total="total"
        total-label="etapa"
    >
        <select
            name="status"
            :value="filters.status"
            class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
        >
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </ListFiltersBar>
</template>
