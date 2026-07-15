<script setup lang="ts">
import { ArrowLeftRight, Ban, ChevronDown, ChevronUp, GripVertical, Layers, Loader2, MoveHorizontal, RefreshCw, Ruler, Trash2, X } from 'lucide-vue-next';
import {  computed, onMounted, onUnmounted, ref, watch } from 'vue';
import type {Component} from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { RejectedProduct } from '@/composables/plannerate/core/useGondolaState';
import { selectedTemplateCategoryId } from '@/composables/plannerate/core/useGondolaState';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/core/usePlanogramSelection';
import { setRejectedProductDragData } from '@/composables/plannerate/dnd/transfer';
import { useRejectedProductsStore } from '@/composables/plannerate/interactions/useRejectedProductsStore';
import { useT } from '@/composables/useT';
import ProductPlaceholder from './ProductPlaceholder.vue';

const props = defineProps<{ gondolaId: string }>();

const { t } = useT();
const editor = usePlanogramEditor();
const selection = usePlanogramSelection();
const rejectedStore = useRejectedProductsStore();

const isOpen = ref(false);
const isSwapping = ref(false);
// Controla se a lista de rejeitados já foi carregada sob demanda (via botão).
// Enquanto false, o corpo mostra o botão "Carregar" em vez de cards/empty-state.
const hasLoaded = ref(false);
const draggingId = ref<string | null>(null);
const swapSource = ref<RejectedProduct | null>(null);
const cardsContainer = ref<HTMLElement | null>(null);

/**
 * `product.image_url` costuma vir preenchido antes do arquivo existir de fato
 * no storage — sem tratamento de erro, o `<img>` mostrava o ícone quebrado do
 * navegador em vez do placeholder "sem imagem" que já existia para URL ausente.
 * Um Set (não um único ref) porque a lista renderiza vários cards ao mesmo tempo.
 */
const failedImageIds = ref<Set<string>>(new Set());

function hasImage(product: RejectedProduct): boolean {
    return Boolean(product.image_url) && !failedImageIds.value.has(product.id);
}

function onImageError(productId: string): void {
    failedImageIds.value = new Set(failedImageIds.value).add(productId);
}

function handleWheel(event: WheelEvent) {
    if (!cardsContainer.value) {
return;
}

    event.preventDefault();
    cardsContainer.value.scrollLeft += event.deltaY;
}

const swapModeActive = computed(() => swapSource.value !== null);

// Filtro por motivo de rejeição (null = todos). Combina com o filtro de categoria.
const selectedReason = ref<string | null>(null);

// Lista após o filtro de categoria (base para os chips de motivo e suas contagens).
const categoryFilteredProducts = computed(() => {
    const all = editor.rejectedProducts.value;
    const filter = selectedTemplateCategoryId.value;

    if (!filter) {
return all;
}

    return all.filter((p) => p.category_id === filter);
});

// Motivos presentes na seleção atual, com contagem — alimenta os chips de filtro.
const reasonsPresent = computed(() => {
    const counts = new Map<string, number>();

    for (const p of categoryFilteredProducts.value) {
        counts.set(p.rejection_reason, (counts.get(p.rejection_reason) ?? 0) + 1);
    }

    return Array.from(counts.entries()).map(([reason, count]) => ({ reason, count, meta: reasonMeta(reason) }));
});

const filteredRejectedProducts = computed(() => {
    const base = categoryFilteredProducts.value;

    if (!selectedReason.value) {
return base;
}

    return base.filter((p) => p.rejection_reason === selectedReason.value);
});

// Se o motivo selecionado deixar de existir (ex.: trocou o filtro de categoria), reseta.
watch(reasonsPresent, (list) => {
    if (selectedReason.value && !list.some((r) => r.reason === selectedReason.value)) {
        selectedReason.value = null;
    }
});

// ── Cookie utilities ─────────────────────────────────────────────────────────
const DRAWER_STATE_COOKIE = 'rejected-products-drawer-state';

function getCookie(name: string): string | null {
    if (typeof document === 'undefined') {
return null;
}

    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);

    if (parts.length === 2) {
return parts.pop()?.split(';').shift() || null;
}

    return null;
}

function setCookie(name: string, value: string, days: number = 30): void {
    if (typeof document === 'undefined') {
return;
}

    const date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    const expires = `expires=${date.toUTCString()}`;
    document.cookie = `${name}=${value}; ${expires}; path=/`;
}

// ── Click / dblclick ─────────────────────────────────────────────────────────
let clickTimer: ReturnType<typeof setTimeout> | null = null;
const clickDelay = 250;

/**
 * IDs dos produtos atualmente selecionados (única + múltipla), como Set para
 * lookup O(1) no template. Calculado UMA vez por mudança de seleção — antes,
 * cada card chamava `selection.isSelected('product', id)` a cada re-render do
 * drawer (que ocorre a todo clique na gôndola), repetindo a varredura para
 * todos os cards. Agora o card só faz `selectedProductIds.has(id)`.
 */
