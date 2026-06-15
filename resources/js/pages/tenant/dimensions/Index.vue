<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    ChevronDown,
    ExternalLink,
    Info,
    Loader2,
    Pencil,
    RefreshCcw,
    SlidersHorizontal,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import ListPage from '@/components/ListPage.vue';
import ColumnHeader from '@/components/table/columns/ColumnHeader.vue';
import ColumnStatusBadge from '@/components/table/columns/ColumnStatusBadge.vue';
import TableLoadingSkeleton from '@/components/table/TableLoadingSkeleton.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useDeferredPaginator } from '@/composables/useDeferredPaginator';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import * as DimensionApprovalController from '@/actions/App/Http/Controllers/Tenant/Products/DimensionApprovalController';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type AiStatus = 'pending' | 'researching' | 'awaiting_approval' | 'approved' | 'not_found' | 'rejected' | null;
type AiSource = 'local_similarity' | 'cosmos' | 'web_search' | 'not_found' | null;
type AiConfidence = 'high' | 'medium' | 'low' | null;

type DimensionRow = {
    id: string;
    name: string | null;
    ean: string | null;
    codigo_erp: string | null;
    width: string | number | null;
    height: string | number | null;
    depth: string | number | null;
    weight: string | number | null;
    unit: string | null;
    dimension_publish_status: 'draft' | 'published' | null;
    // Campos do pipeline AI
    ai_status: AiStatus;
    ai_status_label: string | null;
    ai_status_color: string | null;
    ai_source: AiSource;
    ai_source_url: string | null;
    ai_confidence: AiConfidence;
    ai_reasoning: string | null;
    ai_warnings: string[];
    ai_researched_at: string | null;
};

type EditingRow = {
    width: string;
    height: string;
    depth: string;
    weight: string;
    unit: string;
    dimension_publish_status: 'draft' | 'published';
};

const props = defineProps<{
    products?: Paginator<DimensionRow>;
    filters: {
        search: string;
        category_id: string;
        dimension_publish_status: string;
    };
}>();

const { t } = useT();
const { meta, rows, loading } = useDeferredPaginator(() => props.products, 20);
const listPageRef = ref<InstanceType<typeof ListPage> | null>(null);
const categoryId = ref<string | null>(props.filters.category_id ?? null);
const categoryPopoverOpen = ref(false);

watch(categoryId, (value, prev) => {
    if (value !== prev) {
        categoryPopoverOpen.value = false;
        nextTick(() => listPageRef.value?.submitForm());
    }
});

const categoryLabel = computed(() => {
    if (!categoryId.value) {
        return t('app.tenant.products.fields.category');
    }

    return `${t('app.tenant.products.fields.category')} ✓`;
});

const indexPath = `/dimensions`;
const updatePath = (id: string) => `/dimensions/${id}`;
const syncRowPath = (id: string) => `/dimensions/${id}/sync-from-reference`;
const syncPagePath = `/dimensions/sync-from-reference-page`;

const editingId = ref<string | null>(null);
const editingData = ref<EditingRow | null>(null);
const savingId = ref<string | null>(null);
const syncingRowId = ref<string | null>(null);
const syncingPage = ref(false);

function startEdit(row: DimensionRow): void {
    editingId.value = row.id;
    editingData.value = {
        width: row.width !== null ? String(row.width) : '',
        height: row.height !== null ? String(row.height) : '',
        depth: row.depth !== null ? String(row.depth) : '',
        weight: row.weight !== null ? String(row.weight) : '',
        unit: row.unit ?? 'cm',
        dimension_publish_status: row.dimension_publish_status ?? 'draft',
    };
}

function cancelEdit(): void {
    editingId.value = null;
    editingData.value = null;
}

function saveEdit(id: string): void {
    if (!editingData.value || savingId.value) {
        return;
    }

    savingId.value = id;

    router.patch(
        updatePath(id),
        { ...editingData.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
                editingData.value = null;
            },
            onFinish: () => {
                savingId.value = null;
            },
        },
    );
}

