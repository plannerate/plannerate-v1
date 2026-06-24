<template>
    <div class="space-y-4">
        <!-- Frentes (editável) -->
        <div v-if="segment?.layer" class="space-y-2">
            <Label for="tab-layer-quantity">
                {{ t('plannerate.sidebar.segment_details.position.facings') }}
            </Label>
            <Input
                id="tab-layer-quantity"
                :model-value="segment.layer.quantity"
                @update:model-value="$emit('update:layer-field', 'quantity', Number($event))"
                type="number"
                min="1"
            />
        </div>

        <!-- Posicionamento: frentes × empilhamento × profundidade = total -->
        <div class="space-y-2">
            <Label>{{ t('plannerate.print.product_detail.positioning') }}</Label>
            <ProductPositioning
                :layer-quantity="segment?.layer?.quantity"
                :segment-quantity="segment?.quantity"
                :product-depth="product?.depth"
                :shelf-depth="shelf?.shelf_depth"
            />
        </div>

        <Separator />

        <!-- Seção: Dimensões Físicas -->
        <div class="space-y-3">
            <p class="text-xs font-semibold text-foreground">
                {{ t('plannerate.sidebar.segment_details.position.physical_title') }}
            </p>
            <ProductDimensionsEditor
                :height="Number(product?.height ?? 0)"
                :width="Number(product?.width ?? 0)"
                :depth="Number(product?.depth ?? 0)"
                @update:height="$emit('update:dimension', 'height', $event)"
                @update:width="$emit('update:dimension', 'width', $event)"
                @update:depth="$emit('update:dimension', 'depth', $event)"
            />
            <!-- Orientação (campo sem suporte ainda) -->
            <div class="flex items-center justify-between rounded-md bg-muted/30 px-3 py-1.5">
                <span class="text-xs text-muted-foreground">
                    {{ t('plannerate.sidebar.segment_details.position.orientation') }}
                </span>
                <span class="text-xs font-medium text-muted-foreground">—</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useT } from '@/composables/useT';
import type { Product, Segment, Shelf } from '@/types/planogram';
import ProductPositioning from '../../../../print/partials/ProductPositioning.vue';
import ProductDimensionsEditor from '../ProductDimensionsEditor.vue';

interface Props {
    /** Produto do segmento */
    product?: Product | null;
    /** Segmento selecionado (contém layer com quantity) */
    segment?: Segment | any;
    /** Prateleira do segmento (para profundidade) */
    shelf?: Shelf | null;
}

defineProps<Props>();
const { t } = useT();

defineEmits<{
    /** Atualiza campo da layer (ex.: 'quantity') */
    'update:layer-field': [field: string, value: any];
    /** Atualiza dimensão do produto (height, width, depth) */
    'update:dimension': [dimension: 'height' | 'width' | 'depth', value: number];
}>();
</script>
