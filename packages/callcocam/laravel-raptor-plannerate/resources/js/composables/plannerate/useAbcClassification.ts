import { createEanAnalysisStore } from './analysis/useEanAnalysisStore';

/**
 * Store singleton para classificações ABC por EAN.
 * Criada no nível do módulo para garantir estado compartilhado entre todos os
 * componentes que chamarem `useAbcClassification()`.
 *
 * Cada valor armazenado é diretamente a classificação ('A' | 'B' | 'C'),
 * portanto `getClassificacao` simplesmente retorna o próprio item.
 */
const useAbcStore = createEanAnalysisStore<'A' | 'B' | 'C'>('abc-classification', {
    getClassificacao: (item) => item,
});

/**
 * Composable para gerenciar classificações ABC de produtos em tempo real.
 * Armazena um mapa de EAN → Classificação (A, B ou C).
 *
 * Uso:
 * - No PerformanceAbcTab: salvar resultados após cálculo via `setClassifications`
 * - No Segment / AbcBadge: buscar classificação por EAN via `getClassification`
 */
export function useAbcClassification() {
    const store = useAbcStore();

    /**
     * Define a classificação ABC de um produto pelo EAN.
     * Não atualiza lastAnalysisDate — use `setClassifications` (batch) para isso.
     */
    function setClassification(ean: string, classification: 'A' | 'B' | 'C') {
        store.set(ean, classification);
    }

    /**
     * Define múltiplas classificações de uma vez (batch) e registra a data da análise.
     * Itens com EAN ou classificação vazios são ignorados silenciosamente.
     */
    function setClassifications(items: Array<{ ean: string; classificacao: 'A' | 'B' | 'C' }>) {
        store.setBatch(
            items
                .filter((i) => i.ean && i.classificacao)
                .map((i) => ({ ean: i.ean, value: i.classificacao })),
        );
    }

    /**
     * Obtém a classificação ABC de um produto pelo EAN.
     * Retorna undefined se não houver classificação registrada.
     */
    function getClassification(ean: string | undefined): 'A' | 'B' | 'C' | undefined {
        return store.get(ean);
    }

    /**
     * Verifica se existe classificação para o EAN informado.
     */
    function hasClassification(ean: string | undefined): boolean {
        return store.has(ean);
    }

    /**
     * Remove todas as classificações e reseta o timestamp da análise.
     */
    function clearClassifications() {
        store.clear();
    }

    /**
     * Remove a classificação de um produto específico pelo EAN.
     */
    function removeClassification(ean: string) {
        store.remove(ean);
    }

    return {
        // Métodos
        setClassification,
        setClassifications,
        getClassification,
        hasClassification,
        clearClassifications,
        removeClassification,
        toggleVisibility: store.toggleVisibility,
        setVisibility: store.setVisibility,

        // Computed
        stats: store.stats,
        hasData: store.hasData,
        isVisible: store.isVisible,
        lastAnalysisDate: store.lastAnalysisDate,
    };
}
