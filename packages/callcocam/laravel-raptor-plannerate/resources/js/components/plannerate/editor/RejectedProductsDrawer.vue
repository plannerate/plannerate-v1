<script setup lang="ts">
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { useRejectedProductsStore } from '@/composables/plannerate/editor/useRejectedProductsStore';
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import { useAutoplanogramUrls } from '@/composables/useAutoplanogramUrls';
import { ArrowLeftRight, ChevronDown, ChevronUp, GripVertical, Layers, Loader2, MoveHorizontal, Ruler, X } from 'lucide-vue-next';
import { type Component, computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface RejectedProduct {
    id: string;
    product_id: string;
    product_name: string;
    ean: string | null;
    image_url: string | null;
    product_width: number | null;
    product_height: number | null;
    rejection_reason: string;
    rejection_reason_label: string;
    slot_id: string | null;
    grouping: string | null;
    module_number: number | null;
    shelf_order: number | null;
}

const props = defineProps<{ gondolaId: string }>();

const { rejectedProductsUrl, swapProductUrl, destroyRejectedUrl } = useAutoplanogramUrls(props.gondolaId);
const selection = usePlanogramSelection();
const editor = usePlanogramEditor();
const rejectedStore = useRejectedProductsStore();

const isOpen = ref(false);
const isLoading = ref(false);
const isSwapping = ref(false);
const draggingId = ref<string | null>(null);
const rejectedProducts = ref<RejectedProduct[]>([]);
const swapSource = ref<RejectedProduct | null>(null);

const swapModeActive = computed(() => swapSource.value !== null);

// ── Click / dblclick (mesmo padrão do Card.vue do sidebar) ───────────────────
let clickTimer: ReturnType<typeof setTimeout> | null = null;
const clickDelay = 250;

function isProductSelected(product: RejectedProduct): boolean {
    return selection.isSelected('product', product.product_id);
}

function handleCardClick(event: MouseEvent, product: RejectedProduct) {
    if (clickTimer) {
        clearTimeout(clickTimer);
        clickTimer = null;
        return; // dblclick — deixa handleDoubleClick tratar
    }
    clickTimer = setTimeout(() => {
        if (event.ctrlKey || event.metaKey) {
            // @ts-ignore
            selection.toggleSelection('product', product.product_id, buildProduct(product));
        } else {
            //  @ts-ignore
            selection.selectItem('product', product.product_id, buildProduct(product));
        }
        clickTimer = null;
    }, clickDelay);
}

const csrfToken = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

// ── Reason badge ────────────────────────────────────────────────────────────
const reasonMeta = (
    reason: string,
): { icon: Component; label: string; variant: 'outline' | 'destructive' | 'secondary' } => {
    if (reason === 'no_horizontal_space')
        return { icon: MoveHorizontal, label: 'Sem espaço', variant: 'outline' };
    if (reason === 'height_exceeds_shelf')
        return { icon: Ruler, label: 'Altura', variant: 'destructive' };
    return { icon: Layers, label: 'Nível', variant: 'secondary' };
};

// ── Build product object compatible with addProductToShelf ──────────────────
function buildProduct(r: RejectedProduct) {
    return {
        id: r.product_id,
        name: r.product_name,
        ean: r.ean ?? undefined,
        image_url: r.image_url ?? undefined,
        width: r.product_width,
        height: r.product_height,
        depth: null,
        status: 'published',
        has_dimensions: !!(r.product_width && r.product_height),
    };
}

// ── Optimistic remove + backend cleanup ─────────────────────────────────────
function removeLocally(rejectedId: string) {
    rejectedProducts.value = rejectedProducts.value.filter((r) => r.id !== rejectedId);
    if (rejectedProducts.value.length === 0) isOpen.value = false;
}

async function deleteFromBackend(rejectedId: string) {
    try {
        await fetch(destroyRejectedUrl(rejectedId), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });
    } catch {
        // silently ignore — list will reconcile on next fetchRejected
    }
}

