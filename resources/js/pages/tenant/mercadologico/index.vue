<script setup lang="ts">
import MercadologicoDetailPanel from '@/components/mercadologico/MercadologicoDetailPanel.vue';
import MercadologicoKanban from '@/components/mercadologico/MercadologicoKanban.vue';
import MercadologicoPanelTree from '@/components/mercadologico/MercadologicoPanelTree.vue';
import type { BackendBreadcrumb } from '~/composables/useBreadcrumbs';
import ResourceLayout from '~/layouts/ResourceLayout.vue';
import type { CategoryNode, HierarchyLevelNames } from '@/composables/useMercadologicoTree';
import {
    getRootId,
    findNodeById,
    getAncestorIds,
    getPathNames,
} from '@/composables/useMercadologicoTree';
import { router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import MercadologicoReorganizeChat from '@/components/mercadologico/MercadologicoReorganizeChat.vue';
import MercadologicoProductsModal from '@/components/mercadologico/MercadologicoProductsModal.vue';
import { MessageSquare } from 'lucide-vue-next';

export interface CategoryUsage {
    children_count: number;
    products_count: number;
    planograms_count: number;
}

interface ReorganizeLogItem {
    id: number;
    status: string;
    applied_at?: string | null;
    created_at: string;
    agent_response?: {
        renames?: Array<{ category_id: string; new_name: string }>;
        merges?: Array<{ keep_id: string; remove_id: string }>;
        reasoning?: string;
    };
}

interface Props {
    title?: string;
    categories: CategoryNode[];
    expand?: string[];
    selected?: string;
    hierarchy_level_names?: HierarchyLevelNames;
    move_url: string;
    destroy_url: string;
    store_url: string;
    duplicate_url: string;
    update_url: string;
    reorganize_url: string;
    reorganize_apply_url: string;
    reorganize_restore_url: string;
    products_url: string;
    products_move_url: string;
    reorganize_logs?: ReorganizeLogItem[];
    reorganize_log?: ReorganizeLogItem | null;
    message?: string;
    resourceLabel?: string;
    resourcePluralLabel?: string;
    breadcrumbs?: BackendBreadcrumb[];
    stores?: unknown[];
}

const props = withDefaults(defineProps<Props>(), {
    expand: () => [],
    selected: undefined,
});

const layoutProps = {
    message: props.message,
    resourceLabel: props.resourceLabel,
    resourcePluralLabel: props.resourcePluralLabel,
    breadcrumbs: props.breadcrumbs,
};

const searchQuery = ref('');
const selectedId = ref<string | null>(props.selected ?? null);

const localExpandIds = ref<string[]>([...(props.expand ?? [])]);

watch(
    () => props.expand,
    (next) => {
        localExpandIds.value = [...(next ?? [])];
    },
);

watch(
    () => props.selected,
    (next) => {
        selectedId.value = next ?? null;
    },
);

const selectedNode = computed(() => {
    return selectedId.value ? findNodeById(props.categories, selectedId.value) ?? null : null;
});

const usage = computed((): CategoryUsage | null => {
    const node = selectedNode.value;
    if (!node) return null;
    return {
        children_count: node.children_count ?? node.children?.length ?? 0,
        products_count: node.products_count ?? 0,
        planograms_count: node.planograms_count ?? 0,
    };
});

const activeRootIds = computed(() => {
    const ids = [...localExpandIds.value];
    const seen = new Set<string>();
    const ordered: string[] = [];

    for (const id of ids) {
        const rootId = getRootId(props.categories, id);
        if (!rootId || seen.has(rootId)) {
            continue;
        }

        seen.add(rootId);
        ordered.push(rootId);
    }

    return ordered;
});

const rootColorIndex = computed(() => {
    return Object.fromEntries(activeRootIds.value.map((rootId, index) => [rootId, index]));
});

const breadcrumbText = computed(() => {
    if (!selectedId.value) return 'Selecione uma categoria na árvore';
    const node = findNodeById(props.categories, selectedId.value);
    if (!node) return '—';
    const path = getPathNames(props.categories, node.id);
    return path ? ['Mercadológico', ...path].join(' / ') : node.name;
});

function syncUrlState() {
    const params = new URLSearchParams();
    if (localExpandIds.value.length > 0) {
        params.set('expand', localExpandIds.value.join(','));
    }
    if (selectedId.value) {
        params.set('selected', selectedId.value);
    }
    const qs = params.toString();
    const url = window.location.pathname + (qs ? '?' + qs : '');
    window.history.replaceState({}, '', url);
}

function redirectQuery(): { expand?: string; selected?: string } {
    const q: { expand?: string; selected?: string } = {};
    if (localExpandIds.value.length) q.expand = localExpandIds.value.join(',');
    if (selectedId.value) q.selected = selectedId.value;
    return q;
}

function handleSelect(node: CategoryNode) {
    selectedId.value = node.id;
    const ancestors = getAncestorIds(props.categories, node.id) ?? [];
    const nextExpand = [...new Set([...localExpandIds.value, ...ancestors, node.id])];

    const expandChanged = nextExpand.length !== localExpandIds.value.length
        || nextExpand.some((id) => !localExpandIds.value.includes(id));

    localExpandIds.value = nextExpand;

    if (expandChanged) {
        const query: Record<string, string> = { selected: node.id };
        if (nextExpand.length) query.expand = nextExpand.join(',');
        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            only: ['categories', 'expand'],
        });
    } else {
        syncUrlState();
    }
}

