<template>
    <div class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold">
                <Box class="mr-2 inline size-5 text-foreground" />
                Layer / Produto
            </h3>
        </div>

        <Separator />

        <!-- Product Info -->
        <div v-if="product" class="space-y-3">
            <ProductImageCard
                :product="product"
                :show-upload-button="true"
                @upload="showImageUploadDialog = true"
                @delete="handleDeleteProductImage"
            />

            <Separator />

            <!-- Product Dimensions -->
            <ProductDimensionsEditor
                :height="Number(product.height)"
                :width="Number(product.width)"
                :depth="Number(product.depth)"
                @update:height="handleUpdateProductDimension('height', $event)"
                @update:width="handleUpdateProductDimension('width', $event)"
                @update:depth="handleUpdateProductDimension('depth', $event)"
            />

            <Separator />
        </div>

        <!-- Dialog de Upload de Imagem -->
        <ProductImageUpload
            v-model:open="showImageUploadDialog"
            :product="product"
        />

        <!-- Informações da Layer se existir -->
        <div v-if="segment.layer" class="mt-4 space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="layer-quantity">Frentes</Label>
                    <Input
                        id="layer-quantity"
                        :model-value="segment.layer.quantity"
                        @update:model-value="
                            handleUpdateLayer('quantity', Number($event))
                        "
                        type="number"
                        min="1"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="layer-height">Altura Layer (cm)</Label>
                    <Input
                        id="layer-height"
                        :model-value="segment.layer.height"
                        @update:model-value="
                            handleUpdateLayer('height', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-2">
                    <Label for="layer-spacing">Espaçamento (cm)</Label>
                    <Input
                        id="layer-spacing"
                        :model-value="segment.layer.spacing"
                        @update:model-value="
                            handleUpdateLayer('spacing', Number($event))
                        "
                        type="number"
                        step="0.1"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="layer-alignment">Alinhamento</Label>
                    <Input
                        id="layer-alignment"
                        :model-value="segment.layer.alignment"
                        @update:model-value="
                            handleUpdateLayer('alignment', $event)
                        "
                    />
                </div>
            </div>
        </div>
        <!-- Product Sales Summary -->
        <ProductSalesSummary :product-id="product.id" />
        <Separator />

        <!-- Botões de ação -->
        <div class="space-y-2">
            <Label>Ações</Label>
            <div class="grid grid-cols-2 gap-2">
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleMoveLeft"
                    :disabled="!segmentActions.canMoveLeft"
                    tooltip="Mover para esquerda (Ctrl+ ←)"
                >
                    <ArrowLeft class="mr-2 size-4" />
                    Esquerda
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleMoveRight"
                    :disabled="!segmentActions.canMoveRight"
                    tooltip="Mover para direita (Ctrl+ →)"
                >
                    <ArrowRight class="mr-2 size-4" />
                    Direita
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="destructive"
                    size="sm"
                    @click="handleDelete"
                    class="col-span-2"
                    tooltip="Excluir segmento (Del)"
                >
                    <Trash2 class="mr-2 size-4" />
                    Excluir
                </ButtonWithTooltip>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Box, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { wayfinderPath } from '@/libs/wayfinderPath';
import { deleteImage } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Api/ProductImageController';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import { useSegmentActions } from '@/composables/plannerate/useSegmentActions';
import type { Segment } from '@/types/planogram';
import ProductDimensionsEditor from './ProductDimensionsEditor.vue';
import ProductImageCard from './ProductImageCard.vue';
import ProductImageUpload from './ProductImageUpload.vue';
import ProductSalesSummary from './ProductSalesSummary.vue';

interface Props {
    item: Segment | any; // Pode ser Segment ou Layer
}

const props = defineProps<Props>();

const editor = usePlanogramEditor();
const selection = usePlanogramSelection();

// Estado do upload de imagem
const showImageUploadDialog = ref(false);
const deleteImageAction = deleteImage;

// Busca o segmento diretamente do gondola para garantir reatividade
const segment = computed(() => {
    const currentGondola = editor.currentGondola.value;

    if (!currentGondola?.sections || !props.item?.id) {
return props.item;
}

    // Se o item for uma layer, busca pelo segment_id
    const searchId = props.item.segment_id || props.item.id;

    // Busca o segmento na estrutura do gondola
    for (const section of currentGondola.sections) {
        if (!section.shelves) {
continue;
}

        for (const shelf of section.shelves) {
            if (!shelf.segments) {
continue;
}

            const seg = shelf.segments.find((s: any) => s.id === searchId);

            if (seg) {
return seg;
}
        }
    }

    // Fallback para o item original
    return props.item;
});

// Busca a prateleira do segmento
const shelf = computed(() => {
    const found = editor.findSegmentById(segment.value.id);

    return found?.shelf;
});

// Usa composable compartilhado para ações de segmento
const segmentActions = useSegmentActions(
    () => segment.value,
    () => shelf.value,
);

const product = computed(() => {
    return segment.value?.layer?.product;
});
 
/**
 * Atualiza dimensão do produto de forma reativa
 */
function handleUpdateProductDimension(
    dimension: 'width' | 'height' | 'depth',
    value: number,
) {
    if (!segment.value?.layer?.id || !product.value?.id) {
return;
}

    // Usa o método do editor (já registra change e força reatividade)
    editor.updateProductDimension(segment.value.layer.id, dimension, value);
}

/**
 * Atualiza propriedade da layer de forma reativa
 */
function handleUpdateLayer(
    field: 'quantity' | 'height' | 'spacing' | 'alignment',
    value: any,
) {
    if (!segment.value?.layer?.id) {
return;
}

    // Usa o método do editor (já registra change e força reatividade)
    editor.updateLayer(segment.value.layer.id, { [field]: value });
}

/**
 * Move segmento para esquerda (usa composable compartilhado)
 */
function handleMoveLeft() {
    segmentActions.moveLeft();
}

/**
 * Move segmento para direita (usa composable compartilhado)
 */
function handleMoveRight() {
    segmentActions.moveRight();
}

/**
 * Exclui o segmento (mesma lógica do Delete)
 * Segmentos são deletados diretamente sem modal de confirmação
 */
function handleDelete() {
    if (!segment.value?.id) {
        return;
    }

    // Garante que o segmento está selecionado antes de qualquer ação
    if (shelf.value) {
        selection.selectItem('segment', segment.value.id, segment.value, {
            shelf: shelf.value,
        });
    }

    // Deleta diretamente sem confirmação (mesmo comportamento do keyboard handler)
    selection.deleteSelected();
}

/**
 * Remove a imagem do produto
 */
function handleDeleteProductImage() {
    if (!product.value?.id) {
return;
}

    if (!deleteImageAction) {
return;
}

    router.delete(wayfinderPath(deleteImageAction.url(product.value.id)), {
        onSuccess: () => {
            toast.success('Imagem removida com sucesso!');
        },
        onError: () => {
            toast.error('Erro ao remover imagem');
        },
    });
}
</script>
