<script setup lang="ts">
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import ListFiltersBar from '@/components/ListFiltersBar.vue';

type FilterOption = {
    value: string;
    label: string;
};

defineProps<{
    tenantId: string;
    filters: { search: string; status: string; trashed: 'without' | 'only' | 'with' };
    statusOptions: FilterOption[];
    usersFrom: number | null;
    usersTo: number | null;
    usersTotal: number;
    filterLabel: string;
    clearLabel: string;
}>();
</script>

<template>
    <ListFiltersBar
        :action="TenantUserAccessController.edit.url(tenantId)"
        :clear-href="TenantUserAccessController.edit.url(tenantId)"
        search-name="search"
        :search-value="filters.search"
        search-placeholder="Buscar por nome, email ou perfil..."
        :filter-label="filterLabel"
        :clear-label="clearLabel"
        :total="usersTotal"
        total-label="usuário"
        :show-trashed-filter="true"
        :trashed-value="filters.trashed"
    >
        <select
            name="status"
            class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
        >
            <option
                v-for="option in statusOptions"
                :key="option.value"
                :value="option.value"
                :selected="option.value === filters.status"
            >
                {{ option.label }}
            </option>
        </select>
    </ListFiltersBar>
</template>
