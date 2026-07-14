import { computed, shallowRef } from 'vue';
import type { BcgAxis, BcgQuadrant, BcgResult, BcgSpaceAction } from '@/components/plannerate/analysis/bcg/types';
import { createEanAnalysisStore } from './useEanAnalysisStore';

/**
 * O que o selo da gôndola precisa saber sobre um produto.
 *
 * Guarda a AÇÃO junto do quadrante de propósito: um selo que só diz o quadrante é
 * decoração — o que o repositor precisa ver na frente do produto é "esta aqui pede
 * mais frente" / "esta aqui está ocupando espaço demais".
 *
 * Os eixos vêm junto porque o rótulo do quadrante é derivado deles (ver bcg/labels.ts).
 */
export interface BcgBadgeData {
    quadrant: BcgQuadrant;
    acao_espaco: BcgSpaceAction;
    x_axis: BcgAxis;
    y_axis: BcgAxis;
}

const useBcgStore = createEanAnalysisStore<BcgBadgeData>('bcg-analysis');

/**
 * Filtro de quadrante ativo na gôndola. Escopo de MÓDULO (não do composable) para
 * ser um singleton: o painel de análises e o selo de cada segmento precisam ler o
 * mesmo estado. Set vazio = sem filtro (mostra todos).
 */
const _activeQuadrants = shallowRef<Set<BcgQuadrant>>(new Set());

/**
 * Store dos quadrantes BCG por EAN, para o selo na frente do produto.
 *
 * Uso:
 *   - PerformanceBcgTab / DropdownPerformance → alimentam via setBcgQuadrants
 *   - Segment / BcgBadge                      → leem via getBcgData
 */
export function useBcgAnalysis() {
    const store = useBcgStore();

    function setBcgQuadrants(items: BcgResult[]) {
        store.setBatch(
            items
                .filter((item) => item.ean && item.quadrant)
                .map((item) => ({
                    ean: item.ean,
                    value: {
                        quadrant: item.quadrant,
                        acao_espaco: item.acao_espaco,
                        x_axis: item.x_axis,
                        y_axis: item.y_axis,
                    },
                })),
        );
    }

    function getBcgData(ean: string | undefined): BcgBadgeData | undefined {
        return store.get(ean);
    }

    function hasBcgData(ean: string | undefined): boolean {
        return store.has(ean);
    }

    function clearBcgQuadrants() {
        store.clear();
        _activeQuadrants.value = new Set();
    }

    /** Sem filtro (conjunto vazio) → todos os quadrantes aparecem. */
    function isQuadrantActive(quadrant: BcgQuadrant | undefined): boolean {
        if (!quadrant) {
return false;
}

        return _activeQuadrants.value.size === 0 || _activeQuadrants.value.has(quadrant);
    }

    function toggleQuadrantFilter(quadrant: BcgQuadrant) {
        const next = new Set(_activeQuadrants.value);

        if (next.has(quadrant)) {
            next.delete(quadrant);
        } else {
            next.add(quadrant);
        }

        _activeQuadrants.value = next;
    }

    function resetQuadrantFilter() {
        _activeQuadrants.value = new Set();
    }

    /** Contagem por quadrante — alimenta os botões de filtro do painel. */
    const quadrantStats = computed(() => {
        const stats: Record<BcgQuadrant, number> = {
            alto_alto: 0,
            forte_x: 0,
            forte_y: 0,
            baixo_baixo: 0,
        };

        for (const item of store.rawValues.value) {
            stats[item.quadrant] += 1;
        }

        return stats;
    });

    /** Produtos cujo espaço na gôndola está desalinhado do valor entregue. */
    const misallocatedCount = computed(
        () =>
            store.rawValues.value.filter(
                (item) => item.acao_espaco === 'aumentar' || item.acao_espaco === 'reduzir',
            ).length,
    );

    return {
        setBcgQuadrants,
        getBcgData,
        hasBcgData,
        clearBcgQuadrants,
        isQuadrantActive,
        toggleQuadrantFilter,
        resetQuadrantFilter,
        activeQuadrants: computed(() => _activeQuadrants.value),
        quadrantStats,
        misallocatedCount,
        toggleVisibility: store.toggleVisibility,
        setVisibility: store.setVisibility,
        stats: store.stats,
        hasData: store.hasData,
        isVisible: store.isVisible,
        lastAnalysisDate: store.lastAnalysisDate,
    };
}
