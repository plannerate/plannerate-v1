import { ref, computed } from 'vue';

/**
 * Composable para gerenciar classificações ABC de produtos em tempo real
 * Armazena um mapa de EAN → Classificação (A, B ou C)
 * 
 * Uso:
 * - No PerformanceAbcTab: salvar resultados após cálculo
 * - No Segment: buscar classificação por EAN do produto
 */

// Estado global reativo - Map de EAN → Classificação
const abcClassifications = ref<Map<string, 'A' | 'B' | 'C'>>(new Map());

// Timestamp da última análise
const lastAnalysisDate = ref<Date | null>(null);

// Controle de visibilidade dos indicadores ABC
const isVisible = ref<boolean>(true);

export function useAbcClassification() {
    /**
     * Define a classificação ABC de um produto pelo EAN
     */
    function setClassification(ean: string, classification: 'A' | 'B' | 'C') {
        if (!ean) {
            console.warn('⚠️ EAN vazio ao definir classificação ABC');

            return;
        }
        
        abcClassifications.value.set(ean, classification);
    }

    /**
     * Define múltiplas classificações de uma vez (batch)
     * Usado quando recebe resultados da análise ABC
     */
    function setClassifications(items: Array<{ ean: string; classificacao: 'A' | 'B' | 'C' }>) {
        items.forEach(item => {
            if (item.ean && item.classificacao) {
                setClassification(item.ean, item.classificacao);
            }
        });
        
        lastAnalysisDate.value = new Date();
    }

    /**
     * Obtém a classificação ABC de um produto pelo EAN
     * Retorna undefined se não houver classificação
     */
    function getClassification(ean: string | undefined): 'A' | 'B' | 'C' | undefined {
        if (!ean) {
return undefined;
}

        return abcClassifications.value.get(ean);
    }

    /**
     * Verifica se existe classificação para um EAN
     */
    function hasClassification(ean: string | undefined): boolean {
        if (!ean) {
return false;
}

        return abcClassifications.value.has(ean);
    }

    /**
     * Remove todas as classificações
     */
    function clearClassifications() {
        abcClassifications.value.clear();
        lastAnalysisDate.value = null;
    }

    /**
     * Remove classificação de um produto específico
     */
    function removeClassification(ean: string) {
        if (!ean) {
return;
}

        abcClassifications.value.delete(ean);
    }

    /**
     * Retorna estatísticas das classificações
     */
    const stats = computed(() => {
        const classifications = Array.from(abcClassifications.value.values());
        
        return {
            total: classifications.length,
            classA: classifications.filter(c => c === 'A').length,
            classB: classifications.filter(c => c === 'B').length,
            classC: classifications.filter(c => c === 'C').length,
            lastAnalysis: lastAnalysisDate.value
        };
    });

    /**
     * Verifica se há classificações carregadas
     */
    const hasData = computed(() => abcClassifications.value.size > 0);

    /**
     * Alterna a visibilidade dos indicadores ABC
     */
    function toggleVisibility() {
        isVisible.value = !isVisible.value;
    }

    /**
     * Define a visibilidade dos indicadores ABC
     */
    function setVisibility(visible: boolean) {
        isVisible.value = visible;
    }

    return {
        // Métodos
        setClassification,
        setClassifications,
        getClassification,
        hasClassification,
        clearClassifications,
        removeClassification,
        toggleVisibility,
        setVisibility,
        
        // Computed
        stats,
        hasData,
        isVisible: computed(() => isVisible.value),
        lastAnalysisDate: computed(() => lastAnalysisDate.value),
    };
}

