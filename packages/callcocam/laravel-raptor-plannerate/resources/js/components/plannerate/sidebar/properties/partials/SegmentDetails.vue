<template>
    <div class="space-y-3">
        <!-- Cabeçalho + imagem compacta do produto -->
        <div class="flex items-center gap-2">
            <Box class="size-4 shrink-0 text-foreground" />
            <h3 class="text-base font-semibold leading-tight">
                {{ product?.name || t('plannerate.sidebar.segment_details.layer_product') }}
            </h3>
        </div>

        <!-- Card de alocação (gerado pelo auto-planograma) -->
        <div
            v-if="allocationEntry"
            class="space-y-1.5 rounded-lg border border-emerald-300/70 bg-emerald-50/70 p-3 text-xs text-emerald-900 dark:border-emerald-700/60 dark:bg-emerald-950/20 dark:text-emerald-200"
        >
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold">Detalhes da alocação</p>
                <div class="flex gap-1">
                    <span
                        v-if="allocationEntry.is_mandatory"
                        class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700 dark:bg-red-900 dark:text-red-300"
                    >Obrigatório</span>
                    <span
                        v-if="allocationEntry.facings_expanded"
                        class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                    >Expandido</span>
                    <span
                        v-if="allocationEntry.has_target_stock"
                        class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                    >Est. alvo</span>
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

        <!-- Abas de informações do produto -->
        <Tabs v-if="product" v-model="activeTab" class="w-full">
            <TabsList class="grid w-full grid-cols-5 text-xs">
                <TabsTrigger value="identification" class="px-1 text-[11px]">
                    {{ t('plannerate.sidebar.segment_details.tabs.identification') }}
                </TabsTrigger>
                <TabsTrigger value="position" class="px-1 text-[11px]">
                    {{ t('plannerate.sidebar.segment_details.tabs.position') }}
                </TabsTrigger>
                <TabsTrigger value="structure" class="px-1 text-[11px]">
                    {{ t('plannerate.sidebar.segment_details.tabs.structure') }}
                </TabsTrigger>
                <TabsTrigger value="performance" class="px-1 text-[11px]">
                    {{ t('plannerate.sidebar.segment_details.tabs.performance') }}
                </TabsTrigger>
                <TabsTrigger value="images" class="px-1 text-[11px]">
                    {{ t('plannerate.sidebar.segment_details.tabs.images') }}
                </TabsTrigger>
            </TabsList>

            <!-- Aba: Identificação + Dados Adicionais -->
            <TabsContent value="identification" class="mt-3">
                <TabIdentification :product="product" />
            </TabsContent>

            <!-- Aba: Posição + Dimensões Físicas -->
            <TabsContent value="position" class="mt-3">
                <TabPosition
                    :product="product"
                    :segment="segment"
                    :shelf="shelf"
                    @update:layer-field="handleUpdateLayer"
                    @update:dimension="handleUpdateProductDimension"
                />
            </TabsContent>

            <!-- Aba: Estrutura Mercadológica -->
            <TabsContent value="structure" class="mt-3">
                <TabStructure :product="product" />
            </TabsContent>

            <!-- Aba: Performance / Vendas -->
            <TabsContent value="performance" class="mt-3">
                <TabPerformance :product="product" />
            </TabsContent>

            <!-- Aba: Imagens -->
            <TabsContent value="images" class="mt-3">
                <TabImages
                    :product="product"
                    :show-upload-button="true"
                    @upload="showImageUploadDialog = true"
                    @delete="handleDeleteProductImage"
                />
            </TabsContent>
        </Tabs>

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

        <!-- Dialog de Upload de Imagem -->
        <ProductImageUpload v-model:open="showImageUploadDialog" :product="product" />
    </div>
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Box, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { deleteImage } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Api/ProductImageController';
import ButtonWithTooltip from '@/components/ui/ButtonWithTooltip.vue';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/core/usePlanogramSelection';
import { useSegmentActions } from '@/composables/plannerate/actions/useSegmentActions';
import { useT } from '@/composables/useT';
import type { Segment } from '@/types/planogram';
import { wayfinderPath } from '../../../../../libs/wayfinderPath';
import ProductImageUpload from './ProductImageUpload.vue';
import TabIdentification from './segment-tabs/TabIdentification.vue';
import TabImages from './segment-tabs/TabImages.vue';
import TabPerformance from './segment-tabs/TabPerformance.vue';
import TabPosition from './segment-tabs/TabPosition.vue';
import TabStructure from './segment-tabs/TabStructure.vue';

interface Props {
    item: Segment | any;
}

const props = defineProps<Props>();

const editor = usePlanogramEditor();
const page = usePage();
const { t } = useT();
const selection = usePlanogramSelection();

const showImageUploadDialog = ref(false);

const STORAGE_KEY = 'plannerate-segment-details-tab';
const activeTab = ref(localStorage.getItem(STORAGE_KEY) ?? 'identification');

watch(activeTab, (tab) => {
    localStorage.setItem(STORAGE_KEY, tab);
});
const deleteImageAction = deleteImage;

/**
 * Resolve segmento + prateleira em uma única travessia da árvore.
 * Memoizado pelo computed para evitar re-varreduras desnecessárias.
 */
const located = computed(() => {
    const searchId = props.item?.segment_id || props.item?.id;
    if (!searchId) return null;
    return editor.findSegmentById(searchId);
});

const segment = computed(() => located.value?.segment ?? props.item);
const shelf = computed(() => located.value?.shelf);

const segmentActions = useSegmentActions(
    () => segment.value,
    () => shelf.value,
);

const product = computed(() => segment.value?.layer?.product);

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
 * Atualiza dimensão do produto via editor (registra change e força reatividade).
 */
function handleUpdateProductDimension(
    dimension: 'width' | 'height' | 'depth',
    value: number,
) {
    if (!segment.value?.layer?.id || !product.value?.id) {
        return;
    }
    editor.updateProductDimension(segment.value.layer.id, dimension, value);
}

/**
 * Atualiza campo da layer via editor (registra change e força reatividade).
 */
function handleUpdateLayer(field: 'quantity' | 'height' | 'spacing' | 'alignment', value: any) {
    if (!segment.value?.layer?.id) {
        return;
    }
    editor.updateLayer(segment.value.layer.id, { [field]: value });
}

function handleMoveLeft() {
    segmentActions.moveLeft();
}

function handleMoveRight() {
    segmentActions.moveRight();
}

/**
 * Exclui o segmento diretamente (sem modal de confirmação).
 */
function handleDelete() {
    if (!segment.value?.id) {
        return;
    }
    if (shelf.value) {
        selection.selectItem('segment', segment.value.id, segment.value, { shelf: shelf.value });
    }
    selection.deleteSelected();
}

/**
 * Remove a imagem do produto via API.
 */
function handleDeleteProductImage() {
    if (!product.value?.id || !deleteImageAction) {
        return;
    }
    router.delete(wayfinderPath(deleteImageAction.url(product.value.id)), {
        onSuccess: () => toast.success(t('plannerate.sidebar.product_image_upload.success.removed')),
        onError: () => toast.error(t('plannerate.sidebar.segment_details.remove_image_error')),
    });
}
</script>
