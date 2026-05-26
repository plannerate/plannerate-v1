<script setup lang="ts">
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { useRejectedProductsStore } from '@/composables/plannerate/interactions/useRejectedProductsStore';
import { usePlanogramSelection } from '@/composables/plannerate/core/usePlanogramSelection';
import { selectedTemplateCategoryId } from '@/composables/plannerate/core/useGondolaState';
import type { RejectedProduct } from '@/composables/plannerate/core/useGondolaState';
import { ArrowLeftRight, ChevronDown, ChevronUp, GripVertical, Layers, Loader2, MoveHorizontal, Ruler, Trash2, X } from 'lucide-vue-next';
import { type Component, computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps<{ gondolaId: string }>();

const editor = usePlanogramEditor();
const selection = usePlanogramSelection();
const rejectedStore = useRejectedProductsStore();

const isOpen = ref(false);
const isSwapping = ref(false);
const draggingId = ref<string | null>(null);
const swapSource = ref<RejectedProduct | null>(null);
const cardsContainer = ref<HTMLElement | null>(null);

function handleWheel(event: WheelEvent) {
    if (!cardsContainer.value) return;
    event.preventDefault();
    cardsContainer.value.scrollLeft += event.deltaY;
}

const swapModeActive = computed(() => swapSource.value !== null);

const filteredRejectedProducts = computed(() => {
    const all = editor.rejectedProducts.value;
    const filter = selectedTemplateCategoryId.value;
    if (!filter) return all;
    return all.filter((p) => p.category_id === filter);
});

// ── Cookie utilities ─────────────────────────────────────────────────────────
const DRAWER_STATE_COOKIE = 'rejected-products-drawer-state';

function getCookie(name: string): string | null {
    if (typeof document === 'undefined') return null;
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop()?.split(';').shift() || null;
    return null;
}

function setCookie(name: string, value: string, days: number = 30): void {
    if (typeof document === 'undefined') return;
    const date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    const expires = `expires=${date.toUTCString()}`;
    document.cookie = `${name}=${value}; ${expires}; path=/`;
}

// ── Click / dblclick ─────────────────────────────────────────────────────────
let clickTimer: ReturnType<typeof setTimeout> | null = null;
const clickDelay = 250;

function isProductSelected(product: RejectedProduct): boolean {
    return selection.isSelected('product', product.product_id);
}

function handleCardClick(_event: MouseEvent, product: RejectedProduct) {
    if (clickTimer) {
        clearTimeout(clickTimer);
        clickTimer = null;
        return;
    }
    clickTimer = setTimeout(() => {
        selection.selectItem('product', product.product_id, buildProduct(product), {
            source: 'rejected_products',
            rejection: {
                id: product.id,
                reason: product.rejection_reason,
                reason_label: product.rejection_reason_label,
                slot_id: product.slot_id,
                category_name: product.category_name,
                category_id: product.category_id,
                module_number: product.module_number,
                shelf_order: product.shelf_order,
                rejected_shelf_orders: product.rejected_shelf_orders,
            },
        });

        if (product.category_id && product.category_id !== selectedTemplateCategoryId.value) {
            selectedTemplateCategoryId.value = product.category_id;
        }

        clickTimer = null;
    }, clickDelay);
}

// ── Reason badge ─────────────────────────────────────────────────────────────
const reasonMeta = (
    reason: string,
): { icon: Component; label: string; variant: 'outline' | 'destructive' | 'secondary' } => {
    if (reason === 'no_horizontal_space')
        return { icon: MoveHorizontal, label: 'Sem espaço', variant: 'outline' };
    if (reason === 'height_exceeds_shelf')
        return { icon: Ruler, label: 'Altura', variant: 'destructive' };
    if (reason === 'manually_removed')
        return { icon: Trash2, label: 'Removido', variant: 'secondary' };
    return { icon: Layers, label: 'Nível', variant: 'secondary' };
};

// ── Build product object compatible with addProductToShelf ───────────────────
function buildProduct(r: RejectedProduct) {
    return {
        id: r.product_id,
        name: r.product_name,
        ean: r.ean ?? undefined,
        image_url: r.image_url ?? undefined,
        width: r.product_width ?? undefined,
        height: r.product_height ?? undefined,
        depth: undefined,
        status: 'published',
        has_dimensions: !!(r.product_width && r.product_height),
    };
}

// ── Drag ─────────────────────────────────────────────────────────────────────
function handleDragStart(event: DragEvent, product: RejectedProduct) {
    draggingId.value = product.id;
    if (!event.dataTransfer) return;
    const productObj = buildProduct(product);
    event.dataTransfer.effectAllowed = 'copy';
    event.dataTransfer.setData('application/x-product-id', productObj.id);
    event.dataTransfer.setData('application/x-product', JSON.stringify(productObj));
    event.dataTransfer.setData('text/plain', productObj.name ?? '');
    event.dataTransfer.setData('application/x-rejected-id', product.id);
}

function handleDragEnd() {
    draggingId.value = null;
}

// ── Double-click: add to selected shelf ──────────────────────────────────────
function handleDoubleClick(product: RejectedProduct) {
    if (selection.selectedType.value !== 'shelf' || !selection.selectedId.value) {
        toast.error('Selecione uma prateleira primeiro (clique nela uma vez).');
        return;
    }
    if (!product.product_width || !product.product_height) {
        toast.error(`"${product.product_name}" não tem dimensões cadastradas.`);
        return;
    }
    const placed = editor.placeFromRejected(product, selection.selectedId.value);
    if (placed) {
        toast.success(`"${product.product_name}" adicionado à prateleira.`);
    }
}

// ── Listen for drag-placed event from useShelfDragDrop ───────────────────────
rejectedStore.setOnProductPlaced((productId: string) => {
    const found = editor.rejectedProducts.value.find((r) => r.product_id === productId);
    if (!found) return;
    editor.patchRejectedProductToLastAction(found);
    void editor.deleteRejectedProduct(found.id);
});

// ── Swap mode ────────────────────────────────────────────────────────────────
function enterSwapMode(product: RejectedProduct) {
    swapSource.value = product;
    toast.info(`Clique em um produto na gôndola para trocar com "${product.product_name}".`, {
        duration: 8000,
    });
}

function cancelSwapMode() {
    swapSource.value = null;
    selection.clearSelection();
}

async function executeSwap(layerId: string) {
    if (!swapSource.value) return;
    isSwapping.value = true;
    const source = swapSource.value;
    swapSource.value = null;

    const success = await editor.swapRejectedProduct(source, layerId);

    if (success) {
        toast.success(`"${source.product_name}" posicionado na gôndola.`);
        selection.clearSelection();
    } else {
        swapSource.value = source;
        toast.error('Não foi possível realizar a troca. Tente novamente.');
    }

    isSwapping.value = false;
}

watch(
    () => selection.selectedItem.value,
    (item) => {
        if (!swapModeActive.value || !item || item.type !== 'segment') return;
        const layerId: string | undefined = (item.item as any)?.layer?.id;
        if (!layerId) {
            toast.warning('Este segmento não tem layer. Clique em outro produto.');
            return;
        }
        void executeSwap(layerId);
    },
);

onMounted(() => {
    const savedState = getCookie(DRAWER_STATE_COOKIE);
    const hasUserPreference = savedState !== null;

    if (savedState === 'open') {
        isOpen.value = true;
    } else if (savedState === 'closed') {
        isOpen.value = false;
    }

    void editor.fetchRejectedProducts(props.gondolaId).then(() => {
        if (!hasUserPreference && editor.rejectedProducts.value.length > 0) {
            isOpen.value = true;
        }
    });
});

watch(
    () => isOpen.value,
    (value) => {
        setCookie(DRAWER_STATE_COOKIE, value ? 'open' : 'closed');
    },
);

onUnmounted(() => rejectedStore.clearOnProductPlaced());

defineExpose({
    fetchRejected: () => editor.fetchRejectedProducts(props.gondolaId),
});
</script>

<template>
    <div
        class="z-50 flex flex-col"
        :class="isOpen ? 'shadow-2xl' : ''"
    >
        <!-- Handle / Tab -->
        <div
            role="button"
            tabindex="0"
            class="flex w-full cursor-pointer items-center justify-between border-t border-border bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-muted/60"
            @click="isOpen = !isOpen"
            @keydown.enter.space.prevent="isOpen = !isOpen"
        >
            <div class="flex items-center gap-2">
                <ArrowLeftRight class="size-4 text-muted-foreground" />
                <span>Produtos rejeitados</span>
                <Badge v-if="editor.rejectedProducts.value.length > 0" variant="destructive" class="h-5 px-1.5 text-xs">
                    <template v-if="selectedTemplateCategoryId && filteredRejectedProducts.length !== editor.rejectedProducts.value.length">
                        {{ filteredRejectedProducts.length }}/{{ editor.rejectedProducts.value.length }}
                    </template>
                    <template v-else>
                        {{ editor.rejectedProducts.value.length }}
                    </template>
                </Badge>
                <Loader2 v-if="editor.isLoadingRejectedProducts.value" class="size-3.5 animate-spin text-muted-foreground" />
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-if="selectedTemplateCategoryId"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-6 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                    @click.stop="selectedTemplateCategoryId = null"
                >
                    <X class="size-3" />
                    Limpar filtro
                </Button>
                <Button
                    v-if="selection.selectedId.value"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-6 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                    @click.stop="selection.clearSelection()"
                >
                    <X class="size-3" />
                    Limpar seleção
                </Button>
                <ChevronUp v-if="isOpen" class="size-4 text-muted-foreground" />
                <ChevronDown v-else class="size-4 text-muted-foreground" />
            </div>
        </div>

        <!-- Drawer Body with Transition -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-300 ease-in"
            enter-from-class="max-h-0 opacity-0 overflow-hidden"
            enter-to-class="max-h-screen opacity-100 overflow-visible"
            leave-from-class="max-h-screen opacity-100 overflow-visible"
            leave-to-class="max-h-0 opacity-0 overflow-hidden"
        >
            <div
                v-if="isOpen"
                class="border-t border-border bg-background"
            >
                <!-- Swap mode banner -->
                <div
                    v-if="swapModeActive"
                    class="flex items-center justify-between bg-amber-50 px-4 py-2 text-sm dark:bg-amber-950/30"
                >
                    <span class="font-medium text-amber-700 dark:text-amber-400">
                        Clique em um produto na gôndola para trocar com
                        <strong>{{ swapSource?.product_name }}</strong>
                    </span>
                    <Button variant="ghost" size="sm" class="h-6 gap-1 text-xs" @click="cancelSwapMode">
                        <X class="size-3" /> Cancelar
                    </Button>
                </div>

                <!-- Empty state -->
                <div
                    v-if="!editor.isLoadingRejectedProducts.value && filteredRejectedProducts.length === 0"
                    class="flex h-20 items-center justify-center text-sm text-muted-foreground"
                >
                    <span v-if="selectedTemplateCategoryId && editor.rejectedProducts.value.length > 0">
                        Nenhum produto rejeitado neste grouping.
                    </span>
                    <span v-else>Nenhum produto rejeitado nesta geração.</span>
                </div>

                <!-- Product cards -->
                <div ref="cardsContainer" v-else class="flex gap-3 overflow-x-auto p-3" @wheel="handleWheel">
                    <div
                        v-for="product in filteredRejectedProducts"
                        :key="product.id"
                        draggable="true"
                        class="flex w-36 shrink-0 flex-col gap-1.5 rounded-lg border p-2 transition-all select-none"
                        :class="{
                            'border-blue-500 bg-blue-50 shadow-md ring-2 ring-blue-200 dark:bg-blue-950/20 dark:ring-blue-900':
                                isProductSelected(product) && !swapModeActive,
                            'border-border bg-card': !isProductSelected(product),
                            'ring-2 ring-amber-400 border-amber-400': swapSource?.id === product.id,
                            'opacity-40': swapModeActive && swapSource?.id !== product.id,
                            'cursor-grabbing opacity-50 ring-2 ring-primary': draggingId === product.id,
                            'cursor-grab': draggingId !== product.id,
                        }"
                        @click.stop="handleCardClick($event, product)"
                        @dragstart="handleDragStart($event, product)"
                        @dragend="handleDragEnd"
                        @dblclick.stop="handleDoubleClick(product)"
                    >
                        <!-- Drag handle hint + image -->
                        <div class="relative flex h-16 items-center justify-center overflow-hidden rounded bg-muted">
                            <GripVertical class="absolute left-0.5 top-0.5 size-3 text-muted-foreground/40" />
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                :alt="product.product_name"
                                class="max-h-full max-w-full object-contain"
                            />
                            <span v-else class="text-xs text-muted-foreground">Sem imagem</span>
                        </div>

                        <!-- Product name -->
                        <p class="line-clamp-2 text-xs font-medium leading-tight">
                            {{ product.product_name }}
                        </p>

                        <!-- Reason badge -->
                        <TooltipProvider :delay-duration="200">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Badge
                                        :variant="reasonMeta(product.rejection_reason).variant"
                                        class="w-full cursor-default justify-start gap-1 px-1.5 py-0.5 text-xs"
                                    >
                                        <component :is="reasonMeta(product.rejection_reason).icon" class="size-3 shrink-0" />
                                        {{ reasonMeta(product.rejection_reason).label }}
                                    </Badge>
                                </TooltipTrigger>
                                <TooltipContent side="top">
                                    {{ product.rejection_reason_label }}
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <!-- Swap button -->
                        <Button
                            size="sm"
                            variant="outline"
                            class="mt-auto h-7 w-full gap-1 text-xs"
                            :disabled="isSwapping || (swapModeActive && swapSource?.id !== product.id)"
                            @click.stop="swapSource?.id === product.id ? cancelSwapMode() : enterSwapMode(product)"
                        >
                            <Loader2 v-if="isSwapping && swapSource?.id === product.id" class="size-3 animate-spin" />
                            <ArrowLeftRight v-else class="size-3" />
                            {{ swapSource?.id === product.id ? 'Cancelar' : 'Trocar' }}
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