const selectedProductIds = computed<Set<string>>(() => {
    const ids = new Set<string>();

    if (selection.selectedType.value === 'product' && selection.selectedId.value) {
        ids.add(selection.selectedId.value);
    }

    for (const item of selection.selectedItems.value) {
        if (item.type === 'product') {
            ids.add(item.id);
        }
    }

    return ids;
});

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
type ReasonMeta = { icon: Component; label: string; variant: 'outline' | 'destructive' | 'secondary' };

/**
 * Cache de metadados por motivo. O template chama `reasonMeta` 3× por card; sem
 * cache, cada chamada alocava um objeto novo (centenas de alocações por clique,
 * pressionando o GC). Como os motivos são um conjunto fixo e pequeno, retornar
 * sempre a MESMA referência elimina alocações e ajuda o Vue a evitar trabalho.
 */
const _reasonMetaCache = new Map<string, ReasonMeta>();

function buildReasonMeta(reason: string): ReasonMeta {
    if (reason === 'no_horizontal_space') {
return { icon: MoveHorizontal, label: t('plannerate.editor.rejected_products.reasons.no_horizontal_space'), variant: 'outline' };
}

    if (reason === 'height_exceeds_shelf') {
return { icon: Ruler, label: t('plannerate.editor.rejected_products.reasons.height_exceeds_shelf'), variant: 'destructive' };
}

    if (reason === 'manually_removed') {
return { icon: Trash2, label: t('plannerate.editor.rejected_products.reasons.manually_removed'), variant: 'secondary' };
}

    if (reason === 'removed_from_mix') {
return { icon: Ban, label: t('plannerate.editor.rejected_products.reasons.removed_from_mix'), variant: 'secondary' };
}

    return { icon: Layers, label: t('plannerate.editor.rejected_products.reasons.level'), variant: 'secondary' };
}

const reasonMeta = (reason: string): ReasonMeta => {
    let cached = _reasonMetaCache.get(reason);

    if (!cached) {
        cached = buildReasonMeta(reason);
        _reasonMetaCache.set(reason, cached);
    }

    return cached;
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

    if (!event.dataTransfer) {
return;
}

    const productObj = buildProduct(product);
    setRejectedProductDragData(event.dataTransfer, product.id, productObj);
    event.dataTransfer.setData('text/plain', productObj.name ?? '');
}

function handleDragEnd() {
    draggingId.value = null;
}

// ── Double-click: add to selected shelf ──────────────────────────────────────
function handleDoubleClick(product: RejectedProduct) {
    if (selection.selectedType.value !== 'shelf' || !selection.selectedId.value) {
        toast.error(t('plannerate.editor.rejected_products.select_shelf_first'));

        return;
    }

    if (!product.product_width || !product.product_height) {
        toast.error(t('plannerate.editor.rejected_products.no_dimensions', { product: product.product_name }));

        return;
    }

    const placed = editor.placeFromRejected(product, selection.selectedId.value);

    if (placed) {
        toast.success(t('plannerate.editor.rejected_products.added_to_shelf', { product: product.product_name }));
    }
}

// ── Listen for drag-placed event from useShelfDragDrop ───────────────────────
rejectedStore.setOnProductPlaced((productId: string) => {
    const found = editor.rejectedProducts.value.find((r) => r.product_id === productId);

    if (!found) {
return;
}

    editor.patchRejectedProductToLastAction(found);
    void editor.deleteRejectedProduct(found.id);
});

// ── Swap mode ────────────────────────────────────────────────────────────────
function enterSwapMode(product: RejectedProduct) {
    swapSource.value = product;
    toast.info(t('plannerate.editor.rejected_products.swap_hint', { product: product.product_name }), {
        duration: 8000,
    });
}

function cancelSwapMode() {
    swapSource.value = null;
    selection.clearSelection();
}

async function executeSwap(layerId: string) {
    if (!swapSource.value) {
return;
}

    isSwapping.value = true;
    const source = swapSource.value;
    swapSource.value = null;

    const success = await editor.swapRejectedProduct(source, layerId);

    if (success) {
        toast.success(t('plannerate.editor.rejected_products.positioned', { product: source.product_name }));
        selection.clearSelection();
    } else {
        swapSource.value = source;
        toast.error(t('plannerate.editor.rejected_products.swap_failed'));
    }

    isSwapping.value = false;
}

watch(
    () => selection.selectedItem.value,
    (item) => {
        if (!swapModeActive.value || !item || item.type !== 'segment') {
return;
}

        const layerId: string | undefined = (item.item as any)?.layer?.id;

        if (!layerId) {
            toast.warning(t('plannerate.editor.rejected_products.segment_no_layer'));

            return;
        }

        void executeSwap(layerId);
    },
);

