<template>
    <div class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold">
                <Box class="mr-2 inline size-5 text-foreground" />
                {{ t('plannerate.sidebar.segment_details.layer_product') }}
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

            <!-- Card de alocação (gerado pelo auto-planograma) -->
            <div v-if="allocationEntry"
                class="space-y-2 rounded-lg border border-emerald-300/70 bg-emerald-50/70 p-3 text-xs text-emerald-900 dark:border-emerald-700/60 dark:bg-emerald-950/20 dark:text-emerald-200">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold">Detalhes da alocação</p>
                    <div class="flex gap-1">
                        <span v-if="allocationEntry.is_mandatory"
                            class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700 dark:bg-red-900 dark:text-red-300">
                            Obrigatório
                        </span>
                        <span v-if="allocationEntry.facings_expanded"
                            class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                            Expandido
                        </span>
                        <span v-if="allocationEntry.has_target_stock"
                            class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                            Est. alvo
                        </span>
                    </div>
                </div>
                <p v-if="allocationEntry.category_name">
                    Categoria: <span class="font-medium">{{ allocationEntry.category_name }}</span>
                </p>
                <div class="flex items-center gap-4">
                    <p>Frentes: <span class="font-medium">{{ allocationEntry.facings }}</span></p>
                    <p v-if="allocationEntry.abc_class">Curva: <span class="font-medium">{{ allocationEntry.abc_class }}</span></p>
                </div>
                <p class="flex items-center gap-1.5">
                    Zona:
                    <span :class="zoneDotClass[allocationEntry.zone]" class="inline-block h-2 w-2 rounded-full" />
                    <span class="font-medium">{{ zoneLabelMap[allocationEntry.zone] }}</span>
                </p>
                <p v-if="allocationEntry.role">
                    Papel: <span class="font-medium capitalize">{{ allocationEntry.role }}</span>
                </p>
            </div>

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
                    <Label for="layer-quantity">{{ t('plannerate.print.product_detail.fronts') }}</Label>
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
                    <Label for="layer-height">{{ t('plannerate.sidebar.segment_details.layer_height') }}</Label>
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
                    <Label for="layer-spacing">{{ t('plannerate.sidebar.section_details.hole_spacing') }}</Label>
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
                    <Label for="layer-alignment">{{ t('plannerate.sidebar.segment_details.alignment') }}</Label>
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
            <Label>{{ t('plannerate.sidebar.section_details.actions') }}</Label>
            <div class="grid grid-cols-2 gap-2">
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleMoveLeft"
                    :disabled="!segmentActions.canMoveLeft"
                    :tooltip="t('plannerate.sidebar.segment_details.move_left_tooltip')"
                >
                    <ArrowLeft class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.shelf_details.left') }}
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="outline"
                    size="sm"
                    @click="handleMoveRight"
                    :disabled="!segmentActions.canMoveRight"
                    :tooltip="t('plannerate.sidebar.segment_details.move_right_tooltip')"
                >
                    <ArrowRight class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.shelf_details.right') }}
                </ButtonWithTooltip>
                <ButtonWithTooltip
                    variant="destructive"
                    size="sm"
                    @click="handleDelete"
                    class="col-span-2"
                    :tooltip="t('plannerate.sidebar.segment_details.delete_tooltip')"
                >
                    <Trash2 class="mr-2 size-4" />
                    {{ t('plannerate.sidebar.section_details.delete') }}
                </ButtonWithTooltip>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Box, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { deleteImage } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Api/ProductImageController';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/core/usePlanogramSelection';
import { useSegmentActions } from '@/composables/plannerate/actions/useSegmentActions';
import { useT } from '@/composables/useT';
import type { Segment } from '@/types/planogram';
import { wayfinderPath } from '../../../../../libs/wayfinderPath';
import ProductDimensionsEditor from './ProductDimensionsEditor.vue';
import ProductImageCard from './ProductImageCard.vue';
import ProductImageUpload from './ProductImageUpload.vue';
import ProductSalesSummary from './ProductSalesSummary.vue';

interface Props {
    item: Segment | any; // Pode ser Segment ou Layer
}

const props = defineProps<Props>();

const editor = usePlanogramEditor();
const page = usePage();
const { t } = useT();
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

/** Entrada de alocação do último relatório de geração (flash ou localStorage) */
const allocationEntry = computed(() => {
    const pid = product.value?.id;
    if (!pid) return null;

    const flashAllocated: any[] = (page.props.flash as any)?.capacity_report?.explanation_report?.allocated ?? [];
    if (flashAllocated.length) {
        return flashAllocated.find((e: any) => e.product_id === pid) ?? null;
    }

    try {
        const gondolaId = editor.currentGondola.value?.id;
        if (!gondolaId) return null;
        const raw = localStorage.getItem(`plannerate_gen_report_${gondolaId}`);
        if (!raw) return null;
        const report = JSON.parse(raw);
        return (report?.allocated ?? []).find((e: any) => e.product_id === pid) ?? null;
    } catch {
        return null;
    }
});

const zoneLabelMap: Record<string, string> = { hot: 'Quente', cold: 'Fria', neutral: 'Neutra' };
const zoneDotClass: Record<string, string> = {
    hot: 'bg-orange-400',
    cold: 'bg-blue-400',
    neutral: 'bg-slate-300 dark:bg-slate-600',
};

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
            toast.success(t('plannerate.sidebar.product_image_upload.success.removed'));
        },
        onError: () => {
            toast.error(t('plannerate.sidebar.segment_details.remove_image_error'));
        },
    });
}
</script>
