import { inject, ref } from 'vue';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { draggingSegmentShelfId } from './useGondolaState';

/**
 * Composable para gerenciar drag & drop na shelf
 */
export function useShelfDragDrop(shelfId: string) {
    const editor = usePlanogramEditor();
    const isDropTarget = ref(false);
    
    // Injeta função para remover produto da lista quando usado
    const removeUsedProduct = inject<((productId: string) => void) | undefined>(
        'removeUsedProduct',
    );

    /**
     * Handler para dragover - define o tipo de operação
     */
    const handleDragOver = (event: DragEvent) => {
        event.preventDefault();

        if (!event.dataTransfer) {
return;
}

        // Verifica se é produto(s) ou segment
        const hasProduct = event.dataTransfer.types.includes(
            'application/x-product',
        );
        const hasMultipleProducts = event.dataTransfer.types.includes(
            'application/x-products-multiple',
        );
        const hasSegment = event.dataTransfer.types.includes(
            'application/x-segment-id',
        );

        if (hasProduct || hasMultipleProducts) {
            // Produtos sempre são "copy" (adicionar à shelf)
            event.dataTransfer.dropEffect = 'copy';
            isDropTarget.value = true;
        } else if (hasSegment) {
            // Verifica se o segment está na mesma shelf usando o estado global
            if (draggingSegmentShelfId.value === shelfId) {
                // Mesma shelf - não mostra área de drop (deixa as drop zones do Segment funcionarem)
                isDropTarget.value = false;

                return;
            }

            // Segments de outras shelves: copy se Ctrl pressionado, move caso contrário
            const isCopy = event.ctrlKey || event.metaKey;
            event.dataTransfer.dropEffect = isCopy ? 'copy' : 'move';
            isDropTarget.value = true;
        }
    };

    /**
     * Handler para dragleave
     */
    const handleDragLeave = () => {
        isDropTarget.value = false;
    };

    /**
     * Handler para drop - processa produtos e segments
     */
    const handleDrop = async (event: DragEvent) => {
        event.preventDefault();
        isDropTarget.value = false;

        if (!event.dataTransfer) {
return;
}

        // Verifica se são múltiplos produtos, produto único ou segmento
        const isMultiple = event.dataTransfer.getData('application/x-products-multiple') === 'true';
        const productsData = event.dataTransfer.getData('application/x-products');
        const productData = event.dataTransfer.getData('application/x-product');
        const segmentId = event.dataTransfer.getData(
            'application/x-segment-id',
        );
        const segmentShelfId = event.dataTransfer.getData(
            'application/x-segment-shelf-id',
        );

        if (isMultiple && productsData) {
            // Lógica de múltiplos produtos
            await handleMultipleProductsDrop(productsData);
        } else if (productData) {
            // Lógica de produto único
            await handleProductDrop(productData);
        } else if (segmentId) {
            // Verifica se é mesma shelf - se for, ignora (deixa as drop zones do Segment funcionarem)
            if (segmentShelfId === shelfId) {
                return;
            }

            // Lógica de segmento (entre shelves diferentes)
            await handleSegmentDrop(event, segmentId);
        }
    };

    /**
     * Processa drop de múltiplos produtos
     */
    const handleMultipleProductsDrop = async (productsData: string) => {
        const products = productsData ? JSON.parse(productsData) : [];

        if (!products || products.length === 0) {
            console.warn('⚠️ Nenhum produto encontrado');

            return;
        }

        // Adiciona cada produto à shelf
        for (const product of products) {
            // Só adiciona produtos publicados
            if (product.status !== 'published') {
                console.warn(`⚠️ Produto ${product.name} não está publicado, pulando...`);
                continue;
            }

            editor.addProductToShelf(
                shelfId,
                product.id,
                product,
                (addedProductId) => {
                    if (removeUsedProduct) {
                        removeUsedProduct(addedProductId);
                    }
                },
            );
        }
    };

    /**
     * Processa drop de produto único
     */
    const handleProductDrop = async (productData: string) => {
        const product = productData ? JSON.parse(productData) : null;

        if (!product) {
            console.warn('⚠️ Produto não encontrado');

            return;
        }

        const productId = product.id;

        // Adiciona o produto à shelf
        editor.addProductToShelf(
            shelfId,
            productId,
            product,
            (addedProductId) => {
                if (removeUsedProduct) {
                    removeUsedProduct(addedProductId);
                }
            },
        );
    };

    /**
     * Processa drop de segment
     */
    const handleSegmentDrop = async (event: DragEvent, segmentId: string) => {
        const isCopy =
            event.dataTransfer!.getData('application/x-is-copy') === 'true';

        if (isCopy) {
            // Copiar segmento
            const result = editor.copySegmentToShelf(segmentId, shelfId);

            if (!result) {
                console.warn('⚠️ Erro ao copiar segmento');
            }
        } else {
            // Mover segmento
            const result = editor.moveSegmentToShelf(segmentId, shelfId);

            if (!result) {
                console.warn('⚠️ Erro ao mover segmento');
            }
        }
    };

    return {
        isDropTarget,
        handleDragOver,
        handleDragLeave,
        handleDrop,
    };
}
