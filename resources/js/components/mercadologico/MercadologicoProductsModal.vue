<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import { MERCADOLOGICO_PRODUCTS_DRAG_TYPE } from '@/constants/mercadologicoProductsDrag';
import { Package, GripVertical, X } from 'lucide-vue-next';

export interface MercadologicoProduct {
    id: string;
    name: string;
    ean: string | null;
    category_id: string | null;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        categoryId: string | null;
        categoryName: string;
        categoryHierarchyPath?: string;
        productsUrl: string;
        refreshTrigger?: number;
        x?: number;
        y?: number;
    }>(),
    { categoryHierarchyPath: '', refreshTrigger: 0, x: 100, y: 100 },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:position', x: number, y: number): void;
    (e: 'drop-products', productIds: string[]): void;
}>();

const posX = ref(props.x);
const posY = ref(props.y);
const isDraggingWindow = ref(false);
const dragStart = ref({ x: 0, y: 0, left: 0, top: 0 });

watch(
    () => [props.x, props.y],
    ([x, y]) => {
        posX.value = x ?? 100;
        posY.value = y ?? 100;
    },
    { immediate: true },
);

function onHeaderMouseDown(e: MouseEvent) {
    if ((e.target as HTMLElement).closest('button')) return;
    isDraggingWindow.value = true;
    dragStart.value = {
        x: e.clientX,
        y: e.clientY,
        left: posX.value,
        top: posY.value,
    };
}

function onMouseMove(e: MouseEvent) {
    if (!isDraggingWindow.value) return;
    const dx = e.clientX - dragStart.value.x;
    const dy = e.clientY - dragStart.value.y;
    const newX = Math.max(0, dragStart.value.left + dx);
    const newY = Math.max(0, dragStart.value.top + dy);
    posX.value = newX;
    posY.value = newY;
    emit('update:position', newX, newY);
}

function onMouseUp() {
    isDraggingWindow.value = false;
}

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
});

const products = ref<MercadologicoProduct[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

async function fetchProducts() {
    if (!props.categoryId || !props.productsUrl) {
        products.value = [];
        return;
    }
    loading.value = true;
    error.value = null;
    try {
        const url = `${props.productsUrl}?category_id=${encodeURIComponent(props.categoryId)}`;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (!res.ok) {
            error.value = json?.error ?? 'Erro ao carregar produtos';
            products.value = [];
            return;
        }
        products.value = Array.isArray(json?.data) ? json.data : [];
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Erro ao carregar produtos';
        products.value = [];
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.open, props.categoryId, props.refreshTrigger] as const,
    ([open, categoryId]) => {
        if (open && categoryId) {
            fetchProducts();
        } else if (!open) {
            products.value = [];
            error.value = null;
        }
    },
    { immediate: true },
);

const allProductIds = computed(() => products.value.map((p) => p.id));

function onDragStart(e: DragEvent, productIds: string[]) {
    if (!e.dataTransfer || !props.categoryId) return;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData(
        MERCADOLOGICO_PRODUCTS_DRAG_TYPE,
        JSON.stringify({ productIds, sourceCategoryId: props.categoryId }),
    );
    e.dataTransfer.setData('text/plain', productIds.join(','));
}

const isDropTarget = ref(false);

function onWindowDragOver(e: DragEvent) {
    if (!e.dataTransfer?.types?.includes(MERCADOLOGICO_PRODUCTS_DRAG_TYPE)) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    isDropTarget.value = true;
}

function onWindowDragLeave() {
    isDropTarget.value = false;
}

function onWindowDrop(e: DragEvent) {
    isDropTarget.value = false;
    const raw = e.dataTransfer?.getData(MERCADOLOGICO_PRODUCTS_DRAG_TYPE);
    if (!raw) return;
    e.preventDefault();
    try {
        const { productIds } = JSON.parse(raw) as { productIds: string[] };
        if (Array.isArray(productIds) && productIds.length > 0 && props.categoryId) {
            emit('drop-products', productIds);
        }
    } catch {
        // ignore
    }
}

function close() {
    emit('update:open', false);
}
</script>

<template>
    <Teleport to="body">
        <div
            v-show="open"
            class="fixed z-[100] flex w-[380px] flex-col rounded-lg border border-border bg-background shadow-lg"
            :class="{ 'ring-2 ring-primary': isDropTarget }"
            :style="{ left: `${posX}px`, top: `${posY}px` }"
            @dragover="onWindowDragOver"
            @dragleave="onWindowDragLeave"
            @drop="onWindowDrop"
        >
            <!-- Barra de título arrastável -->
            <div
                class="flex cursor-move flex-col gap-0.5 rounded-t-lg border-b border-border bg-muted/50 px-3 py-2"
                @mousedown="onHeaderMouseDown"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <Package class="size-4 shrink-0 text-muted-foreground" />
                        <span class="truncate text-sm font-medium uppercase">
                            {{ categoryName || 'Produtos' }}
                        </span>
                    </div>
                    <button
                    type="button"
                    class="shrink-0 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Fechar"
                    @mousedown.stop
                        @click="close"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <p
                    v-if="categoryHierarchyPath"
                    class="truncate text-[10px] font-normal text-muted-foreground uppercase"
                    :title="categoryHierarchyPath"
                >
                    {{ categoryHierarchyPath }}
                </p>
            </div>

            <div class="flex flex-col gap-2 p-3">
                <p class="text-xs text-muted-foreground">
                    Arraste itens para a árvore à esquerda ou para outra janela de categoria. Use "Mover todos" para arrastar todos.
                </p>
                <ScrollArea class="h-[280px] rounded-md border border-border p-2">
                    <div v-if="loading" class="flex items-center justify-center py-8 text-sm text-muted-foreground">
                        Carregando…
                    </div>
                    <div v-else-if="error" class="py-4 text-center text-sm text-destructive">
                        {{ error }}
                    </div>
                    <div v-else-if="products.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        Nenhum produto nesta categoria. Arraste produtos de outra janela para mover para aqui.
                    </div>
                    <ul v-else class="space-y-1">
                        <li
                            v-if="products.length > 1"
                            class="flex cursor-grab items-center gap-2 rounded-md border border-dashed border-primary/50 bg-primary/5 px-2 py-2 active:cursor-grabbing"
                            draggable="true"
                            @dragstart="onDragStart($event, allProductIds)"
                        >
                            <GripVertical class="size-4 shrink-0 text-muted-foreground" />
                            <span class="text-xs font-medium text-primary">
                                Mover todos ({{ products.length }})
                            </span>
                        </li>
                        <li
                            v-for="p in products"
                            :key="p.id"
                            class="flex cursor-grab items-center gap-2 rounded-md border border-border bg-background px-2 py-2 transition-colors hover:bg-muted/50 active:cursor-grabbing"
                            draggable="true"
                            @dragstart="onDragStart($event, [p.id])"
                        >
                            <GripVertical class="size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ p.name }}</span>
                            <span v-if="p.ean" class="shrink-0 font-mono text-[10px] text-muted-foreground">
                                {{ p.ean }}
                            </span>
                        </li>
                    </ul>
                </ScrollArea>
            </div>
        </div>
    </Teleport>
</template>
