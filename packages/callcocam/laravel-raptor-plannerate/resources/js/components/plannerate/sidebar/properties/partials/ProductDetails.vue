<template>
    <div class="w-full space-y-4">
        <div>
            <h3 class="text-lg font-semibold">
                <Package class="mr-2 inline size-5 text-foreground" />
                {{ t('plannerate.analysis.selection.product_details') }}
            </h3>
        </div>
        <Separator />
        <!-- Abas: Detalhes (conteúdo atual) | Execução (retorno da loja) -->
        <Tabs v-if="product" default-value="details" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
                <TabsTrigger value="details">{{ t('plannerate.sidebar.product_details.tab_details') }}</TabsTrigger>
                <TabsTrigger value="execution">{{ t('plannerate.sidebar.product_details.tab_execution') }}</TabsTrigger>
            </TabsList>

            <!-- Aba Detalhes -->
            <TabsContent value="details" class="space-y-3 pt-3">
            <ProductImageCard :product="product" :show-upload-button="true" @upload="showImageUploadDialog = true" />

            <Separator />

            <!-- Card de rejeição -->
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

            <!-- Card de alocação (gerado pelo auto-planograma) -->
            <div v-if="allocationEntry && !rejectionDetails"
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
                    <p>
                        Frentes: <span class="font-medium">{{ allocationEntry.facings }}</span>
                    </p>
                    <p v-if="allocationEntry.abc_class">
                        Curva: <span class="font-medium">{{ allocationEntry.abc_class }}</span>
                    </p>
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

            <Separator v-if="rejectionDetails || allocationEntry" />

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
            </TabsContent>

            <!-- Aba Execução (retorno da loja: divergências + evidências) -->
            <TabsContent value="execution" class="pt-3">
                <ProductExecutionFeedback :product-id="product.id" :gondola-id="gondolaId" />
            </TabsContent>
        </Tabs>

        <!-- Dialog de Upload de Imagem -->
        <ProductImageUpload v-if="product" v-model:open="showImageUploadDialog" :product="product" />
    </div>
</template>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Package, Users } from 'lucide-vue-next';
import { computed, inject, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/core/usePlanogramSelection';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';
import ProductDimensionsEditor from './ProductDimensionsEditor.vue';
import ProductExecutionFeedback from './ProductExecutionFeedback.vue';
import ProductImageCard from './ProductImageCard.vue';
import ProductImageUpload from './ProductImageUpload.vue';
import ProductSalesSummary from './ProductSalesSummary.vue';

const editor = usePlanogramEditor();
const selection = usePlanogramSelection();
const page = usePage();
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

/**
 * Entrada de alocação do último relatório de geração — lida do flash Inertia
 * ou do localStorage (persistido pelo PanelLeftGeneration após cada geração).
 */
const allocationEntry = computed(() => {
    if (!product.value?.id) return null;

    // Flash tem prioridade (acaba de ser gerado)
    const flashAllocated: any[] = (page.props.flash as any)?.capacity_report?.explanation_report?.allocated ?? [];
    if (flashAllocated.length) {
        return flashAllocated.find((e: any) => e.product_id === product.value!.id) ?? null;
    }

    // Fallback: localStorage (persiste entre navegações)
    try {
        const gondolaId = (page.props as any)?.record?.id;
        if (!gondolaId) return null;
        const raw = localStorage.getItem(`plannerate_gen_report_${gondolaId}`);
        if (!raw) return null;
        const report = JSON.parse(raw);
        return (report?.allocated ?? []).find((e: any) => e.product_id === product.value!.id) ?? null;
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

// Estado do upload de imagem
const showImageUploadDialog = ref(false);

interface Props {
    item: Product;
}

const props = defineProps<Props>();
const product = computed(() => props.item);

// Gôndola aberta no editor — usada para buscar o retorno da loja do produto.
const gondolaId = computed(() => ((page.props as any)?.record?.id as string | undefined) ?? null);

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