function handleExpandToggle(nodeId: string, expanded: boolean) {
    const next = expanded
        ? [...new Set([...localExpandIds.value, nodeId])]
        : localExpandIds.value.filter((id) => id !== nodeId);
    localExpandIds.value = next;

    const query: Record<string, string> = {};
    if (next.length) query.expand = next.join(',');
    if (selectedId.value) query.selected = selectedId.value;
    router.get(window.location.pathname, query, {
        preserveState: true,
        preserveScroll: true,
        only: ['categories', 'expand'],
    });
}

function handleClear() {
    selectedId.value = null;
    syncUrlState();
}

function handleMoved(categoryId: string, newParentId: string | null) {
    router.patch(props.move_url, {
        id: categoryId,
        category_id: newParentId,
        ...redirectQuery(),
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function handleCategoryDeleted() {
    selectedId.value = null;
}

function handleCategoryCreated() {
    // tree reloaded via redirect
}

function handleCategoryDuplicated() {
    // tree reloaded via redirect
}

function handleCategoryUpdated() {
    // tree reloaded via redirect
}

const reorganizing = ref(false);
const drawerOpen = ref(false);

interface ProductModalWindow {
    categoryId: string;
    categoryName: string;
    hierarchyPath: string;
    x: number;
    y: number;
}
const productModalWindows = ref<ProductModalWindow[]>([]);
const productsRefreshTrigger = ref(0);

const DEFAULT_MODAL_OFFSET = 40;

function getCategoryHierarchyPath(node: CategoryNode): string {
    if (node.full_path && typeof node.full_path === 'string') return node.full_path;
    const pathNames = getPathNames(props.categories, node.id);
    return pathNames?.join(' / ') ?? node.name ?? '';
}

function handleOpenProducts() {
    const node = selectedNode.value;
    if (!node) return;
    const exists = productModalWindows.value.some((w) => w.categoryId === node.id);
    if (exists) return;
    const n = productModalWindows.value.length;
    productModalWindows.value = [
        ...productModalWindows.value,
        {
            categoryId: node.id,
            categoryName: node.name ?? '',
            hierarchyPath: getCategoryHierarchyPath(node),
            x: 120 + n * DEFAULT_MODAL_OFFSET,
            y: 80 + n * DEFAULT_MODAL_OFFSET,
        },
    ];
}

function closeProductModal(categoryId: string) {
    productModalWindows.value = productModalWindows.value.filter((w) => w.categoryId !== categoryId);
}

function updateProductModalPosition(categoryId: string, x: number, y: number) {
    productModalWindows.value = productModalWindows.value.map((w) =>
        w.categoryId === categoryId ? { ...w, x, y } : w,
    );
}

function handleDropProducts(targetCategoryId: string, productIds: string[]) {
    router.patch(props.products_move_url, {
        product_ids: productIds,
        category_id: targetCategoryId,
        ...redirectQuery(),
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            productsRefreshTrigger.value += 1;
        },
    });
}

watch(
    () => props.reorganize_log,
    (log) => {
        if (log) {
            drawerOpen.value = true;
        }
    },
    { immediate: true },
);

function handleReorganizeWithAi() {
    reorganizing.value = true;
    router.post(props.reorganize_url, redirectQuery(), {
        preserveScroll: true,
        onFinish: () => {
            reorganizing.value = false;
        },
    });
}

function handleChatApplied() {
    router.reload({ only: ['categories', 'reorganize_logs'] });
}

function handleChatRestored() {
    router.reload({ only: ['categories', 'reorganize_logs'] });
}

const displayLogs = computed(() => props.reorganize_logs ?? []);
</script>

<template>
    <ResourceLayout
        v-bind="layoutProps"
        :title="title ?? 'Mercadológico'"
        :max-width="'full'"
    >
        <template #content>
            <div class="grid min-h-[420px] grid-cols-[280px_1fr] gap-0">
                <!-- Painel esquerdo: árvore + busca -->
                <MercadologicoPanelTree
                    :categories="categories"
                    :selected-id="selectedId"
                    :expand-ids="localExpandIds"
                    :root-color-index="rootColorIndex"
                    :search-query="searchQuery"
                    :has-selection="selectedId !== null"
                    @select="handleSelect"
                    @expand-toggle="handleExpandToggle"
                    @update:search-query="searchQuery = $event"
                    @clear="handleClear"
                    @drop-products="handleDropProducts"
                />

                <!-- Main: toolbar + conteúdo + detalhes -->
                <div class="flex flex-col overflow-hidden">
                    <div class="toolbar flex items-center gap-3 border-b border-border bg-muted/20 px-6 py-3">
                        <div class="breadcrumb min-w-0 flex-1 truncate font-mono text-[11px] text-muted-foreground">
                            {{ breadcrumbText }}
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow transition-opacity hover:opacity-90 disabled:pointer-events-none disabled:opacity-50"
                            :disabled="reorganizing"
                            @click="handleReorganizeWithAi"
                        >
                            <span v-if="reorganizing">Reorganizando…</span>
                            <span v-else>Reorganizar categorias com IA</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-muted/50"
                            :class="{ 'ring-2 ring-primary': drawerOpen }"
                            title="Ver sugestões e conversas"
                            @click="drawerOpen = true"
                        >
                            <MessageSquare class="size-3.5" />
                            Sugestões ({{ displayLogs.length }})
                        </button>
                    </div>

                    <div class="flex overflow-hidden">
                        <div class="flex min-w-0 flex-1 flex-col">
                            <MercadologicoKanban
                                :categories="categories"
                                :move-url="move_url"
                                :selected-id="selectedId"
                                :root-color-index="rootColorIndex"
                                :hierarchy-level-names="hierarchy_level_names"
                                @moved="handleMoved"
                                @select="handleSelect"
                            />
                        </div>

                        <!-- Painel de detalhes -->
                        <MercadologicoDetailPanel
                            :categories="categories"
                            :selected="selectedNode"
                            :selected-count="selectedId !== null ? 1 : 0"
                            :usage="usage"
                            :hierarchy-level-names="hierarchy_level_names"
                            :destroy-url="destroy_url"
                            :store-url="store_url"
                            :duplicate-url="duplicate_url"
                            :update-url="update_url"
                            :redirect-expand="localExpandIds.join(',')"
                            :redirect-selected="selectedId ?? ''"
                            @deleted="handleCategoryDeleted"
                            @created="handleCategoryCreated"
                            @duplicated="handleCategoryDuplicated"
                            @updated="handleCategoryUpdated"
                            @open-products="handleOpenProducts"
                        />
                    </div>
                </div>
            </div>

            <!-- Janelas flutuantes: produtos por categoria (arrastar para árvore ou para outra janela) -->
            <MercadologicoProductsModal
                v-for="win in productModalWindows"
                :key="win.categoryId"
                :open="true"
                :category-id="win.categoryId"
                :category-name="win.categoryName"
                :category-hierarchy-path="win.hierarchyPath"
                :products-url="products_url"
                :refresh-trigger="productsRefreshTrigger"
                :x="win.x"
                :y="win.y"
                @update:open="() => closeProductModal(win.categoryId)"
                @update:position="(x, y) => updateProductModalPosition(win.categoryId, x, y)"
                @drop-products="(ids) => handleDropProducts(win.categoryId, ids)"
            />

            <!-- Chat / sugestões da IA (drawer à direita) -->
            <MercadologicoReorganizeChat
                v-model:open="drawerOpen"
                :logs="reorganize_logs ?? []"
                :initial-log="reorganize_log ?? null"
                :apply-url="reorganize_apply_url"
                :restore-url="reorganize_restore_url"
                :redirect-expand="localExpandIds.join(',')"
                :redirect-selected="selectedId ?? ''"
                @applied="handleChatApplied"
                @restored="handleChatRestored"
            />
        </template>
    </ResourceLayout>
</template>
