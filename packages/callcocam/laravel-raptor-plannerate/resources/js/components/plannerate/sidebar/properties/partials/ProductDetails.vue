<template>
    <div class="w-full space-y-4">
        <div>
            <h3 class="text-lg font-semibold">
                <Package class="mr-2 inline size-5 text-foreground" />
                Detalhes do Produto
            </h3>
        </div>
        <Separator />
        <!-- Product Info -->
        <div v-if="product" class="w-full space-y-3">
            <ProductImageCard :product="product" :show-upload-button="true" @upload="showImageUploadDialog = true" />

            <Separator />

            <!-- Badge de edição múltipla -->
            <div v-if="hasMultipleSelections"
                class="rounded-lg border border-orange-500 bg-orange-50 p-3 dark:bg-orange-950/20">
                <div class="flex items-center gap-2 text-orange-700 dark:text-orange-400">
                    <Users class="size-4" />
                    <span class="text-sm font-medium">
                        Editando {{ selectedProducts.length }} produtos
                    </span>
                </div>
                <p class="mt-1 text-xs text-orange-600 dark:text-orange-500">
                    As alterações nas dimensões serão aplicadas a todos os produtos selecionados
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
import { Separator } from '@/components/ui/separator';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import type { Product } from '@/types/planogram';
import { Package, Users } from 'lucide-vue-next';
import { computed, inject, ref } from 'vue';
import { toast } from 'vue-sonner';
import ProductDimensionsEditor from './ProductDimensionsEditor.vue';
import ProductImageCard from './ProductImageCard.vue';
import ProductImageUpload from './ProductImageUpload.vue';
import ProductSalesSummary from './ProductSalesSummary.vue';

const editor = usePlanogramEditor();
const selection = usePlanogramSelection();

// Injeta função para recarregar lista de produtos após edição
const reloadProductsList = inject<(() => Promise<void>) | undefined>(
    'reloadProductsList',
);

// Verifica se há múltiplos produtos selecionados
const selectedProducts = computed(() => selection.getSelectedProducts());
const hasMultipleSelections = computed(() => selectedProducts.value.length > 1);

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

    if (!product.value?.id) return;

    // Se há múltiplos produtos selecionados, atualiza todos
    if (hasMultipleSelections.value && selectedProducts.value.length > 0) {
        const count = selectedProducts.value.length;

        toast.info(`Atualizando ${count} produtos selecionados`, {
            description: `${dimension === 'width' ? 'Largura' : dimension === 'height' ? 'Altura' : 'Profundidade'}: ${value}cm`,
            duration: 2000,
        });

        editor.updateMultipleProductsDimensionsDirectly(
            selectedProducts.value,
            dimension,
            value,
            reloadProductsList ? async () => {
                await reloadProductsList();
                toast.success(`${count} produtos atualizados com sucesso!`);
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
            } : undefined
        );
    }
}
</script>
