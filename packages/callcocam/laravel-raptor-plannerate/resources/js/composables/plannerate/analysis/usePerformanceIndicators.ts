import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAbcClassification } from './useAbcClassification';
import { usePaperAnalysis } from './usePaperAnalysis';
import { useTargetStockAnalysis } from './useTargetStockAnalysis';

/**
 * Composable centralizado para gerenciar todos os indicadores de performance.
 * Controla ABC, Target Stock e Análise de Papel.
 */
export function usePerformanceIndicators() {
    const abc = useAbcClassification();
    const targetStock = useTargetStockAnalysis();
    const paper = usePaperAnalysis();

    /** Alterna a visibilidade de TODOS os indicadores de performance. */
    function toggleAllIndicators() {
        const newState = !abc.isVisible.value;
        abc.setVisibility(newState);
        targetStock.setVisibility(newState);
        paper.setVisibility(newState);
    }

    /** Mostra todos os indicadores. */
    function showAllIndicators() {
        abc.setVisibility(true);
        targetStock.setVisibility(true);
        paper.setVisibility(true);
    }

    /** Esconde todos os indicadores. */
    function hideAllIndicators() {
        abc.setVisibility(false);
        targetStock.setVisibility(false);
        paper.setVisibility(false);
    }

    /** Limpa todos os dados de análise (ABC, Target Stock e Análise de Papel). */
    function clearAllAnalysis(gondolaId?: string | number) {
        if (gondolaId) {
            router.delete(`/api/editor/gondolas/${gondolaId}/analysis`, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    abc.clearClassifications();
                    targetStock.clearTargetStockData();
                    paper.clearPaperRoles();
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
    }

    const anyVisible = computed(
        () => abc.isVisible.value || targetStock.isVisible.value || paper.isVisible.value,
    );

    const allVisible = computed(
        () => abc.isVisible.value && targetStock.isVisible.value && paper.isVisible.value,
    );

    const hasAnyData = computed(
        () => abc.hasData.value || targetStock.hasData.value || paper.hasData.value,
    );

    return {
        toggleAllIndicators,
        showAllIndicators,
        hideAllIndicators,
        clearAllAnalysis,
        abc,
        targetStock,
        paper,
        anyVisible,
        allVisible,
        hasAnyData,
    };
}