function handleKeydown(event: KeyboardEvent, id: string): void {
    if (event.key === 'Enter') {
        saveEdit(id);
    } else if (event.key === 'Escape') {
        cancelEdit();
    }
}

function syncRowFromReference(id: string): void {
    if (syncingRowId.value || syncingPage.value || savingId.value) {
        return;
    }

    syncingRowId.value = id;

    router.post(
        syncRowPath(id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncingRowId.value = null;
            },
        },
    );
}

function syncCurrentPageFromReference(): void {
    if (syncingPage.value || loading.value || rows.value.length === 0) {
        return;
    }

    syncingPage.value = true;

    router.post(
        syncPagePath,
        { product_ids: rows.value.map((row) => row.id) },
        {
            preserveScroll: true,
            onFinish: () => {
                syncingPage.value = false;
            },
        },
    );
}

/** Retorna classes CSS Tailwind para o badge de confiança da pesquisa AI. */
function confidenceClass(confidence: AiConfidence): string {
    return {
        high: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        low: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    }[confidence ?? 'low'] ?? '';
}

/** Rótulo legível para a fonte da pesquisa AI. */
function sourceLabel(source: AiSource): string {
    return {
        local_similarity: 'Banco local',
        cosmos: 'Cosmos',
        web_search: 'Web',
        not_found: '—',
    }[source ?? 'not_found'] ?? '—';
}

/** Retorna classes CSS para o badge de status do pipeline AI. */
function aiStatusClass(color: string | null): string {
    return {
        gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    }[color ?? 'gray'] ?? 'bg-gray-100 text-gray-700';
}

