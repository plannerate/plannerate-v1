<!--
 * KanbanFilters — painel de filtros do Kanban.
 *
 * Segue o mesmo padrão do TableFilters do pacote:
 * - inicializa os valores a partir dos query params da URL
 * - aplica via router.get() para manter o histórico de navegação
 * - responde a mudanças externas (botão voltar do navegador)
 *
 * Diferenças em relação ao TableFilters:
 * - sem campo de busca por texto
 * - checkboxes nativos para only_overdue e show_completed
 * - aceita filterConfigs no formato SelectFilter::toArray() do backend
 -->
<script setup lang="ts">
import { Button } from '~/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import FilterRenderer from '~/components/filters/FilterRenderer.vue';
import type { KanbanFilterConfig } from '@/types/workflow';
import { X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

interface Props {
    /**
     * Configurações dos filtros do backend (SelectFilter::toArray()).
     * Cada item: { name, label, component, options: [{ value, label }] }
     */
    filterConfigs?: KanbanFilterConfig[];
    /**
     * Oculta o filtro de planograma (ex.: quando já há planograma fixo no contexto).
     */
    showPlanogramFilter?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filterConfigs: () => [],
    showPlanogramFilter: true,
});

const emit = defineEmits<{
    (e: 'apply', filters: Record<string, any>): void;
    (e: 'clear'): void;
}>();

const page = usePage();
const currentUrl = computed(() => new URL(page.url, window.location.origin));

// ── Estado interno ───────────────────────────────────────────────────────────

const filterValues = ref<Record<string, any>>({});

// ── Configs visíveis (respeita showPlanogramFilter) ──────────────────────────

const visibleFilterConfigs = computed(() => {
    if (!props.showPlanogramFilter) {
        return (props.filterConfigs ?? []).filter((f) => f.name !== 'planogram_id');
    }
    return props.filterConfigs ?? [];
}); 
// ── Inicialização a partir dos query params da URL ───────────────────────────

const initializeFromQuery = () => {
    const params = Object.fromEntries(new URLSearchParams(currentUrl.value.search));

    visibleFilterConfigs.value.forEach((filter) => {
        const v = params[filter.name];
        if (v !== undefined && v !== null && v !== '') {
            filterValues.value[filter.name] = v;
        }
    });

    // Checkboxes booleanos
    filterValues.value.only_overdue  = params.only_overdue  === 'true' || params.only_overdue  === '1';
    filterValues.value.show_completed = params.show_completed === 'true' || params.show_completed === '1';
};

initializeFromQuery();

// ── Contagem de filtros ativos (excluindo show_completed / only_overdue = false) ─

const activeFiltersCount = computed(() => {
    return Object.entries(filterValues.value).filter(([, value]) => {
        if (value === null || value === undefined || value === '') return false;
        if (value === false) return false;
        return true;
    }).length;
});

const hasActiveFilters = computed(() => activeFiltersCount.value > 0);

// ── Atualização de um filtro específico ─────────────────────────────────────

const updateFilter = (name: string, value: any) => {
    if (value === null || value === undefined || value === '') {
        delete filterValues.value[name];
    } else {
        filterValues.value[name] = value;
    }
    applyFilters();
};

// ── Aplicação: atualiza a URL e emite evento ─────────────────────────────────

const applyFilters = () => {
    const params = Object.fromEntries(new URLSearchParams(currentUrl.value.search));

    // Remove page ao filtrar (volta à página 1)
    delete params.page;

    // Injeta valores ativos
    Object.entries(filterValues.value).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '' && value !== false) {
            params[key] = String(value);
        } else {
            delete params[key];
        }
    });

    router.get(window.location.pathname, params, {
        preserveState: true,
        replace: true,
    });

    emit('apply', { ...filterValues.value });
};

// ── Limpar todos os filtros ───────────────────────────────────────────────────

const clearFilters = () => {
    const params = Object.fromEntries(new URLSearchParams(currentUrl.value.search));

    delete params.page;

    // Remove todos os campos gerenciados por este componente
    visibleFilterConfigs.value.forEach((f) => delete params[f.name]);
    delete params.only_overdue;
    delete params.show_completed;

    filterValues.value = { only_overdue: false, show_completed: false };

    router.get(window.location.pathname, params, {
        preserveState: true,
        replace: true,
    });

    emit('clear');
};

// ── Reage a mudanças externas na URL (botão voltar, etc.) ────────────────────

watch(
    () => currentUrl.value.search,
    () => initializeFromQuery(),
);
</script>

<template>
    <div
        v-if="visibleFilterConfigs.length > 0"
        class="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 px-4 py-3"
    >
        <!-- Filtros dinâmicos do backend -->
        <div
            v-for="filter in visibleFilterConfigs"
            :key="filter.name"
            :class="cn('w-44', filter.classes)"
        >
            <FilterRenderer
                :filter="filter"
                :model-value="filterValues[filter.name]"
                @update:model-value="(v: any) => updateFilter(filter.name, v)"
            />
        </div>

        <!-- Slot para filtros customizados extras -->
        <slot name="extra-filters" :filter-values="filterValues" :update-filter="updateFilter" />

        <!-- Checkboxes: only_overdue e show_completed -->
        <div class="flex flex-col justify-end gap-2 pb-0.5">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="kanban-only-overdue"
                    :checked="!!filterValues.only_overdue"
                    @update:checked="(v: boolean) => updateFilter('only_overdue', v)"
                />
                <Label for="kanban-only-overdue" class="cursor-pointer text-xs">
                    Apenas atrasadas
                </Label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox
                    id="kanban-show-completed"
                    :checked="!!filterValues.show_completed"
                    @update:checked="(v: boolean) => updateFilter('show_completed', v)"
                />
                <Label for="kanban-show-completed" class="cursor-pointer text-xs">
                    Mostrar concluídas
                </Label>
            </div>
        </div>

        <!-- Limpar filtros -->
        <div class="ml-auto flex items-end gap-2 pb-0.5">
            <Badge
                v-if="activeFiltersCount > 0"
                variant="secondary"
                class="h-5 px-1.5 text-[10px]"
            >
                {{ activeFiltersCount }}
            </Badge>
            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                type="button"
                class="h-9 gap-1.5 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                @click="clearFilters"
            >
                <X class="size-3.5" />
                Limpar filtros
            </Button>
        </div>
    </div>
</template>
