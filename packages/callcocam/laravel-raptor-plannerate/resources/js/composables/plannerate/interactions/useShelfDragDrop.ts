import { inject, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useT } from '@/composables/useT';
import { draggingSegmentShelfId } from '../core/useGondolaState';
import { usePlanogramEditor } from '../core/usePlanogramEditor';
import {
    getMultipleProductsDragData,
    getProductDragData,
    getSegmentDragData,
    hasMultipleProductsData,
    hasProductData,
    hasSegmentData,
    isCopyModifier,
} from '../dnd/transfer';
import { useRejectedProductsStore } from './useRejectedProductsStore';

/**
 * Composable para gerenciar drag & drop na shelf (drop target).
 *
 * Aceita: produto único, múltiplos produtos (seleção), e segmentos vindos de
 * OUTRAS prateleiras (move; com Ctrl = copy). Segmentos da mesma prateleira são
 * ignorados aqui — o swap é tratado pelas drop zones do próprio Segment.vue.
 */
export function useShelfDragDrop(shelfId: string) {
    const editor = usePlanogramEditor();
    const rejectedStore = useRejectedProductsStore();
    const { t } = useT();
    const isDropTarget = ref(false);

    /**
     * Tipo do que está sendo arrastado sobre a shelf. Permite ao overlay de drop
     * adaptar a mensagem: a dica "Ctrl para copiar" só faz sentido para segmento
     * (produto é sempre copy — mostrar a dica ali confunde).
     */
    const dropKind = ref<'product' | 'segment' | null>(null);

    /**
     * Índice (entre segmentos ativos) onde o item cairá se solto agora — null
     * = fim da lista (comportamento legado). Passado às operações no drop.
     */
    const insertIndex = ref<number | null>(null);

    /**
     * X (px, relativo à área da shelf) da linha de inserção. `null` = sem
     * preview. Posicionada de forma ABSOLUTA para não perturbar o layout flex
     * dos segmentos (justify-evenly conta itens, então interleave deslocaria
     * tudo).
     */
    const insertLineX = ref<number | null>(null);

    // Injeta função para remover produto da lista quando usado
    const removeUsedProduct = inject<((productId: string) => void) | undefined>(
        'removeUsedProduct',
    );

    /**
     * Calcula o índice de inserção comparando o X do cursor com o meio de cada
     * segmento renderizado (`[data-segment-id]`) na área da shelf, e o X da
     * linha-guia. Sem cache: o nº de segmentos por prateleira é pequeno, então
     * o custo por dragover é baixo; atribui só-quando-muda (não dispara
     * reatividade a 60fps).
     */
    const updateInsertIndex = (event: DragEvent) => {
        const container = event.currentTarget as HTMLElement | null;

        if (!container) {
            return;
        }

        const containerLeft = container.getBoundingClientRect().left;
        const segmentEls = Array.from(
            container.querySelectorAll('[data-segment-id]'),
        );
        let idx = 0;

        for (const el of segmentEls) {
            const rect = el.getBoundingClientRect();

            if (event.clientX > rect.left + rect.width / 2) {
                idx++;
            } else {
                break;
            }
        }

        // Índice para a operação: null quando cai após o último (= append)
        const nextIndex = idx >= segmentEls.length ? null : idx;

        // X da linha-guia (sempre mostrada, inclusive no append):
        // borda entre o segmento anterior e o próximo.
        let nextLineX: number | null = null;

        if (segmentEls.length === 0) {
            nextLineX = 0;
        } else if (idx === 0) {
            nextLineX = segmentEls[0].getBoundingClientRect().left - containerLeft;
        } else {
            nextLineX =
                segmentEls[idx - 1].getBoundingClientRect().right - containerLeft;
        }

        if (insertIndex.value !== nextIndex) {
            insertIndex.value = nextIndex;
        }

        if (insertLineX.value !== nextLineX) {
            insertLineX.value = nextLineX;
        }
    };

    /**
     * Handler para dragover - define o tipo de operação
     */
    const handleDragOver = (event: DragEvent) => {
        event.preventDefault();

        if (!event.dataTransfer) {
            return;
        }

        if (hasProductData(event.dataTransfer) || hasMultipleProductsData(event.dataTransfer)) {
            // Produtos sempre são "copy" (adicionar à shelf)
            event.dataTransfer.dropEffect = 'copy';
            dropKind.value = 'product';
            isDropTarget.value = true;
            updateInsertIndex(event);

            return;
        }

        if (hasSegmentData(event.dataTransfer)) {
            // Verifica se o segment está na mesma shelf usando o estado global
            if (draggingSegmentShelfId.value === shelfId) {
                // Mesma shelf - não mostra área de drop (deixa as drop zones do Segment funcionarem)
                isDropTarget.value = false;
                dropKind.value = null;

                return;
            }

            // Segments de outras shelves: copy com modificador, move caso contrário.
            // Lido do evento ao vivo (o modificador costuma ser pressionado depois
            // que o arraste já começou — no macOS, sempre).
            event.dataTransfer.dropEffect = isCopyModifier(event) ? 'copy' : 'move';
            dropKind.value = 'segment';
            isDropTarget.value = true;
            updateInsertIndex(event);
        }
    };

    /**
     * Handler para dragleave.
     *
     * Guard de containment (mesmo padrão do Segment.vue): os filhos da área da
     * shelf (segmentos, overlay) disparam dragleave ao passar o mouse sobre
     * eles; sem o guard, o overlay "Solte aqui" pisca durante todo o arraste.
     * Só limpa quando o cursor realmente sai da área (relatedTarget fora dela
     * ou null — saída pela borda da janela).
     */
    const handleDragLeave = (event: DragEvent) => {
        const target = event.currentTarget as HTMLElement | null;
        const relatedTarget = event.relatedTarget as Node | null;

        if (target && relatedTarget && target.contains(relatedTarget)) {
            return;
        }

        isDropTarget.value = false;
        dropKind.value = null;
        insertIndex.value = null;
        insertLineX.value = null;
    };

    /**
     * Handler para drop - processa produtos e segments
     */
    const handleDrop = async (event: DragEvent) => {
        event.preventDefault();
        isDropTarget.value = false;
        dropKind.value = null;

        // Captura o índice de inserção do preview antes de resetar. `null`
        // (soltar após o último segmento) vira `undefined` = append.
        const dropIndex = insertIndex.value ?? undefined;

        insertIndex.value = null;
        insertLineX.value = null;

        if (!event.dataTransfer) {
            return;
        }

        const multipleProducts = getMultipleProductsDragData(event.dataTransfer);

        if (multipleProducts.length > 0) {
            // Múltiplos: mantém append (inserir todos no mesmo índice
            // inverteria a ordem entre eles)
            handleMultipleProductsDrop(multipleProducts);

            return;
        }

        const product = getProductDragData(event.dataTransfer);

        if (product) {
            addProduct(product, dropIndex);

            return;
        }

        const segment = getSegmentDragData(event.dataTransfer);

        if (segment) {
            // Mesma shelf: ignora (deixa as drop zones do Segment funcionarem)
            if (segment.sourceShelfId === shelfId) {
                return;
            }

            // O modificador vale no momento do drop; o flag gravado no dragstart
            // é só o fallback (browser que não propague modificadores no drop).
            handleSegmentDrop(
                segment.segmentId,
                isCopyModifier(event) || segment.isCopy,
                dropIndex,
            );
        }
    };

    /**
     * Processa drop de múltiplos produtos (a validação de status fica em
     * `addProduct`, ponto único por onde todos os drops de produto passam)
     */
    const handleMultipleProductsDrop = (products: Array<{ id: string; name?: string; status?: string }>) => {
        for (const product of products) {
            addProduct(product);
        }
    };

    /**
     * Adiciona um produto à shelf, removendo-o do painel e notificando o
     * módulo de rejeitados (caso tenha vindo de lá).
     *
     * Produtos em rascunho nunca entram em planograma (regra de negócio).
     * `status` ausente é permitido: payloads que não carregam o campo (ex.:
     * drawer de rejeitados monta o produto localmente) não devem ser barrados.
     */
    const addProduct = (
        product: { id: string; name?: string; status?: string },
        targetIndex?: number,
    ) => {
        if (product.status !== undefined && product.status !== 'published') {
            toast.warning(
                t('plannerate.editor.product_not_published', {
                    name: product.name ?? product.id,
                }),
            );

            return;
        }

        editor.addProductToShelf(
            shelfId,
            product.id,
            product,
            (addedProductId) => {
                removeUsedProduct?.(addedProductId);
                rejectedStore.notifyProductPlaced(addedProductId);
            },
            targetIndex,
        );
    };

    /**
     * Processa drop de segment vindo de outra prateleira (move ou copy).
     * `targetIndex` (preview de inserção) só se aplica a MOVE; copy mantém
     * append (fluxo/histórico próprio, escopo contido).
     */
    const handleSegmentDrop = (
        segmentId: string,
        isCopy: boolean,
        targetIndex?: number,
    ) => {
        const result = isCopy
            ? editor.copySegmentToShelf(segmentId, shelfId)
            : editor.moveSegmentToShelf(segmentId, shelfId, targetIndex);

        if (!result) {
            console.warn(`⚠️ Erro ao ${isCopy ? 'copiar' : 'mover'} segmento`);
        }
    };

    return {
        isDropTarget,
        dropKind,
        insertIndex,
        insertLineX,
        handleDragOver,
        handleDragLeave,
        handleDrop,
    };
}
