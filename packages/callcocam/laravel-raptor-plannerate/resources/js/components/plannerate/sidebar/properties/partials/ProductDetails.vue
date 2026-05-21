<template>
    <div class="w-full space-y-4">
        <div>
            <h3 class="text-lg font-semibold">
                <Package class="mr-2 inline size-5 text-foreground" />
                {{ t('plannerate.analysis.selection.product_details') }}
            </h3>
        </div>
        <Separator />
        <!-- Product Info -->
        <div v-if="product" class="w-full space-y-3">
            <ProductImageCard :product="product" :show-upload-button="true" @upload="showImageUploadDialog = true" />

            <Separator />

            <div v-if="rejectionDetails"
                class="space-y-2 rounded-lg border border-amber-300/70 bg-amber-50/70 p-3 text-xs text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/20 dark:text-amber-200">
                <p class="text-sm font-semibold">Detalhes da rejeição</p>
                <p>
                    Motivo:
                    <span class="font-medium">{{ rejectionDetails.reasonLabel }}</span>
                </p>
                <p v-if="rejectionDetails.moduleNumber !== null || rejectionDetails.shelfOrders.length > 0">
                    Slot:
                    <span class="font-medium">
                        M{{ rejectionDetails.moduleNumber ?? '-' }} •
                        <template v-if="rejectionDetails.shelfOrders.length > 1">
                            {{ rejectionDetails.shelfOrders.map(s => 'P' + s).join(', ') }}
                        </template>
                        <template v-else>
                            P{{ rejectionDetails.shelfOrders[0] ?? rejectionDetails.shelfOrder ?? '-' }}
                        </template>
                    </span>
                </p>
                <p v-if="rejectionDetails.categoryName">
                    Agrupamento: <span class="font-medium">{{ rejectionDetails.categoryName }}</span>
                </p>
            </div>

            <Separator v-if="rejectionDetails" />

            <!-- Badge de edição múltipla -->
            <div v-if="hasMultipleSelections"
                class="rounded-lg border border-orange-500 bg-orange-50 p-3 dark:bg-orange-950/20">
                <div class="flex items-center gap-2 text-orange-700 dark:text-orange-400">
                    <Users class="size-4" />
                    <span class="text-sm font-medium">
                        {{ t('plannerate.sidebar.product_details.editing_products', {
                            count:
                                String(selectedProducts.length) }) }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-orange-600 dark:text-orange-500">
                    {{ t('plannerate.sidebar.product_details.multiple_edit_hint') }}
                </p>
            </div>

            <!-- Product Dimensions -->
            <ProductDimensionsEditor :height="Number(product.height)" :width="Number(product.width)"
                :depth="Number(product.depth)" @update:height="handleUpdateProductDimension('height', $event)"
                @update:width="handleUpdateProductDimension('width', $event)"
                @update:depth="handleUpdateProductDimension('depth', $event)" />

            <Separator />

            <!-- Product Sales Summary -->
            <ProductSalesSummary :product-id="product.id" />
        </div>

        <!-- Dialog de Upload de Imagem -->
        <ProductImageUpload v-model:open="showImageUploadDialog" :product="product" />
    </div>
</template>

<script setup lang="ts">
import { Package, Users } from 'lucide-vue-next';
import { computed, inject, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Separator } from '@/components/ui/separator';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';
import ProductDimensionsEditor from './ProductDimensionsEditor.vue';
import ProductImageCard from './ProductImageCard.vue';
import ProductImageUpload from './ProductImageUpload.vue';
import ProductSalesSummary from './ProductSalesSummary.vue';

const editor = usePlanogramEditor();
const selection = usePlanogramSelection();
const { t } = useT();

// Injeta função para recarregar lista de produtos após edição
const reloadProductsList = inject<(() => Promise<void>) | undefined>(
    'reloadProductsList',
);

// Verifica se há múltiplos produtos selecionados
const selectedProducts = computed(() => selection.getSelectedProducts());
const hasMultipleSelections = computed(() => selectedProducts.value.length > 1);
const selectedItem = computed(() => selection.selectedItem.value);

const rejectionDetails = computed(() => {
    const context = selectedItem.value?.context as
        | {
            source?: string;
            rejection?: {
                reason?: string;
                reason_label?: string;
                category_name?: string | null;
                category_id?: string | null;
                module_number?: number | null;
                shelf_order?: number | null;
                rejected_shelf_orders?: number[] | null;
            };
        }
        | undefined;

    if (context?.source !== 'rejected_products' || !context.rejection) {
        return null;
    }

    const shelfOrders: number[] =
        Array.isArray(context.rejection.rejected_shelf_orders) && context.rejection.rejected_shelf_orders.length > 0
            ? context.rejection.rejected_shelf_orders
            : context.rejection.shelf_order !== null && context.rejection.shelf_order !== undefined
              ? [context.rejection.shelf_order]
              : [];

    return {
        reason: context.rejection.reason ?? 'unknown',
        reasonLabel: context.rejection.reason_label ?? 'Sem motivo',
        categoryName: context.rejection.category_name ?? null,
        categoryId: context.rejection.category_id ?? null,
        moduleNumber: context.rejection.module_number ?? null,
        shelfOrder: context.rejection.shelf_order ?? null,
        shelfOrders,
    };
});

// Estado do upload de imagem
const showImageUploadDialog = ref(false);

interface Props {
    item: Product;
}

const props = defineProps<Props>();
const product = computed(() => props.item);

/**
 * Atualiza dimensão do produto de forma reativa e persistente
 * Se múltiplos produtos estiverem selecionados, aplica a todos
 */
function handleUpdateProductDimension(
    dimension: 'width' | 'height' | 'depth',
    value: number,
) {

    if (!product.value?.id) {
        return;
    }

    // Se há múltiplos produtos selecionados, atualiza todos
    if (hasMultipleSelections.value && selectedProducts.value.length > 0) {
        const count = selectedProducts.value.length;

        toast.info(t('plannerate.sidebar.product_details.updating_products', { count: String(count) }), {
            description: t('plannerate.sidebar.product_details.dimension_update', {
                dimension:
                    dimension === 'width'
                        ? t('plannerate.print.product_detail.width')
                        : dimension === 'height'
                            ? t('plannerate.print.product_detail.height')
                            : t('plannerate.print.product_detail.depth'),
                value: String(value),
            }),
            duration: 2000,
        });

        editor.updateMultipleProductsDimensionsDirectly(
            selectedProducts.value,
            dimension,
            value,
            reloadProductsList ? async () => {
                await reloadProductsList();
                toast.success(t('plannerate.sidebar.product_details.updated_success', { count: String(count) }));
            } : undefined
        );
    } else {
        // Atualiza apenas o produto atual
        editor.updateProductDimensionDirectly(
            product.value,
            dimension,
            value,
            reloadProductsList ? async () => {
                await reloadProductsList();
                toast.success(t('plannerate.sidebar.product_details.updated_success', { count: '1' }));
            } : undefined
        );
    }
}
</script>
