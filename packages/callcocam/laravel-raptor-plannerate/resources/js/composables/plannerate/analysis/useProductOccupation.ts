import type { Gondola, Shelf } from '../../../types/planogram';
import { currentGondola } from '../core/useGondolaState';

/**
 * Resumo de ocupação de um produto num determinado escopo (prateleira ou planograma).
 */
export interface OccupationSummary {
    /** Número de segmentos (colocações físicas) do produto no escopo */
    segments: number;
    /** Total de frentes lado a lado (soma de layer.quantity) no escopo */
    facings: number;
    /** Total de unidades (capacidade) do produto no escopo */
    units: number;
}

/** Resumo vazio reutilizável. */
function emptySummary(): OccupationSummary {
    return { segments: 0, facings: 0, units: 0 };
}

/**
 * Quantos produtos cabem na profundidade da prateleira.
 * Espelha a fórmula usada nos cards de ocupação (mínimo 1 quando faltam dados).
 */
function itemsInDepth(productDepth: number, shelfDepth: number): number {
    if (!shelfDepth || !productDepth) {
        return 1;
    }

    return Math.max(1, Math.floor(shelfDepth / productDepth));
}

/**
 * Capacidade de um segmento em unidades = frentes × empilhamento × profundidade.
 * Usa a mesma semântica exibida no card "Ocupação do Segmento" (mínimo 1 na profundidade).
 */
function segmentUnits(segment: any, shelfDepth: number): number {
    const facings = Number(segment?.layer?.quantity ?? 1);
    const stacking = Number(segment?.quantity ?? 1);
    const depth = Number(segment?.layer?.product?.depth ?? 0);

    return facings * stacking * itemsInDepth(depth, shelfDepth);
}

/**
 * Capacidade de um segmento com a semântica estrita do estoque alvo:
 * retorna 0 quando não há profundidade do produto ou da prateleira
 * (preserva o comportamento anterior do indicador — sem profundidade = desconhecido).
 */
function segmentStockUnits(segment: any, shelfDepth: number): number {
    const facings = Number(segment?.layer?.quantity ?? 0);
    const stacking = Number(segment?.quantity ?? 0);
    const depth = Number(segment?.layer?.product?.depth ?? 0);

    if (!depth || !shelfDepth) {
        return 0;
    }

    return facings * stacking * Math.floor(shelfDepth / depth);
}

/**
 * Composable com helpers para agregar a ocupação de um produto na prateleira
 * e no planograma inteiro (todas as seções/prateleiras/segmentos da gôndola atual).
 *
 * Observação de escopo: no editor apenas a gôndola atual é carregada com a árvore
 * completa; as demais gôndolas do planograma vêm só com id/nome. Portanto
 * "planograma" aqui equivale à gôndola em edição (que é o escopo reativo às edições).
 */
export function useProductOccupation() {
    /**
     * Ocupação de um produto dentro de uma prateleira específica.
     * Soma todos os segmentos da prateleira que apontam para o mesmo produto.
     */
    function getShelfOccupation(
        productId: string | undefined,
        shelf: Shelf | null | undefined,
    ): OccupationSummary {
        if (!productId || !shelf?.segments?.length) {
            return emptySummary();
        }

        const shelfDepth = Number(shelf.shelf_depth ?? 0);
        const summary = emptySummary();

        for (const segment of shelf.segments) {
            if (segment?.deleted_at || segment?.layer?.deleted_at) {
                continue;
            }

            if (segment?.layer?.product?.id !== productId) {
                continue;
            }

            summary.segments += 1;
            summary.facings += Number(segment?.layer?.quantity ?? 1);
            summary.units += segmentUnits(segment, shelfDepth);
        }

        return summary;
    }

    /**
     * Ocupação de um produto em toda a gôndola informada (ou na gôndola atual).
     * Percorre seções → prateleiras → segmentos somando frentes e unidades.
     */
    function getPlanogramOccupation(
        productId: string | undefined,
        gondola?: Gondola | null,
    ): OccupationSummary {
        const target = gondola ?? currentGondola.value;

        if (!productId || !target?.sections?.length) {
            return emptySummary();
        }

        const summary = emptySummary();

        for (const section of target.sections) {
            if (section?.deleted_at) {
                continue;
            }

            for (const shelf of section.shelves ?? []) {
                if (shelf?.deleted_at) {
                    continue;
                }

                const shelfDepth = Number(shelf.shelf_depth ?? 0);

                for (const segment of shelf.segments ?? []) {
                    if (segment?.deleted_at || segment?.layer?.deleted_at) {
                        continue;
                    }

                    if (segment?.layer?.product?.id !== productId) {
                        continue;
                    }

                    summary.segments += 1;
                    summary.facings += Number(segment?.layer?.quantity ?? 1);
                    summary.units += segmentUnits(segment, shelfDepth);
                }
            }
        }

        return summary;
    }

    /**
     * Capacidade total do produto no planograma para comparação com o estoque alvo.
     * Usa a semântica estrita (0 quando falta profundidade) e soma todos os
     * segmentos do produto na gôndola. Retorna 0 quando o produto não é encontrado.
     */
    function getPlanogramStockCapacity(
        productId: string | undefined,
        gondola?: Gondola | null,
    ): number {
        const target = gondola ?? currentGondola.value;

        if (!productId || !target?.sections?.length) {
            return 0;
        }

        let total = 0;

        for (const section of target.sections) {
            if (section?.deleted_at) {
                continue;
            }

            for (const shelf of section.shelves ?? []) {
                if (shelf?.deleted_at) {
                    continue;
                }

                const shelfDepth = Number(shelf.shelf_depth ?? 0);

                for (const segment of shelf.segments ?? []) {
                    if (segment?.deleted_at || segment?.layer?.deleted_at) {
                        continue;
                    }

                    if (segment?.layer?.product?.id !== productId) {
                        continue;
                    }

                    total += segmentStockUnits(segment, shelfDepth);
                }
            }
        }

        return total;
    }

    return {
        getShelfOccupation,
        getPlanogramOccupation,
        getPlanogramStockCapacity,
    };
}