const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.dimensions.title'),
    title: t('app.tenant.dimensions.title'),
    description: t('app.tenant.dimensions.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.dimensions.navigation'), href: indexPath },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <template #header-actions>
            <Link :href="DimensionApprovalController.index.url()"
                class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted">
                <Check class="size-3.5 shrink-0" />
                {{ t('app.tenant.dimensions.actions.pending_approval') }}
            </Link>
            <button type="button" :disabled="syncingPage || loading || rows.length === 0"
                class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                @click="syncCurrentPageFromReference">
                <RefreshCcw class="size-3.5 shrink-0" :class="{ 'animate-spin': syncingPage }" />
                {{ syncingPage ? t('app.tenant.dimensions.actions.syncing') :
                    t('app.tenant.dimensions.actions.sync_page_from_reference') }}
            </button>
        </template>

        <Head :title="pageMeta.headTitle" />

        <ListPage ref="listPageRef" :meta="meta" :label="t('app.tenant.dimensions.product_label')" :action="indexPath"
            :clear-href="indexPath" :search-value="props.filters.search"
            :search-placeholder="t('app.tenant.common.search')" :filter-label="t('app.tenant.common.filter')"
            :clear-label="t('app.tenant.common.clear_filters')">
            <template #filters>
                <input type="hidden" name="category_id" :value="categoryId ?? ''" />

                <Popover v-model:open="categoryPopoverOpen">
                    <PopoverTrigger as-child>
                        <button type="button"
                            class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted"
                            :class="categoryId ? 'border-primary/60 text-primary' : ''">
                            <SlidersHorizontal class="size-3.5 shrink-0" />
                            <span>{{ categoryLabel }}</span>
                            <button v-if="categoryId" type="button" class="ml-1 rounded-sm opacity-60 hover:opacity-100"
                                @click.stop="categoryId = null">
                                <X class="size-3" />
                            </button>
                            <ChevronDown v-else class="size-3.5 shrink-0 opacity-50" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[90vw] max-w-170 p-4" align="start">
                        <p class="mb-3 text-sm font-medium">{{ t('app.tenant.products.form.sections.category') }}</p>
                        <CategoryCascadeSelect v-model="categoryId" />
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="rounded-md px-3 py-1.5 text-sm hover:bg-muted"
                                @click="categoryId = null; categoryPopoverOpen = false">
                                {{ t('app.tenant.common.clear_filters') }}
                            </button>
                            <button type="submit"
                                class="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground hover:bg-primary/90"
                                @click="categoryPopoverOpen = false">
                                {{ t('app.tenant.common.filter') }}
                            </button>
                        </div>
                    </PopoverContent>
                </Popover>

                <select name="dimension_publish_status" :value="filters.dimension_publish_status"
                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                    <option value="">{{ t('app.tenant.common.all') }}</option>
                    <option value="draft">{{ t('app.tenant.products.dimensions_status_options.draft') }}</option>
                    <option value="published">{{ t('app.tenant.products.dimensions_status_options.published') }}
                    </option>
                </select>
            </template>

            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <ColumnHeader field="name">{{ t('app.tenant.products.fields.name') }}</ColumnHeader>
                        <ColumnHeader field="codigo_erp">{{ t('app.tenant.products.fields.codigo_erp') }}</ColumnHeader>
                        <ColumnHeader field="ean">{{ t('app.tenant.products.fields.ean') }}</ColumnHeader>
                        <ColumnHeader field="height">{{ t('app.tenant.products.fields.height') }}</ColumnHeader>
                        <ColumnHeader field="width">{{ t('app.tenant.products.fields.width') }}</ColumnHeader>
                        <ColumnHeader field="depth">{{ t('app.tenant.products.fields.depth') }}</ColumnHeader>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.products.fields.unit') }}</th>
                        <ColumnHeader field="dimension_publish_status">{{ t('app.tenant.common.status') }}</ColumnHeader>
                        <th class="px-4 py-3 font-medium">{{ t('app.tenant.dimensions.ai_research_label') }}</th>
                        <th class="w-28 px-4 py-3 text-center font-medium">{{ t('app.tenant.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="loading">
                        <TableLoadingSkeleton :columns="10" :rows="8" />
                    </template>
                    <tr v-else-if="rows.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="10">
                            {{ t('app.tenant.common.empty') }}
                        </td>
                    </tr>
                    <tr v-for="row in rows" :key="row.id"
                        class="border-t border-sidebar-border/60 transition-colors odd:bg-transparent even:bg-muted/30 hover:bg-muted/50 dark:border-sidebar-border"
                        :class="{ 'bg-primary/5 even:bg-primary/5': editingId === row.id }">

                        <!-- Nome do produto -->
                        <td class="max-w-48 px-4 py-2">
                            <span class="block truncate" :title="row.name ?? ''">{{ row.name ?? '—' }}</span>
                        </td>

                        <td class="px-4 py-2">{{ row.codigo_erp ?? '—' }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ row.ean ?? '—' }}</td>

                        <!-- Dimensões (editável ou somente leitura) -->
                        <template v-if="editingId === row.id && editingData">
                            <td class="px-2 py-1">
                                <input v-model="editingData.height" type="number" min="0" step="0.01"
                                    class="h-8 w-20 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    :placeholder="t('app.tenant.dimensions.placeholders.height_short')"
                                    @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <input v-model="editingData.width" type="number" min="0" step="0.01"
                                    class="h-8 w-20 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    :placeholder="t('app.tenant.dimensions.placeholders.width_short')"
                                    @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <input v-model="editingData.depth" type="number" min="0" step="0.01"
                                    class="h-8 w-20 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    :placeholder="t('app.tenant.dimensions.placeholders.depth_short')"
                                    @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <input v-model="editingData.unit"
                                    class="h-8 w-16 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20"
                                    :placeholder="t('app.tenant.dimensions.placeholders.unit')" maxlength="20"
                                    @keydown="handleKeydown($event, row.id)" />
                            </td>
                            <td class="px-2 py-1">
                                <select v-model="editingData.dimension_publish_status"
                                    class="h-8 rounded border border-border bg-background px-2 text-sm focus:border-primary/60 focus:outline-none focus:ring-1 focus:ring-primary/20">
                                    <option value="draft">{{ t('app.tenant.products.dimensions_status_options.draft') }}
                                    </option>
                                    <option value="published">{{
                                        t('app.tenant.products.dimensions_status_options.published') }}</option>
                                </select>
                            </td>
                        </template>

                        <template v-else>
                            <td class="px-4 py-2">{{ row.height ?? '—' }}</td>
                            <td class="px-4 py-2">{{ row.width ?? '—' }}</td>
                            <td class="px-4 py-2">{{ row.depth ?? '—' }}</td>
                            <td class="px-4 py-2">{{ row.unit ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <ColumnStatusBadge :status="row.dimension_publish_status ?? 'draft'" />
                            </td>
                        </template>

                        <!-- Coluna de Pesquisa AI -->
                        <td class="px-4 py-2">
                            <div v-if="!row.ai_status" class="text-muted-foreground">—</div>
                            <div v-else class="flex flex-col gap-1">
                                <!-- Badge de status do pipeline AI -->
                                <div class="flex items-center gap-1.5">
                                    <Loader2 v-if="row.ai_status === 'researching'"
                                        class="size-3 shrink-0 animate-spin text-blue-500" />
                                    <span class="rounded px-1.5 py-0.5 text-xs font-medium"
                                        :class="aiStatusClass(row.ai_status_color)">
                                        {{ row.ai_status_label }}
                                    </span>
                                    <!-- Ícone de aviso quando há warnings -->
                                    <span v-if="row.ai_warnings.length > 0"
                                        :title="row.ai_warnings.join(' | ')"
                                        class="cursor-help text-amber-500">
                                        <AlertTriangle class="size-3.5" />
                                    </span>
                                </div>

                                <!-- Fonte e confiança (quando pesquisa concluída) -->
                                <div v-if="row.ai_source && row.ai_source !== 'not_found'"
                                    class="flex items-center gap-1">
                                    <span class="text-xs text-muted-foreground">{{ sourceLabel(row.ai_source) }}</span>
                                    <span v-if="row.ai_confidence"
                                        class="rounded px-1 py-px text-xs font-medium"
                                        :class="confidenceClass(row.ai_confidence)">
                                        {{ row.ai_confidence }}
                                    </span>
                                    <a v-if="row.ai_source_url" :href="row.ai_source_url" target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-muted-foreground transition hover:text-foreground"
                                        :title="row.ai_source_url">
                                        <ExternalLink class="size-3" />
                                    </a>
                                </div>

                                <!-- Raciocínio da IA (tooltip) -->
                                <div v-if="row.ai_reasoning" class="flex items-center gap-1">
                                    <button type="button"
                                        class="cursor-help text-muted-foreground hover:text-foreground"
                                        :title="row.ai_reasoning">
                                        <Info class="size-3.5" />
                                    </button>
                                    <span class="max-w-40 truncate text-xs text-muted-foreground"
                                        :title="row.ai_reasoning">
                                        {{ row.ai_reasoning }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Ações -->
                        <td class="px-4 py-2 text-center">
                            <template v-if="editingId === row.id">
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" :disabled="savingId === row.id"
                                        class="flex size-7 items-center justify-center rounded bg-primary text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                                        :title="t('app.actions.save')" @click="saveEdit(row.id)">
                                        <Loader2 v-if="savingId === row.id" class="size-3.5 animate-spin" />
                                        <Check v-else class="size-3.5" />
                                    </button>
                                    <button type="button"
                                        class="flex size-7 items-center justify-center rounded border border-border bg-background transition hover:bg-muted"
                                        :title="t('app.actions.cancel')" @click="cancelEdit">
                                        <X class="size-3.5" />
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" :disabled="syncingRowId === row.id || syncingPage"
                                        class="flex size-7 items-center justify-center rounded border border-border bg-background transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                        :title="t('app.tenant.dimensions.actions.sync_from_reference')"
                                        @click="syncRowFromReference(row.id)">
                                        <Loader2 v-if="syncingRowId === row.id" class="size-3.5 animate-spin" />
                                        <RefreshCcw v-else class="size-3.5" />
                                    </button>
                                    <button type="button"
                                        class="flex size-7 items-center justify-center rounded border border-border bg-background transition hover:bg-muted"
                                        :title="t('app.tenant.dimensions.actions.edit_dimensions')"
                                        @click="startEdit(row)">
                                        <Pencil class="size-3.5" />
                                    </button>
                                </div>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </ListPage>
    </AppLayout>
</template>
