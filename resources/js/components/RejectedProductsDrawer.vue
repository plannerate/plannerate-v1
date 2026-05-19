<script setup lang="ts">
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import { useAutoplanogramUrls } from '@/composables/useAutoplanogramUrls';
import { ArrowLeftRight, ChevronDown, ChevronUp, Loader2, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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

const props = defineProps<{
    gondolaId: string;
}>();

const { rejectedProductsUrl, swapProductUrl } = useAutoplanogramUrls(props.gondolaId);
const selection = usePlanogramSelection();

const isOpen = ref(false);
const isLoading = ref(false);
const isSwapping = ref(false);
const rejectedProducts = ref<RejectedProduct[]>([]);
const swapSource = ref<RejectedProduct | null>(null);

const swapModeActive = computed(() => swapSource.value !== null);

const reasonVariant = (reason: string) => {
    if (reason === 'no_horizontal_space') return 'warning' as const;
    if (reason === 'height_exceeds_shelf') return 'destructive' as const;
    return 'secondary' as const;
};

async function fetchRejected() {
    isLoading.value = true;

    try {
        const res = await fetch(rejectedProductsUrl());

        if (!res.ok) throw new Error('Falha ao buscar rejeitados');

        const json = await res.json();
        rejectedProducts.value = json.data ?? [];

        if (rejectedProducts.value.length > 0) {
            isOpen.value = true;
        }
    } catch {
        toast.error('Não foi possível carregar produtos rejeitados.');
    } finally {
        isLoading.value = false;
    }
}

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
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({
                rejected_product_id: source.id,
                layer_id: layerId,
            }),
        });

        if (!res.ok) throw new Error('Falha na troca');

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

// Intercept segment selection when swap mode is active
watch(
    () => selection.selectedItem.value,
    (item) => {
        if (!swapModeActive.value || !item) return;
        if (item.type !== 'segment') return;

        const segment = item.item as any;
        const layerId: string | undefined = segment?.layer?.id;

        if (!layerId) {
            toast.warning('Este segmento não tem layer. Clique em outro produto.');
            return;
        }

        void executeSwap(layerId);
    },
);

onMounted(() => {
    void fetchRejected();
});

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
                    class="flex w-36 flex-shrink-0 flex-col gap-1.5 rounded-lg border border-border bg-card p-2 transition-all"
                    :class="{
                        'ring-2 ring-amber-400': swapSource?.id === product.id,
                        'opacity-40': swapModeActive && swapSource?.id !== product.id,
                    }"
                >
                    <!-- Product image -->
                    <div class="flex h-16 items-center justify-center overflow-hidden rounded bg-muted">
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
                    <Badge :variant="reasonVariant(product.rejection_reason)" class="h-4 truncate px-1 text-xs">
                        {{ product.rejection_reason_label }}
                    </Badge>

                    <!-- Swap button -->
                    <Button
                        size="sm"
                        variant="outline"
                        class="mt-auto h-7 w-full gap-1 text-xs"
                        :disabled="isSwapping || (swapModeActive && swapSource?.id !== product.id)"
                        @click="swapSource?.id === product.id ? cancelSwapMode() : enterSwapMode(product)"
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
