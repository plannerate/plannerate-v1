import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAbcClassification } from './useAbcClassification';
import { useBcgAnalysis } from './useBcgAnalysis';
import { usePaperAnalysis } from './usePaperAnalysis';
import { useTargetStockAnalysis } from './useTargetStockAnalysis';

/**
 * Composable centralizado para gerenciar todos os indicadores de performance.
 * Controla ABC, Target Stock, Análise de Papel e Análise BCG.
 */
export function usePerformanceIndicators() {
    const abc = useAbcClassification();
    const targetStock = useTargetStockAnalysis();
    const paper = usePaperAnalysis();
    const bcg = useBcgAnalysis();

    /** Alterna a visibilidade de TODOS os indicadores de performance. */
    function toggleAllIndicators() {
        const newState = !abc.isVisible.value;
        abc.setVisibility(newState);
        targetStock.setVisibility(newState);
        paper.setVisibility(newState);
        bcg.setVisibility(newState);
    }

    /** Mostra todos os indicadores. */
    function showAllIndicators() {
        abc.setVisibility(true);
        targetStock.setVisibility(true);
        paper.setVisibility(true);
        bcg.setVisibility(true);
    }

    /** Esconde todos os indicadores. */
    function hideAllIndicators() {
        abc.setVisibility(false);
        targetStock.setVisibility(false);
        paper.setVisibility(false);
        bcg.setVisibility(false);
    }

    /** Limpa todos os dados de análise (ABC, Target Stock, Papel e BCG). */
    function clearAllAnalysis(gondolaId?: string | number) {
        if (gondolaId) {
            router.delete(`/api/editor/gondolas/${gondolaId}/analysis`, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    abc.clearClassifications();
                    targetStock.clearTargetStockData();
                    paper.clearPaperRoles();
                    bcg.clearBcgQuadrants();
                },
                onError: (errors) => {
                    console.error('Erro ao limpar análises no banco:', errors);
                },
            });

            return;
        }

        abc.clearClassifications();
        targetStock.clearTargetStockData();
        paper.clearPaperRoles();
        bcg.clearBcgQuadrants();
    }

    const anyVisible = computed(
        () =>
            abc.isVisible.value ||
            targetStock.isVisible.value ||
            paper.isVisible.value ||
            bcg.isVisible.value,
    );

    const allVisible = computed(
        () =>
            abc.isVisible.value &&
            targetStock.isVisible.value &&
            paper.isVisible.value &&
            bcg.isVisible.value,
    );

    const hasAnyData = computed(
        () =>
            abc.hasData.value ||
            targetStock.hasData.value ||
            paper.hasData.value ||
            bcg.hasData.value,
    );

    return {
        toggleAllIndicators,
        showAllIndicators,
        hideAllIndicators,
        clearAllAnalysis,
        abc,
        targetStock,
        paper,
        bcg,
        anyVisible,
        allVisible,
        hasAnyData,
    };
}
