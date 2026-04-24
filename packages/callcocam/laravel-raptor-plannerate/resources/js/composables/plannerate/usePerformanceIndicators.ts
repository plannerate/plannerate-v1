import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAbcClassification } from './useAbcClassification';
import { useTargetStockAnalysis } from './useTargetStockAnalysis';

/**
 * Composable centralizado para gerenciar todos os indicadores de performance
 * Controla ABC, Target Stock e futuramente BCG
 */
export function usePerformanceIndicators() {
    const abc = useAbcClassification();
    const targetStock = useTargetStockAnalysis();

    /**
     * Alterna a visibilidade de TODOS os indicadores de performance
     */
    function toggleAllIndicators() {
        const newState = !abc.isVisible.value;
        abc.setVisibility(newState);
        targetStock.setVisibility(newState);
    }

    /**
     * Mostra todos os indicadores
     */
    function showAllIndicators() {
        abc.setVisibility(true);
        targetStock.setVisibility(true);
    }

    /**
     * Esconde todos os indicadores
     */
    function hideAllIndicators() {
        abc.setVisibility(false);
        targetStock.setVisibility(false);
    }

    /**
     * Limpa todos os dados de análise (ABC e Target Stock)
     */
    function clearAllAnalysis(gondolaId?: string | number) {
        if (gondolaId) {
            router.delete(`/api/editor/gondolas/${gondolaId}/analysis`, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    abc.clearClassifications();
                    targetStock.clearTargetStockData();
                },
                onError: (errors) => {
                    console.error('Erro ao limpar análises no banco:', errors);
                },
            });

            return;
        }

        abc.clearClassifications();
        targetStock.clearTargetStockData();
    }

    /**
     * Verifica se algum indicador está visível
     */
    const anyVisible = computed(() => abc.isVisible.value || targetStock.isVisible.value);

    /**
     * Verifica se todos os indicadores estão visíveis
     */
    const allVisible = computed(() => abc.isVisible.value && targetStock.isVisible.value);

    /**
     * Verifica se há algum dado carregado
     */
    const hasAnyData = computed(() => abc.hasData.value || targetStock.hasData.value);

    return {
        // Controles gerais
        toggleAllIndicators,
        showAllIndicators,
        hideAllIndicators,
        clearAllAnalysis,

        // Controles individuais
        abc,
        targetStock,

        // Estados computados
        anyVisible,
        allVisible,
        hasAnyData,
    };
}

