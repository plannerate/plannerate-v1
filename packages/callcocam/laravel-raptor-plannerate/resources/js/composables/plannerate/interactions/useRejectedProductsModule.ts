// ============================================================================
// MÓDULO DE PRODUTOS REJEITADOS
// ============================================================================
// Gerencia o ciclo de vida dos produtos rejeitados na geração do planograma:
// carregamento, remoção, posicionamento e troca na gôndola.
// ============================================================================

import { toast } from 'vue-sonner';
import { useT } from '@/composables/useT';
import type { ChangeType } from '../core/usePlanogramChanges';
import type { usePlanogramHistory } from '../core/usePlanogramHistory';
import type { EntityType } from '@/types/planogram';
import type { useShelfOperations } from '../operations/useShelfOperations';
import {
    currentGondola,
    isLoadingRejectedProducts,
    rejectedProducts,
    type RejectedProduct,
} from '../core/useGondolaState';
import {
    findSegmentByLayerId,
    findShelfById,
} from '../core/useLookupHelpers';
import { updateSegmentReactive } from '../core/useReactivityHelpers';

/** Shape mínimo de commitOptimistic para evitar acoplamento circular */
type CommitFn = <T>(params: {
    apply: () => T;
    historySnapshot?: any;
    change?: any;
    autoSave?: boolean;
    onSaved?: () => void | Promise<void>;
}) => T | null;

type RecordChangeFn = (change: {
    type: ChangeType;
    entityType: EntityType;
    entityId: string;
    data: Record<string, any>;
}) => void;

