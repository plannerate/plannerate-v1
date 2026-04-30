<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Kanban } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import KanbanColumnExecutionsController from '@/actions/App/Http/Controllers/Tenant/KanbanColumnExecutionsController';
import KanbanCard from '@/components/kanban/KanbanCard.vue';
import type { BoardColumn, Execution } from '@/components/kanban/types';
import { useT } from '@/composables/useT';

type ColumnExecutionsResponse = {
    data: Execution[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

const props = defineProps<{
    column: BoardColumn;
    subdomain: string;
    filters: { planogram_id?: string; store_id?: string; gondola_search?: string; status?: string };
    replaceColumnExecutions: (stepIds: string[], executions: Execution[]) => void;
    appendColumnExecutions: (stepIds: string[], more: Execution[]) => void;
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
    dragstart: [execution: Execution];
    dragover: [stepId: string];
    dragleave: [stepId: string];
    drop: [column: BoardColumn];
    details: [execution: Execution];
    start: [execution: Execution];
    pause: [execution: Execution];
    resume: [execution: Execution];
    complete: [execution: Execution];
    abandon: [execution: Execution];
}>();

const columnSearch = ref('');
const { t } = useT();
const http = useHttp();

const loading = ref(false);
const loadingMore = ref(false);
const loadError = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const totalFromApi = ref<number | null>(null);

const filterSignature = computed(() => JSON.stringify(props.filters));

const displayCount = computed(() => totalFromApi.value ?? props.column.executions_count);

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

function normalizedUrl(url: string): string {
    return url.replace(/^\/\/[^/]+/, '');
}

function buildExecutionsUrl(page: number): string {
    const base = KanbanColumnExecutionsController.url(props.subdomain);
    const params = new URLSearchParams();

    for (const id of props.column.step_ids) {
        params.append('step_ids[]', id);
    }

    params.set('page', String(page));
    params.set('per_page', '20');

    if (props.filters.status) {
        params.set('status', props.filters.status);
    }

    const gondola = props.filters.gondola_search?.trim();

    if (gondola) {
        params.set('gondola_search', gondola);
    }

    return `${normalizedUrl(base)}?${params.toString()}`;
}

async function fetchPage(page: number, append: boolean): Promise<void> {
    if (append) {
        loadingMore.value = true;
    } else {
        loading.value = true;
    }

    loadError.value = false;

    try {
        const url = buildExecutionsUrl(page);
        const payload = (await http.get(url)) as ColumnExecutionsResponse;

        lastPage.value = payload.meta.last_page;
        currentPage.value = payload.meta.current_page;
        totalFromApi.value = payload.meta.total;

        if (append) {
            props.appendColumnExecutions(props.column.step_ids, payload.data);
        } else {
            props.replaceColumnExecutions(props.column.step_ids, payload.data);
        }
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function onScroll(event: Event): void {
    const el = event.target as HTMLElement;

    if (loadingMore.value || loading.value || currentPage.value >= lastPage.value) {
        return;
    }

    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
        void fetchPage(currentPage.value + 1, true);
    }
}

function retryLoad(): void {
    void fetchPage(1, false);
}

onMounted(() => {
    void fetchPage(1, false);
});

watch(filterSignature, () => {
    currentPage.value = 1;
    lastPage.value = 1;
    totalFromApi.value = null;
    void fetchPage(1, false);
});
</script>

<template>
    <div
        class="flex h-[calc(100dvh-16.5rem)] max-h-[calc(100dvh-16.5rem)] w-72 shrink-0 flex-col rounded-lg border bg-card transition-all"
        :class="{ 'ring-2 ring-primary/30': isDragOver }"
        :style="{ borderTopWidth: '3px', borderTopColor: topColor }"
        @dragover.prevent="emit('dragover', column.step.id)"
        @dragleave="emit('dragleave', column.step.id)"
        @drop.prevent="emit('drop', column)"
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
                    {{ displayCount }}
                </span>
            </div>

            <input
                v-model="columnSearch"
                type="text"
                :placeholder="t('app.kanban.filters.search_gondola_short')"
                class="h-8 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary/60 focus:ring-1 focus:ring-primary/20"
            />
        </div>

        <div
            class="kanban-column-scroll flex-1 space-y-2 overflow-y-auto p-2"
            @scroll.passive="onScroll"
        >
            <div v-if="loadError" class="rounded-md border border-destructive/40 bg-destructive/10 px-2 py-2 text-xs text-destructive">
                {{ t('app.kanban.column.load_error') }}
                <button
                    type="button"
                    class="mt-1 block font-medium underline"
                    @click="retryLoad"
                >
                    {{ t('app.kanban.column.retry') }}
                </button>
            </div>

            <template v-if="loading && column.executions.length === 0 && !loadError">
                <div
                    v-for="i in [1, 2, 3, 4]"
                    :key="i"
                    class="h-24 animate-pulse rounded-lg bg-muted/40"
                />
            </template>

            <template v-else-if="visibleExecutions.length > 0">
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
                    @dragstart="emit('dragstart', $event)"
                    @details="emit('details', $event)"
                    @start="emit('start', $event)"
                    @pause="emit('pause', $event)"
                    @resume="emit('resume', $event)"
                    @complete="emit('complete', $event)"
                    @abandon="emit('abandon', $event)"
                />
                <p v-if="loadingMore" class="py-2 text-center text-xs text-muted-foreground">
                    {{ t('app.kanban.column.loading_more') }}
                </p>
            </template>

            <div
                v-else-if="!loading"
                class="flex h-24 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground"
            >
                <Kanban class="size-5 opacity-30" />
                <span>{{ t('app.kanban.column.empty') }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.kanban-column-scroll {
    scrollbar-width: thin;
    scrollbar-color: color-mix(in oklab, var(--muted-foreground) 35%, transparent) transparent;
}

.kanban-column-scroll::-webkit-scrollbar {
    width: 6px;
}

.kanban-column-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.kanban-column-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: color-mix(in oklab, var(--muted-foreground) 30%, transparent);
}

.kanban-column-scroll::-webkit-scrollbar-thumb:hover {
    background: color-mix(in oklab, var(--muted-foreground) 45%, transparent);
}
</style>