// ── Fetch ────────────────────────────────────────────────────────────────────
async function fetchRejected() {
    isLoading.value = true;
    try {
        const res = await fetch(rejectedProductsUrl());
        if (!res.ok) throw new Error();
        const json = await res.json();
        rejectedProducts.value = json.data ?? [];
        if (rejectedProducts.value.length > 0) isOpen.value = true;
    } catch {
        toast.error('Não foi possível carregar produtos rejeitados.');
    } finally {
        isLoading.value = false;
    }
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
    // tag to identify rejected origin — used by the placed-callback below
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
    const productObj = buildProduct(product);
    if (!productObj.has_dimensions) {
        toast.error(`"${product.product_name}" não tem dimensões cadastradas.`);
        return;
    }
    removeLocally(product.id);
    editor.addProductToShelf(selection.selectedId.value, productObj.id, productObj, () => {
        void deleteFromBackend(product.id);
    });
    toast.success(`"${product.product_name}" adicionado à prateleira.`);
}

// ── Listen for drag-placed event from useShelfDragDrop ───────────────────────
rejectedStore.setOnProductPlaced((productId: string) => {
    const found = rejectedProducts.value.find((r) => r.product_id === productId);
    if (!found) return;
    removeLocally(found.id);
    void deleteFromBackend(found.id);
});

// ── Swap mode (controle seguro) ───────────────────────────────────────────────
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

    try {
        const res = await fetch(swapProductUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ rejected_product_id: source.id, layer_id: layerId }),
        });

        if (!res.ok) throw new Error();

        editor.updateLayer(layerId, {
            product_id: source.product_id,
            ean: source.ean,
            product: {
                id: source.product_id,
                name: source.product_name,
                ean: source.ean,
                image_url: source.image_url,
                width: source.product_width,
                height: source.product_height,
            },
        });

        toast.success(`"${source.product_name}" posicionado na gôndola.`);
        selection.clearSelection();
        await fetchRejected();
    } catch {
        swapSource.value = source;
        toast.error('Não foi possível realizar a troca. Tente novamente.');
    } finally {
        isSwapping.value = false;
    }
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

onMounted(() => void fetchRejected());
onUnmounted(() => rejectedStore.clearOnProductPlaced());

defineExpose({ fetchRejected });
</script>

<template>
    <div
        class="absolute bottom-0 left-0 right-0 z-50 flex flex-col"
        :class="isOpen ? 'shadow-2xl' : ''"
    >
        <!-- Handle / Tab -->
        <button
            type="button"
            class="flex w-full items-center justify-between border-t border-border bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-muted/60"
            @click="isOpen = !isOpen"
        >
            <div class="flex items-center gap-2">
                <ArrowLeftRight class="size-4 text-muted-foreground" />
                <span>Produtos rejeitados</span>
                <Badge v-if="rejectedProducts.length > 0" variant="destructive" class="h-5 px-1.5 text-xs">
                    {{ rejectedProducts.length }}
                </Badge>
                <Loader2 v-if="isLoading" class="size-3.5 animate-spin text-muted-foreground" />
            </div>
            <ChevronUp v-if="isOpen" class="size-4 text-muted-foreground" />
            <ChevronDown v-else class="size-4 text-muted-foreground" />
        </button>

        <!-- Drawer Body -->
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
            <div v-if="!isLoading && rejectedProducts.length === 0" class="flex h-20 items-center justify-center text-sm text-muted-foreground">
                Nenhum produto rejeitado nesta geração.
            </div>

            <!-- Product cards -->
            <div v-else class="flex gap-3 overflow-x-auto p-3">
                <div
                    v-for="product in rejectedProducts"
                    :key="product.id"
                    draggable="true"
                    class="flex w-36 flex-shrink-0 flex-col gap-1.5 rounded-lg border p-2 transition-all select-none"
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

                    <!-- Grouping -->
                    <p v-if="product.grouping" class="truncate text-xs text-muted-foreground">
                        {{ product.grouping.split(' | ').at(-1) }}
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
    </div>
</template>