export function useRejectedProductsModule(
    commitOptimistic: CommitFn,
    recordChange: RecordChangeFn,
    history: Pick<ReturnType<typeof usePlanogramHistory>, 'cloneState' | 'patchCurrentBeforeState'>,
    addProductToShelf: ReturnType<typeof useShelfOperations>['addProductToShelf'],
) {
    const { t } = useT();

    // ========================================================================
    // HELPERS INTERNOS
    // ========================================================================

    function rejectedApiUrl(gondolaId: string, path: string): string {
        return `/api/gondolas/${gondolaId}/${path}`;
    }

    function csrfToken(): string {
        return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    }

    // ========================================================================
    // CARREGAMENTO
    // ========================================================================

    async function fetchRejectedProducts(gondolaId?: string): Promise<void> {
        const id = gondolaId ?? currentGondola.value?.id;

        if (!id) {
            return;
        }

        isLoadingRejectedProducts.value = true;

        try {
            const res = await fetch(rejectedApiUrl(id, 'rejected-products'));

            if (!res.ok) {
                throw new Error('request_failed');
            }

            const json = await res.json();
            rejectedProducts.value = (json.data ?? []) as RejectedProduct[];
        } catch {
            toast.error('Não foi possível carregar produtos rejeitados.');
        } finally {
            isLoadingRejectedProducts.value = false;
        }
    }

    // ========================================================================
    // REMOÇÃO
    // ========================================================================

    function removeRejectedProductLocally(rejectedId: string): void {
        rejectedProducts.value = rejectedProducts.value.filter((r) => r.id !== rejectedId);
    }

    function deleteRejectedProductFromBackend(rejectedId: string): void {
        const id = currentGondola.value?.id;

        if (!id) {
            return;
        }

        void fetch(rejectedApiUrl(id, `rejected-products/${rejectedId}`), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });
    }

    /**
     * Remove o produto rejeitado da lista in-memory e dispara DELETE no backend
     */
    function deleteRejectedProduct(rejectedId: string): void {
        removeRejectedProductLocally(rejectedId);
        deleteRejectedProductFromBackend(rejectedId);
    }

    // ========================================================================
    // POSICIONAMENTO E TROCA
    // ========================================================================

    /**
     * Posiciona um produto rejeitado numa prateleira via operação atômica.
     * Usa segment_copy para que o undo restaure os segmentos + produto rejeitado.
     */
    function placeFromRejected(source: RejectedProduct, shelfId: string): boolean {
        const shelfData = findShelfById(shelfId);

        if (!shelfData) {
            return false;
        }

        const productObj = {
            id: source.product_id,
            name: source.product_name,
            ean: source.ean ?? undefined,
            image_url: source.image_url ?? undefined,
            width: source.product_width,
            height: source.product_height,
            depth: null,
            status: 'published',
            has_dimensions: !!(source.product_width && source.product_height),
        };

        const beforeShelfSegments = history.cloneState(shelfData.shelf.segments);

        const result = commitOptimistic({
            apply: () => {
                const placed = addProductToShelf(
                    shelfId,
                    source.product_id,
                    productObj,
                    undefined,
                    recordChange,
                    t('plannerate.editor.product_does_not_fit_selected_shelf'),
                );

                if (placed) {
                    removeRejectedProductLocally(source.id);
                    deleteRejectedProductFromBackend(source.id);
                }

                return placed;
            },
            historySnapshot: {
                type: 'segment_copy',
                shelfId,
                sectionId: shelfData.section.id,
                description: `Posicionar produto rejeitado na prateleira`,
                beforeState: {
                    targetShelfId: shelfId,
                    targetShelfSegments: beforeShelfSegments,
                    _rejectedProduct: source,
                },
                afterState: null,
            },
        });

        return result !== null;
    }

    /**
     * Retroativamente vincula um produto rejeitado ao snapshot mais recente do
     * histórico. Garante que undo restaure o produto à lista.
     */
    function patchRejectedProductToLastAction(source: RejectedProduct): void {
        history.patchCurrentBeforeState({ _rejectedProduct: source });
    }

    /**
     * Troca um produto na gôndola por um produto rejeitado.
     * O POST ao backend é aguardado antes de qualquer mutação local.
     */
    async function swapRejectedProduct(source: RejectedProduct, layerId: string): Promise<boolean> {
        const gondolaId = currentGondola.value?.id;

        if (!gondolaId) {
            return false;
        }

        const found = findSegmentByLayerId(layerId);

        if (!found || !found.segment.layer) {
            return false;
        }

        try {
            const res = await fetch(rejectedApiUrl(gondolaId, 'swap-product'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ rejected_product_id: source.id, layer_id: layerId }),
            });

            if (!res.ok) {
                throw new Error('request_failed');
            }

            const beforeLayerState = history.cloneState({
                ...found.segment.layer,
                _rejectedProduct: source,
            });

            const newProduct = {
                id: source.product_id,
                name: source.product_name,
                ean: source.ean,
                image_url: source.image_url,
                width: source.product_width,
                height: source.product_height,
            };

            commitOptimistic({
                apply: () => {
                    updateSegmentReactive(
                        found.section,
                        found.shelfIndex,
                        found.segmentIndex,
                        {
                            layer: {
                                ...found.segment.layer,
                                product_id: source.product_id,
                                ean: source.ean,
                                product: newProduct,
                            },
                        },
                    );
                    removeRejectedProductLocally(source.id);
                },
                historySnapshot: {
                    type: 'layer_update',
                    layerId,
                    sectionId: found.section.id,
                    description: `Trocar produto rejeitado na gôndola`,
                    beforeState: beforeLayerState,
                    afterState: null,
                },
                change: {
                    type: 'layer_update',
                    entityType: 'layer',
                    entityId: layerId,
                    data: {
                        product_id: source.product_id,
                        ean: source.ean,
                        product: newProduct,
                    },
                },
            });

            await fetchRejectedProducts(gondolaId);

            return true;
        } catch {
            return false;
        }
    }

    return {
        fetchRejectedProducts,
        removeRejectedProductLocally,
        deleteRejectedProduct,
        placeFromRejected,
        patchRejectedProductToLastAction,
        swapRejectedProduct,
    };
}