// ── Carregamento sob demanda ─────────────────────────────────────────────────
/**
 * Busca (ou recarrega) a lista de produtos rejeitados a pedido do usuário.
 * Nada é carregado no mount nem em saves — só quando este método é chamado
 * pelo botão de carregar/recarregar.
 */
async function loadRejected() {
    await editor.fetchRejectedProducts(props.gondolaId);
    hasLoaded.value = true;
}

onMounted(() => {
    // Apenas restaura a preferência de aberto/fechado do cookie. Sem buscar
    // rejeitados e sem auto-abrir por dados — o carregamento é explícito.
    const savedState = getCookie(DRAWER_STATE_COOKIE);

    if (savedState === 'open') {
        isOpen.value = true;
    } else if (savedState === 'closed') {
        isOpen.value = false;
    }
});

watch(
    () => isOpen.value,
    (value) => {
        setCookie(DRAWER_STATE_COOKIE, value ? 'open' : 'closed');
    },
);

onUnmounted(() => rejectedStore.clearOnProductPlaced());

defineExpose({
    fetchRejected: () => loadRejected(),
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
                <span>{{ t('plannerate.editor.rejected_products.title') }}</span>
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
                    {{ t('plannerate.editor.rejected_products.clear_filter') }}
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
                    {{ t('plannerate.editor.rejected_products.clear_selection') }}
                </Button>
                <TooltipProvider :delay-duration="200">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                                :disabled="editor.isLoadingRejectedProducts.value"
                                @click.stop="loadRejected()"
                            >
                                <RefreshCw
                                    class="size-3.5"
                                    :class="{ 'animate-spin': editor.isLoadingRejectedProducts.value }"
                                />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{ t('plannerate.editor.rejected_products.refresh') }}
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
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
                        {{ t('plannerate.editor.rejected_products.swap_banner') }}
                        <strong>{{ swapSource?.product_name }}</strong>
                    </span>
                    <Button variant="ghost" size="sm" class="h-6 gap-1 text-xs" @click="cancelSwapMode">
                        <X class="size-3" /> {{ t('plannerate.editor.rejected_products.cancel') }}
                    </Button>
                </div>

                <!-- Reason filter chips -->
                <div
                    v-if="reasonsPresent.length > 1"
                    class="flex flex-wrap items-center gap-1.5 border-b border-border px-3 py-2"
                >
                    <span class="mr-0.5 text-xs font-medium text-muted-foreground">{{ t('plannerate.editor.rejected_products.reason_label') }}</span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition-colors"
                        :class="selectedReason === null
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border text-muted-foreground hover:bg-muted'"
                        @click="selectedReason = null"
                    >
                        {{ t('plannerate.editor.rejected_products.all') }} ({{ categoryFilteredProducts.length }})
                    </button>
                    <button
                        v-for="r in reasonsPresent"
                        :key="r.reason"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition-colors"
                        :class="selectedReason === r.reason
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border text-muted-foreground hover:bg-muted'"
                        @click="selectedReason = selectedReason === r.reason ? null : r.reason"
                    >
                        <component :is="r.meta.icon" class="size-3 shrink-0" />
                        {{ r.meta.label }} ({{ r.count }})
                    </button>
                </div>

                <!-- Not loaded state: botão de carregar sob demanda -->
                <div
                    v-if="!hasLoaded"
                    class="flex h-32 flex-col items-center justify-center gap-3 px-4 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ t('plannerate.editor.rejected_products.not_loaded') }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        :disabled="editor.isLoadingRejectedProducts.value"
                        @click="loadRejected()"
                    >
                        <Loader2 v-if="editor.isLoadingRejectedProducts.value" class="size-4 animate-spin" />
                        <RefreshCw v-else class="size-4" />
                        {{ t('plannerate.editor.rejected_products.load') }}
                    </Button>
                </div>

                <!-- Empty state -->
                <div
                    v-else-if="!editor.isLoadingRejectedProducts.value && filteredRejectedProducts.length === 0"
                    class="flex h-20 items-center justify-center text-sm text-muted-foreground"
                >
                    <span v-if="selectedTemplateCategoryId && editor.rejectedProducts.value.length > 0">
                        {{ t('plannerate.editor.rejected_products.empty_grouping') }}
                    </span>
                    <span v-else>{{ t('plannerate.editor.rejected_products.empty_generation') }}</span>
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
                                selectedProductIds.has(product.product_id) && !swapModeActive,
                            'border-border bg-card': !selectedProductIds.has(product.product_id),
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
                                v-if="hasImage(product)"
                                :src="product.image_url ?? undefined"
                                :alt="product.product_name"
                                class="max-h-full max-w-full object-contain"
                                @error="onImageError(product.id)"
                            />
                            <ProductPlaceholder v-else :width="144" :height="64" :name="product.product_name" :ean="product.ean" />
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
                            {{ swapSource?.id === product.id ? t('plannerate.editor.rejected_products.cancel') : t('plannerate.editor.rejected_products.swap') }}
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
