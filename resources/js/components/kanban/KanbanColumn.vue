<script setup lang="ts">
import { computed, ref } from 'vue';
import { Kanban } from 'lucide-vue-next';
import type { BoardColumn } from '@/components/kanban/types';

const props = defineProps<{
    column: BoardColumn;
}>();

const columnSearch = ref('');

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
        class="flex h-full w-72 shrink-0 flex-col rounded-lg border bg-card transition-all"
        :style="{ borderTopWidth: '3px', borderTopColor: topColor }"
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
                placeholder="Buscar gondola"
                class="h-8 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary/60 focus:ring-1 focus:ring-primary/20"
            />
        </div>

        <div class="flex-1 space-y-2 overflow-y-auto p-2">
            <template v-if="visibleExecutions.length > 0">
                <div
                    v-for="execution in visibleExecutions"
                    :key="execution.id"
                    class="rounded-lg border border-border bg-background p-3 text-sm shadow-sm"
                >
                    <p class="truncate font-medium text-foreground">
                        {{ execution.gondola_name ?? '-' }}
                    </p>
                    <p v-if="execution.gondola_location" class="truncate text-xs text-muted-foreground">
                        {{ execution.gondola_location }}
                    </p>
                </div>
            </template>

            <div
                v-else
                class="flex h-24 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground"
            >
                <Kanban class="size-5 opacity-30" />
                <span>Nenhuma gondola encontrada</span>
            </div>
        </div>
    </div>
</template>
