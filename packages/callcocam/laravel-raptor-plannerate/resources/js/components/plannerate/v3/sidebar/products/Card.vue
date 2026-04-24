<template>
    <div class="group relative cursor-pointer rounded-lg border p-3 transition-all duration-200" :class="[
        isSelected && hasMultipleSelections
            ? 'border-orange-500 bg-orange-50 shadow-md ring-2 ring-orange-200 dark:bg-orange-950/20 dark:ring-orange-900'
            : isSelected
                ? 'border-blue-500 bg-blue-50 shadow-md ring-2 ring-blue-200 dark:bg-blue-950/20 dark:ring-blue-900'
                : 'border-border bg-card hover:bg-accent',
        isDragging ? 'cursor-grabbing opacity-50' : 'cursor-grab',
    ]" :draggable="isDraggable ? 'true' : 'false'" data-product-card @click.stop="handlerselectClick" @dblclick.stop="handlerDbClick"
        @dragstart="handleDragStart" @dragend="handleDragEnd">
        <!-- Badge de seleção -->
        <div v-if="isSelected"
            class="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm"
            :class="hasMultipleSelections ? 'bg-orange-500' : 'bg-blue-500'">
            ✓
        </div>

        <div class="flex items-start gap-3">
            <!-- Product Image -->
            <div class="size-12 shrink-0 overflow-hidden rounded bg-muted">
                <img v-if="product.image_url" :src="product.image_url" :alt="product.name"
                    class="size-full object-contain" />
            </div>

            <!-- Product Info -->
            <div class="min-w-0 flex-1">
                <h4 class="truncate text-sm font-medium text-foreground">
                    {{ product.name }}
                </h4>
                <p class="text-xs text-muted-foreground">
                    EAN: {{ product.ean }}
                </p>
                <p v-if="product.height || product.width || product.depth" class="text-xs text-muted-foreground">
                    Dimensões:
                    <span class="text-xs text-muted-foreground">
                        {{ product.height || 0 }}cm (A) x
                        {{ product.width || 0 }}cm (L) x
                        {{ product.depth || 0 }}cm (P)
                    </span>
                </p>
            </div>
            
            <!-- Badge: tem dimensão (has_dimensions) -->
            <div 
                :title="getDimensionsTooltip()"
                class="shrink-0"
            >
                <Badge
                    :variant="product.has_dimensions ? 'default' : 'destructive'"
                    class="flex items-center gap-1 text-xs"
                >
                    <CheckCircle2
                        v-if="product.has_dimensions"
                        class="size-3"
                    />
                    <AlertCircle
                        v-else
                        class="size-3"
                    />
                    <span class="hidden sm:inline">
                        {{ product.has_dimensions ? 'Com dimensão' : 'Sem dimensão' }}
                    </span>
                </Badge>
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">
import { usePlanogramEditor } from '@/composables/plannerate/v3/usePlanogramEditor';
import { usePlanogramSelection } from '@/composables/plannerate/v3/usePlanogramSelection';
import { Product } from '@/types/planogram';
import { computed, inject, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { toast } from 'vue-sonner';
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps<{
    product: Product;
}>();

const { selectItem, toggleSelection, setMultiSelectEnabled } =
    usePlanogramSelection();
const selection = usePlanogramSelection();
const editor = usePlanogramEditor();

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
        return 'Produto com dimensões, pronto para uso no planograma';
    }
    const issues = [];
    if (!props.product.width || !props.product.height || !props.product.depth) {
        issues.push('dimensões incompletas');
    }
    if (!props.product.ean) {
        issues.push('sem EAN');
    }
    return issues.length ? `Sem dimensão: ${issues.join(', ')}` : 'Sem dimensão';
};

// Timer para detectar duplo-clique e evitar seleção indesejada
let clickTimer: ReturnType<typeof setTimeout> | null = null;
const clickDelay = 250; // ms para detectar duplo-clique

// Estado de dragging
const isDragging = ref(false);

const handleDragStart = (event: DragEvent) => {
    isDragging.value = true;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
        
        // Verifica se há múltiplos produtos selecionados
        const selectedProducts = selection.getSelectedProducts();
        const hasMultiple = selectedProducts.length > 1;
        
        if (hasMultiple) {
            // Modo múltiplo: inclui array de produtos
            event.dataTransfer.setData(
                'application/x-products-multiple',
                'true',
            );
            event.dataTransfer.setData(
                'application/x-products',
                JSON.stringify(selectedProducts),
            );
            event.dataTransfer.setData(
                'text/plain',
                `${selectedProducts.length} produtos selecionados`,
            );
            
            // Toast informativo
            toast.info(`Arrastando ${selectedProducts.length} produtos`, {
                duration: 1500,
            });
        } else {
            // Modo único: comportamento normal
            event.dataTransfer.setData(
                'application/x-product-id',
                props.product.id,
            );
            event.dataTransfer.setData(
                'application/x-product',
                JSON.stringify(props.product),
            );
            event.dataTransfer.setData(
                'text/plain',
                props.product.name || 'Product',
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
    // Cancela timer anterior se existir
    if (clickTimer) {
        clearTimeout(clickTimer);
        clickTimer = null;
        return; // É um duplo-clique, não seleciona
    }

    // Inicia timer para aguardar possível segundo clique
    clickTimer = setTimeout(() => {
        // Após o delay, executa a seleção (não foi duplo-clique)
        if (event.ctrlKey || event.metaKey) {
            // Ctrl/Cmd + Clique → Toggle seleção múltipla (adiciona/remove)
            toggleSelection('product', props.product.id, props.product);
        } else {
            // Clique simples → Seleção única
            selectItem('product', props.product.id, props.product);
        }
        clickTimer = null;
    }, clickDelay);
};

const handlerDbClick = () => {
    if (!props.product.has_dimensions) {
        toast.error(
            `Produto "${props.product.name}" não tem dimensões e não pode ser adicionado à prateleira.`,
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
            '⚠️ Selecione uma prateleira antes de adicionar o produto',
        );
        toast.error('Selecione uma prateleira antes de adicionar o produto');
    }
};
onMounted(() => {
    setMultiSelectEnabled(true);
});
</script>
