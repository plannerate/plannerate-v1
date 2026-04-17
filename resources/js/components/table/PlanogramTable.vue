<template>
    <div class="space-y-5">
        <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="min-w-0 flex-1">
                    <TableFilters
                        v-if="
                            table.filters.value.length || table.searchable.value
                        "
                        class="!mb-0 w-full"
                        :filters="table.filters.value"
                        :searchable="table.searchable.value"
                        @apply="table.filter"
                        @clear="table.reset"
                    />
                </div>
                <div class="flex shrink-0 items-start justify-end lg:pt-0.5">
                    <HeaderActions
                        v-if="table.headerActions.value?.length"
                        :actions="table.headerActions.value"
                    />
                </div>
            </div> 
        </div>

        <div class="space-y-4" v-if="table.records.value.length">
            <div
                v-for="record in table.records.value"
                :key="record.id"
                class="overflow-hidden rounded-lg border border-border bg-card shadow-sm transition-shadow hover:shadow-md"
            >
                <div class="flex flex-col lg:flex-row lg:items-stretch">
                    <div
                        class="relative h-36 w-full shrink-0 bg-muted/30 lg:h-auto lg:w-52"
                    >
                        <div
                            class="flex h-full w-full items-center justify-center text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                           <Image />
                        </div>
                    </div>

                    <div class="flex-1 p-4">
                        <div class="flex flex-col gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
                                    :class="getStatusClasses(record)"
                                >
                                    {{ getRecordStatus(record) }}
                                </span>
                                <span
                                    class="text-xs text-muted-foreground"
                                    v-if="getCategory(record)"
                                >
                                    {{ getCategory(record) }}
                                </span>
                            </div>

                            <h3 class="text-base font-bold text-foreground">
                                {{ getRecordTitle(record) }}
                            </h3>

                            <p
                                v-if="getHierarchyPath(record)"
                                class="text-sm text-muted-foreground"
                            >
                                {{ getHierarchyPath(record) }}
                            </p>
                        </div>

                        <div
                            class="mt-4 grid gap-3 border-t border-border pt-4 md:grid-cols-3"
                        >
                            <div class="space-y-2">
                                <div
                                    class="flex items-center justify-between text-xs font-semibold"
                                >
                                    <span class="text-muted-foreground"
                                        >Progresso</span
                                    >
                                    <span class="text-primary"
                                        >{{ getProgress(record) }}%</span
                                    >
                                </div>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary"
                                        :style="{
                                            width: `${getProgress(record)}%`,
                                        }"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-bold text-muted-foreground uppercase"
                                    >Data de inicio</span
                                >
                                <span
                                    class="text-sm font-semibold text-foreground"
                                    >{{ getStartDate(record) }}</span
                                >
                            </div>

                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-bold text-muted-foreground uppercase"
                                    >Prazo de termino</span
                                >
                                <span
                                    class="text-sm font-semibold text-foreground"
                                    >{{ getEndDate(record) }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="mt-4 border-t border-border pt-4"
                            v-if="getActions(record).length"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <ActionRenderer
                                    v-for="action in getActions(record)"
                                    :key="`${record.id}-${action.name}`"
                                    :action="action"
                                    :record="record"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-lg border border-border bg-card p-12 text-center text-muted-foreground shadow-sm"
        >
            Nenhum registro encontrado
        </div>

        <TablePagination
            v-if="table.meta.value.total > 0"
            :meta="table.meta.value"
            @page-change="table.page"
            @per-page-change="table.perPage"
        />
    </div>
</template>

<script setup lang="ts">
import ActionRenderer from '~/components/actions/ActionRenderer.vue';
import TableFilters from '~/components/filters/TableFilters.vue';
import HeaderActions from '~/components/table/HeaderActions.vue';
import TablePagination from '~/components/table/TablePagination.vue';
import { useInertiaTable } from '~/composables/useInertiaTable';
import Image from './Image.vue';
const props = withDefaults(
    defineProps<{
        tableKey?: string;
    }>(),
    {
        tableKey: 'table',
    },
);

const table = useInertiaTable(props.tableKey);

const getActions = (record: any) => {
    if (!record?.actions) {
        return [];
    }

    if (Array.isArray(record.actions)) {
        return record.actions.filter((action: any) => action.visible !== false);
    }

    return Object.values(record.actions).filter(
        (action: any) => action.visible !== false,
    );
};

const getRecordTitle = (record: any): string => {
    return (
        record?.title ||
        record?.name ||
        record?.label ||
        record?.id ||
        'Registro'
    );
};

const getRecordStatus = (record: any): string => {
    return record?.status || record?.state || 'Ativo';
};

const getCategory = (record: any): string => {
    return record?.category?.name || '';
};

const getHierarchyPath = (record: any): string => {
    return record?.hierarchy_path || '';
};

const normalizePercent = (value: any): number => {
    const numericValue = Number(value);
    if (!Number.isFinite(numericValue)) {
        return 0;
    }

    return Math.min(100, Math.max(0, Math.round(numericValue)));
};

const getProgress = (record: any): number => {
    return normalizePercent(
        record?.progress ?? record?.percentage ?? record?.completion,
    );
};

const formatDate = (value: string | null | undefined): string => {
    if (!value) {
        return '-';
    }
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
};

const getStartDate = (record: any): string => {
    return formatDate(record?.start_date || record?.created_at);
};

const getEndDate = (record: any): string => {
    return formatDate(record?.end_date || record?.updated_at);
};

const getStatusClasses = (record: any): string => {
    const status = String(getRecordStatus(record)).toLowerCase();

    if (status.includes('progress') || status.includes('andamento')) {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
    }

    if (status.includes('cancel')) {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400';
    }

    if (status.includes('done') || status.includes('conclu')) {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
    }

    return 'bg-muted text-muted-foreground';
};
</script>
