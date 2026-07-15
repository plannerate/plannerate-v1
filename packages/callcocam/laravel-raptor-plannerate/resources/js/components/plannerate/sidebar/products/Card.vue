<template>
    <div class="group relative cursor-pointer rounded-lg border p-3 transition-all duration-200" :class="[
        isSelected && hasMultipleSelections
            ? 'border-orange-500 bg-orange-50 shadow-md ring-2 ring-orange-200 dark:bg-orange-950/20 dark:ring-orange-900'
            : isSelected
                ? 'border-blue-500 bg-blue-50 shadow-md ring-2 ring-blue-200 dark:bg-blue-950/20 dark:ring-blue-900'
                : 'border-border bg-card hover:bg-accent',
        isDragging ? 'cursor-grabbing opacity-50' : 'cursor-grab',
    ]" :draggable="isDraggable ? 'true' : 'false'" data-product-card @click.stop="handlerselectClick"
        @dblclick.stop="handlerDbClick" @dragstart="handleDragStart" @dragend="handleDragEnd">
        <!-- Badge de seleção -->
        <div v-if="isSelected"
            class="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm"
            :class="hasMultipleSelections ? 'bg-orange-500' : 'bg-blue-500'">
            ✓
        </div>

        <div class="flex items-start gap-3">
            <!-- Product Image -->
            <div class="size-12 shrink-0 overflow-hidden rounded bg-muted">
                <img v-if="hasImage" :src="product.image_url" :alt="product.name" class="size-full object-contain"
                    @error="onImageError" />
                <ProductPlaceholder v-else :width="48" :height="48" :name="product.name" :ean="product.ean" />
            </div>

            <!-- Product Info -->
            <div class="min-w-0 flex-1">
                <h4 class="truncate text-sm font-medium text-foreground">
                    {{ product.name }}
                </h4>
                <p class="text-xs text-muted-foreground">
                    {{ t('plannerate.analysis.results.ean') }}: {{ product.ean }}
                </p>
                <p v-if="product.height || product.width || product.depth" class="text-xs text-muted-foreground">
                    {{ t('plannerate.sidebar.product_card.dimensions') }}:
                    <span class="text-xs text-muted-foreground">
                        {{ product.height || 0 }}cm (A) x
                        {{ product.width || 0 }}cm (L) x
                        {{ product.depth || 0 }}cm (P)
                    </span>
                </p>
            </div>

            <!-- Badge: tem dimensão (has_dimensions) -->
            <div :title="getDimensionsTooltip()" class="shrink-0">
                <Badge :variant="product.has_dimensions ? 'default' : 'destructive'"
                    class="flex items-center gap-1 text-xs">
                    <CheckCircle2 v-if="product.has_dimensions" class="size-3" />
                    <AlertCircle v-else class="size-3" />
                    <span class="hidden sm:inline">
                        {{
                            product.has_dimensions
                                ? t('plannerate.sidebar.product_card.with_dimensions')
                                : t('plannerate.sidebar.product_card.without_dimensions')
                        }}
                    </span>
                </Badge>
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import { computed, inject, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/core/usePlanogramSelection';
import { setMultipleProductsDragData, setProductDragData } from '@/composables/plannerate/dnd/transfer';
import { useT } from '@/composables/useT';
import type { Product } from '@/types/planogram';
import ProductPlaceholder from '../../editor/ProductPlaceholder.vue';

const props = defineProps<{
    product: Product;
}>();

/**
 * `product.image_url` costuma vir preenchido bem antes do arquivo existir de
 * fato no storage (pipeline de pesquisa de dimensão grava a URL e só depois
 * baixa a imagem) — um `<img>` sem tratamento de erro mostrava o ícone quebrado
 * do navegador com o alt text vazando da miniatura de 48px. `@error` cobre esse
 * caso e qualquer 404/URL inválida, caindo no mesmo placeholder SVG do canvas.
 */
const imageFailed = ref(false);

watch(
    () => props.product.image_url,
    () => {
        imageFailed.value = false;
    },
);

const onImageError = (): void => {
    imageFailed.value = true;
};

const hasImage = computed(() => Boolean(props.product.image_url) && !imageFailed.value);

const { selectItem, toggleSelection, setMultiSelectEnabled } =
    usePlanogramSelection();
const selection = usePlanogramSelection();
const editor = usePlanogramEditor();
const { t } = useT();

// Injeta função para remover produto da lista quando usado
const removeUsedProduct = inject<((productId: string) => void) | undefined>(
    'removeUsedProduct',
);

// Verifica se este produto está selecionado
const isSelected = computed(() =>
    selection.isSelected('product', props.product.id),
);
// Produto é arrastável apenas se tiver dimensões
const isDraggable = computed(() => props.product.has_dimensions === true);
// Verifica se há múltiplas seleções ativas
const hasMultipleSelections = computed(() => selection.hasMultipleSelections());

// Tooltip baseado em has_dimensions
const getDimensionsTooltip = () => {
    if (props.product.has_dimensions) {
        return t('plannerate.sidebar.product_card.tooltip_ready');
    }

    const issues = [];

    if (!props.product.width || !props.product.height || !props.product.depth) {
        issues.push(t('plannerate.sidebar.product_card.issue_incomplete_dimensions'));
    }

    if (!props.product.ean) {
        issues.push(t('plannerate.sidebar.product_card.issue_no_ean'));
    }

    return issues.length
        ? t('plannerate.sidebar.product_card.tooltip_without_dimensions_with_issues', {
            issues: issues.join(', '),
        })
        : t('plannerate.sidebar.product_card.without_dimensions');
};

// Estado de dragging
const isDragging = ref(false);

const handleDragStart = (event: DragEvent) => {
    isDragging.value = true;

    if (event.dataTransfer) {
        // Verifica se há múltiplos produtos selecionados
        const selectedProducts = selection.getSelectedProducts();

        if (selectedProducts.length > 1) {
            // Modo múltiplo: inclui array de produtos
            setMultipleProductsDragData(
                event.dataTransfer,
                selectedProducts,
                t('plannerate.sidebar.product_card.selected_products', {
                    count: String(selectedProducts.length),
                }),
            );

            // Toast informativo
            toast.info(t('plannerate.sidebar.product_card.dragging_products', {
                count: String(selectedProducts.length),
            }), {
                duration: 1500,
            });
        } else {
            // Modo único: comportamento normal
            setProductDragData(
                event.dataTransfer,
                props.product,
                props.product.name || t('plannerate.sidebar.product_card.product_fallback'),
            );
        }

        // Define uma imagem de arrastar customizada
        const dragImage = event.currentTarget as HTMLElement;

        if (dragImage) {
            event.dataTransfer.setDragImage(dragImage, 20, 20);
        }
    }
};

const handleDragEnd = () => {
    isDragging.value = false;
};

const handlerselectClick = (event: MouseEvent) => {
    // Seleção IMEDIATA — sem aguardar para detectar duplo-clique. O atraso de
    // 250ms aplicava latência perceptível em todo clique. O duplo-clique
    // (handlerDbClick) continua funcionando: o browser dispara click → click →
    // dblclick; o primeiro click só seleciona (operação barata e idempotente).
    if (event.ctrlKey || event.metaKey) {
        // Ctrl/Cmd + Clique → Toggle seleção múltipla (adiciona/remove)
        toggleSelection('product', props.product.id, props.product);
    } else {
        // Clique simples → Seleção única
        selectItem('product', props.product.id, props.product);
    }
};

const handlerDbClick = () => {
    if (!props.product.has_dimensions) {
        toast.error(
            t('plannerate.sidebar.product_card.error_no_dimensions', {
                product: props.product.name || '',
            }),
        );

        return;
    }

    // Verifica se há uma shelf selecionada
    if (
        selection.selectedType.value === 'shelf' &&
        selection.selectedId.value
    ) {
        // Adiciona o produto à shelf selecionada criando segment → layer → product
        editor.addProductToShelf(
            selection.selectedId.value,
            props.product.id,
            props.product,
            // Callback: remove produto da lista quando adicionado com sucesso
            (productId) => {
                if (removeUsedProduct) {
                    removeUsedProduct(productId);
                }
            },
        );
    } else {
        console.warn(
            t('plannerate.sidebar.product_card.warn_select_shelf_first'),
        );
        toast.error(t('plannerate.sidebar.product_card.error_select_shelf_first'));
    }
};
onMounted(() => {
    setMultiSelectEnabled(true);
});
</script>
