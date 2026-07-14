import { computed } from 'vue';
import { createEanAnalysisStore } from './useEanAnalysisStore';

/**
 * Interface dos dados de estoque alvo por produto (armazenados por EAN).
 */
export interface TargetStockData {
    ean: string;
    product_id: string;
    product_name: string;
    estoque_alvo: number;
    estoque_minimo: number;
    estoque_atual: number;
    estoque_seguranca: number;
    demanda_media: number;
    classificacao: 'A' | 'B' | 'C';
    permite_frentes: string;
    cobertura_dias: number;
}

/**
 * Store singleton para dados de target stock por EAN.
 * Criada no nível do módulo para garantir estado compartilhado entre todos os
 * componentes que chamarem `useTargetStockAnalysis()`.
 */
const useTargetStockStore = createEanAnalysisStore<TargetStockData>('target-stock-analysis', {
    getClassificacao: (item) => item.classificacao,
});

/** Margem de tolerância padrão (10%) para comparação com estoque alvo */
const DEFAULT_TOLERANCE = 0.10;

/**
 * Composable para gerenciar análises de estoque alvo em tempo real.
 * Armazena um mapa de EAN → TargetStockData com cálculos de status e capacidade.
 *
 * Uso:
 * - No PerformanceTargetStockTab: salvar resultados via `setTargetStockDataBatch`
 * - No Segment / StockIndicator: buscar dados e calcular status de estoque
 */
export function useTargetStockAnalysis() {
    const store = useTargetStockStore();

    /**
     * Define os dados de target stock de um produto pelo EAN.
     * Não atualiza lastAnalysisDate — use `setTargetStockDataBatch` para isso.
     */
    function setTargetStockData(ean: string, data: TargetStockData) {
        store.set(ean, data);
    }

    /**
     * Define múltiplos dados de target stock em lote e registra a data da análise.
     * Itens com EAN vazio são ignorados silenciosamente.
     */
    function setTargetStockDataBatch(
        items: Array<{
            ean: string;
            product_id: string;
            product_name: string;
            estoque_alvo: number;
            estoque_minimo: number;
            estoque_atual: number;
            estoque_seguranca: number;
            demanda_media: number;
            classificacao: 'A' | 'B' | 'C';
            permite_frentes: string;
            cobertura_dias: number;
        }>,
    ) {
        store.setBatch(
            items.filter((i) => i.ean).map((i) => ({ ean: i.ean, value: i as TargetStockData })),
        );
    }

    /**
     * Obtém os dados de target stock de um produto pelo EAN.
     * Retorna undefined se não houver dados registrados.
     */
    function getTargetStockData(ean: string | undefined): TargetStockData | undefined {
        return store.get(ean);
    }

    /**
     * Verifica se existem dados de target stock para o EAN informado.
     */
    function hasTargetStockData(ean: string | undefined): boolean {
        return store.has(ean);
    }

    /**
     * Remove todos os dados de target stock e reseta o timestamp da análise.
     */
    function clearTargetStockData() {
        store.clear();
    }

    /**
     * Remove os dados de um produto específico pelo EAN.
     */
    function removeTargetStockData(ean: string) {
        store.remove(ean);
    }

    /**
     * Calcula a margem de tolerância absoluta para um estoque alvo.
     * Garante um mínimo de 5 unidades independente do percentual.
     *
     * @param targetStock          - Estoque alvo desejado
     * @param tolerancePercentage  - Percentual de tolerância (padrão 10%)
     * @returns Margem absoluta em unidades
     */
    function calculateToleranceMargin(
        targetStock: number,
        tolerancePercentage: number = DEFAULT_TOLERANCE,
    ): number {
        const percentualMargin = targetStock * tolerancePercentage;

        return Math.max(percentualMargin, 5); // Mínimo de 5 unidades
    }

    /**
     * Determina o status do estoque comparando capacidade atual com o estoque alvo.
     *
     * @param currentCapacity      - Capacidade atual na gôndola (total de unidades)
     * @param targetStock          - Estoque alvo desejado
     * @param tolerancePercentage  - Percentual de tolerância (padrão 10%)
     * @returns 'increase' | 'decrease' | 'ok' | 'unknown'
     */
    function getStockStatus(
        currentCapacity: number,
        targetStock: number,
        tolerancePercentage: number = DEFAULT_TOLERANCE,
    ): 'increase' | 'decrease' | 'ok' | 'unknown' {
        if (!targetStock || targetStock === 0 || !currentCapacity) {
            return 'unknown';
        }

        const margin = calculateToleranceMargin(targetStock, tolerancePercentage);
        const lowerBound = targetStock - margin;
        const upperBound = targetStock + margin;

        if (currentCapacity < lowerBound) {
            return 'increase'; // Precisa aumentar capacidade
        }

        if (currentCapacity > upperBound) {
            return 'decrease'; // Precisa diminuir capacidade
        }

        return 'ok'; // Capacidade adequada
    }

    /**
     * Calcula a capacidade total de um segmento em unidades.
     *
     * @param segmentQuantity - Quantidade de frentes do segmento
     * @param layerQuantity   - Quantidade de produtos por frente (altura)
     * @param productDepth    - Profundidade do produto (cm)
     * @param shelfDepth      - Profundidade da prateleira (cm)
     * @returns Capacidade total em unidades
     */
    function calculateSegmentCapacity(
        segmentQuantity: number,
        layerQuantity: number,
        productDepth: number,
        shelfDepth: number,
    ): number {
        if (!productDepth || !shelfDepth || productDepth === 0) {
            return 0;
        }

        // Quantos produtos cabem na profundidade da prateleira
        const itemsInDepth = Math.floor(shelfDepth / productDepth);

        // Capacidade total = frentes × altura × profundidade
        return segmentQuantity * layerQuantity * itemsInDepth;
    }

    /**
     * Estatísticas estendidas: total, distribuição ABC, média de estoque alvo e
     * data/hora da última análise salva.
     */
    const stats = computed(() => {
        const base = store.stats.value;
        const values = store.rawValues.value;

        return {
            ...base,
            avgTargetStock:
                values.length > 0
                    ? values.reduce((sum, d) => sum + d.estoque_alvo, 0) / values.length
                    : 0,
        };
    });

    return {
        // Métodos
        setTargetStockData,
        setTargetStockDataBatch,
        getTargetStockData,
        hasTargetStockData,
        calculateToleranceMargin,
        getStockStatus,
        calculateSegmentCapacity,
        clearTargetStockData,
        removeTargetStockData,
        toggleVisibility: store.toggleVisibility,
        setVisibility: store.setVisibility,

        // Computed (stats sobrescreve o base com avgTargetStock)
        stats,
        hasData: store.hasData,
        isVisible: store.isVisible,
        lastAnalysisDate: store.lastAnalysisDate,

        // Constante
        DEFAULT_TOLERANCE,
    };
}
