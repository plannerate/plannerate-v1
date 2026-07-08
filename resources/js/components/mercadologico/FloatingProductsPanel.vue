<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { useDraggable, watchDebounced } from '@vueuse/core';
import { ArrowRightLeft, GripHorizontal, PackageOpen, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';

import type { MercadologicoUrls, OpenModal, ProductRow } from './types';

/** MIME custom do payload de produtos arrastados entre painéis. */
const DRAG_MIME = 'application/x-mercadologico-products';

type DragPayload = { from: string; ids: string[] };

type ProductsResponse = {
    category: { id: string; name: string; full_path: string | null };
    products: {
        data: ProductRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

const props = defineProps<{
    urls: MercadologicoUrls;
    category: OpenModal;
    /** Outras categorias abertas — destinos possíveis para mover produtos. */
    otherCategories: OpenModal[];
    /** Posição inicial (viewport) do painel flutuante. */
    initialPosition: { x: number; y: number };
}>();

const emit = defineEmits<{
    close: [];
    moved: [payload: { from: string; to: string; count: number }];
}>();

const { t } = useT();

// ── Janela flutuante arrastável (pelo cabeçalho) ──────────
const panelRef = ref<HTMLElement | null>(null);
const handleRef = ref<HTMLElement | null>(null);
const { x, y } = useDraggable(panelRef, {
    initialValue: props.initialPosition,
    handle: handleRef,
    preventDefault: true,
});

// ── Produtos ──────────────────────────────────────────────
const http = useHttp<Record<string, string>, ProductsResponse>();

const products = ref<ProductRow[]>([]);
const fullPath = ref<string | null>(null);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const search = ref('');
const selectedIds = ref<Set<string>>(new Set());
const targetId = ref('');
const moving = ref(false);
const isDropActive = ref(false);

const hasMore = computed(() => page.value < lastPage.value);
const selectedCount = computed(() => selectedIds.value.size);
const allVisibleSelected = computed(
    () =>
        products.value.length > 0 &&
        products.value.every((product) => selectedIds.value.has(product.id)),
);
const canMove = computed(
    () => selectedCount.value > 0 && targetId.value !== '' && !moving.value,
);

/**
 * Carrega uma página de produtos da categoria (append quando paginando).
 */
async function fetchProducts(append = false): Promise<void> {
    loading.value = true;

    const url = new URL(
        props.urls.products(props.category.categoryId),
        window.location.origin,
    );
    url.searchParams.set('page', String(page.value));

    if (search.value.trim() !== '') {
        url.searchParams.set('search', search.value.trim());
    }

    try {
        await http.get(url.toString());
        const payload = http.response;

        if (!payload) {
            return;
        }

        fullPath.value = payload.category.full_path;
        total.value = payload.products.total;
        lastPage.value = payload.products.last_page;
        products.value = append
            ? [...products.value, ...payload.products.data]
            : payload.products.data;
    } finally {
        loading.value = false;
    }
}

/** Recarrega a lista do zero — chamado pelo manager após um move. */
function refetch(): void {
    page.value = 1;
    selectedIds.value = new Set();
    void fetchProducts();
}

defineExpose({ refetch });

function toggle(id: string): void {
    const next = new Set(selectedIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selectedIds.value = next;
}

function toggleAll(): void {
    if (allVisibleSelected.value) {
        selectedIds.value = new Set();

        return;
    }

    selectedIds.value = new Set(products.value.map((product) => product.id));
}

async function loadMore(): Promise<void> {
    if (!hasMore.value || loading.value) {
        return;
    }

    page.value += 1;
    await fetchProducts(true);
}

/**
 * Dispara o POST de movimentação. A atualização das listas de origem e destino
 * é feita pelo manager (via `moved` → `refetch`), evitando dupla busca aqui.
 */
function performMove(ids: string[], from: string, to: string): void {
    if (ids.length === 0 || from === to) {
        return;
    }

    moving.value = true;

    router.post(
        props.urls.moveProducts(),
        { product_ids: ids, target_category_id: to },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('moved', { from, to, count: ids.length });
            },
            onFinish: () => {
                moving.value = false;
            },
        },
    );
}

/** Move os selecionados para a categoria escolhida no rodapé. */
function moveSelected(): void {
    if (!canMove.value) {
        return;
    }

    const ids = Array.from(selectedIds.value);
    const to = targetId.value;
    targetId.value = '';
    performMove(ids, props.category.categoryId, to);
}

// ── Drag de produtos entre painéis ────────────────────────
function onProductDragStart(event: DragEvent, product: ProductRow): void {
    // Se o produto arrastado faz parte da seleção, arrasta a seleção inteira.
    const ids =
        selectedIds.value.has(product.id) && selectedIds.value.size > 0
            ? Array.from(selectedIds.value)
            : [product.id];

    const payload: DragPayload = { from: props.category.categoryId, ids };
    event.dataTransfer?.setData(DRAG_MIME, JSON.stringify(payload));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onPanelDragOver(event: DragEvent): void {
    if (event.dataTransfer?.types.includes(DRAG_MIME)) {
        event.preventDefault();
        isDropActive.value = true;
    }
}

function onPanelDragLeave(event: DragEvent): void {
    // Só desliga o realce ao sair de fato do painel (não ao passar por filhos).
    if (!event.currentTarget || !(event.relatedTarget instanceof Node)) {
        isDropActive.value = false;

        return;
    }

    if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget)) {
        isDropActive.value = false;
    }
}

function onPanelDrop(event: DragEvent): void {
    isDropActive.value = false;

    const raw = event.dataTransfer?.getData(DRAG_MIME);

    if (!raw) {
        return;
    }

    let payload: DragPayload;

    try {
        payload = JSON.parse(raw) as DragPayload;
    } catch {
        return;
    }

    if (!payload?.ids?.length || payload.from === props.category.categoryId) {
        return;
    }

    event.preventDefault();
    performMove(payload.ids, payload.from, props.category.categoryId);
}

watchDebounced(
    search,
    () => {
        page.value = 1;
        void fetchProducts();
    },
    { debounce: 300 },
);

onMounted(() => {
    void fetchProducts();
});
</script>

<template>
    <div
        ref="panelRef"
        class="fixed z-50 flex max-h-[80vh] w-[min(92vw,30rem)] flex-col overflow-hidden rounded-xl border bg-background shadow-2xl transition-shadow"
        :class="{ 'ring-2 ring-primary/60': isDropActive }"
        :style="{ left: `${x}px`, top: `${y}px` }"
        @dragover="onPanelDragOver"
        @dragleave="onPanelDragLeave"
        @drop="onPanelDrop"
    >
        <!-- Cabeçalho (arrasta a janela) -->
        <div
            ref="handleRef"
            class="flex cursor-move items-center gap-2 border-b bg-muted/30 px-4 py-3 select-none"
        >
            <GripHorizontal class="size-4 shrink-0 text-muted-foreground" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">
                    {{ t('app.landlord.mercadologico.products.modal_title', { name: category.categoryName }) }}
                </p>
                <p v-if="fullPath" class="truncate text-xs text-muted-foreground">
                    {{ fullPath }}
                </p>
            </div>
            <button
                type="button"
                class="flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                :aria-label="t('app.landlord.mercadologico.products.close')"
                @click="emit('close')"
            >
                <X class="size-4" />
            </button>
        </div>

        <!-- Busca -->
        <div class="border-b px-4 py-3">
            <div class="relative">
                <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="search"
                    type="text"
                    :placeholder="t('app.landlord.mercadologico.products.search')"
                    class="h-9 w-full rounded-lg border border-input bg-background pr-3 pl-8 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                />
            </div>

            <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                <label class="flex cursor-pointer items-center gap-2">
                    <Checkbox
                        :model-value="allVisibleSelected"
                        @update:model-value="toggleAll"
                    />
                    <span>{{ t('app.landlord.mercadologico.products.selected', { count: String(selectedCount) }) }}</span>
                </label>
                <span>{{ total }}</span>
            </div>
        </div>

        <!-- Lista (rola com altura própria) -->
        <div class="max-h-[45vh] flex-1 overflow-y-auto px-2 py-2">
            <div v-if="loading && products.length === 0" class="flex justify-center py-10">
                <Spinner class="size-5" />
            </div>

            <div
                v-else-if="products.length === 0"
                class="flex flex-col items-center gap-2 py-10 text-sm text-muted-foreground"
            >
                <PackageOpen class="size-8 opacity-30" />
                {{ t('app.landlord.mercadologico.products.empty') }}
            </div>

            <template v-else>
                <div
                    v-for="product in products"
                    :key="product.id"
                    draggable="true"
                    class="flex cursor-grab items-center gap-3 rounded-md px-2 py-1.5 transition hover:bg-muted/60 active:cursor-grabbing"
                    @dragstart="onProductDragStart($event, product)"
                >
                    <Checkbox
                        :model-value="selectedIds.has(product.id)"
                        @update:model-value="toggle(product.id)"
                    />
                    <img
                        v-if="product.image_url"
                        :src="product.image_url"
                        alt=""
                        draggable="false"
                        class="size-9 shrink-0 rounded border border-border/60 object-contain"
                    />
                    <div v-else class="flex size-9 shrink-0 items-center justify-center rounded border border-border/60 bg-muted">
                        <PackageOpen class="size-4 text-muted-foreground/50" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ product.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            <span v-if="product.ean">{{ t('app.landlord.mercadologico.products.columns.ean') }}: {{ product.ean }}</span>
                            <span v-if="product.codigo_erp"> · {{ product.codigo_erp }}</span>
                        </p>
                    </div>
                </div>

                <div v-if="hasMore" class="px-2 py-2">
                    <Button variant="outline" size="sm" class="w-full" :disabled="loading" @click="loadMore">
                        <Spinner v-if="loading" class="size-4" />
                        {{ t('app.landlord.mercadologico.products.load_more') }}
                    </Button>
                </div>
            </template>
        </div>

        <!-- Rodapé: mover selecionados (alternativa ao arraste entre painéis) -->
        <div class="flex items-center gap-2 border-t px-4 py-3">
            <ArrowRightLeft class="size-4 shrink-0 text-muted-foreground" />
            <select
                v-model="targetId"
                class="h-9 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:opacity-50"
                :disabled="otherCategories.length === 0"
            >
                <option value="">
                    {{ otherCategories.length === 0 ? t('app.landlord.mercadologico.products.open_target_hint') : t('app.landlord.mercadologico.products.select_target') }}
                </option>
                <option v-for="option in otherCategories" :key="option.categoryId" :value="option.categoryId">
                    {{ option.categoryName }}
                </option>
            </select>
            <Button size="sm" :disabled="!canMove" @click="moveSelected">
                <Spinner v-if="moving" class="size-4" />
                {{ t('app.landlord.mercadologico.products.move_selected') }}
            </Button>
        </div>
    </div>
</template>
