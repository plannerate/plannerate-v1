import type { Layer, Shelf } from '@/types/planogram';
import { ulid } from 'ulid';
import { toast } from 'vue-sonner';

const debouncedToast = (() => {
    let canShow = true;
    let timeout: ReturnType<typeof setTimeout> | null = null;

    return (message: string, delay = 5000) => {
        // Se pode mostrar, mostra imediatamente e bloqueia por um tempo
        if (canShow) {
            toast.error(message);
            canShow = false;

            // Libera para mostrar novamente após o delay
            if (timeout) clearTimeout(timeout);
            timeout = setTimeout(() => {
                canShow = true;
                timeout = null;
            }, delay);
        }
        // Se não pode mostrar, ignora (está no período de bloqueio)
    };
})();

/**
 * Valida se a largura total dos segmentos em uma prateleira, considerando uma mudança proposta
 * ou um segmento a ser adicionado, excede a largura da seção.
 *
 * @param shelf - O objeto da prateleira atual (com seus segmentos).
 * @param sectionWidth - A largura da seção que contém a prateleira.
 * @param changedLayerProductId - O ID do produto da camada sendo alterada (null se adicionando/removendo segmento).
 * @param proposedQuantity - A nova quantidade para a camada alterada (irrelevante se changedLayerProductId for null).
 * @param addedSegmentLayer - A camada de um novo segmento a ser adicionado (null se apenas alterando quantidade).
 * @returns Objeto com { isValid: boolean, totalWidth: number, sectionWidth: number }
 */
export function validateShelfWidth(
    shelf: Shelf,
    sectionWidth: number,
    changedLayerProductId: string | null,
    proposedQuantity: number,
    addedSegmentLayer: Layer | null = null,
): { isValid: boolean; totalWidth: number; sectionWidth: number } {
    let totalWidth = 0;
    // Filtrar segmentos que foram soft deleted (deleted_at não é null/undefined)
    const segmentsToCalculate = [...(shelf.segments || [])].filter(
        (seg) => !seg.deleted_at,
    );
    let temporarySegmentId: string | null = null;

    if (addedSegmentLayer) {
        temporarySegmentId = ulid();
        // Usar 'as any' para contornar a incompatibilidade de tipos estrita
        segmentsToCalculate.push({
            id: temporarySegmentId,
            layer: addedSegmentLayer,
            // Não precisa mais dos outros campos mínimos aqui
        } as any); // <-- CAST PARA ANY
    }

    for (const seg of segmentsToCalculate) {
        // Garantir que o ID é string ou é o nosso temporário
        if (typeof seg.id !== 'string' || seg.id === '') {
            if (seg.id !== temporarySegmentId) continue;
        }

        const currentLayer = seg.layer as Layer | undefined;

        if (!currentLayer) continue;
        const product = currentLayer.product;
        if (!product) continue;
        // Dimensões agora estão diretamente no produto (tabela dimensions foi removida)
        if (!product.width || product.width <= 0) continue;

        const productWidth = product.width;
        const quantity =
            changedLayerProductId && product.id === changedLayerProductId
                ? proposedQuantity
                : currentLayer.quantity;
        const spacing = currentLayer.spacing ?? 0;

        let segmentWidth = 0;
        if (quantity && quantity > 0) {
            segmentWidth = productWidth * quantity;
            if (quantity > 1) {
                segmentWidth += spacing * (quantity - 1);
            }
        }
        totalWidth += segmentWidth;
    }

    totalWidth = parseFloat(totalWidth.toFixed(2));
    sectionWidth = parseFloat(sectionWidth.toFixed(2));
    const isValid = totalWidth <= sectionWidth;

    if (!isValid) {
        debouncedToast(
            `A largura total dos produtos (${totalWidth} cm) excede a largura da seção (${sectionWidth} cm). Ajuste as quantidades ou remova produtos para continuar.`,
        );
    }

    return { isValid, totalWidth, sectionWidth };
}