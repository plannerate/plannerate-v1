import { ref, computed } from 'vue';

/**
 * Composable para gerenciar análises de estoque alvo em tempo real
 * Armazena informações de estoque por EAN e calcula status baseado na capacidade da gôndola
 * 
 * Uso:
 * - No PerformanceTargetStockTab: salvar resultados após cálculo
 * - No Segment: buscar dados e calcular status de estoque
 */

// Interface dos dados de estoque alvo
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

// Estado global reativo - Map de EAN → Dados de Estoque
const targetStockData = ref<Map<string, TargetStockData>>(new Map());

// Timestamp da última análise
const lastAnalysisDate = ref<Date | null>(null);

// Controle de visibilidade dos indicadores de Target Stock
const isVisible = ref<boolean>(true);

// Margem de tolerância padrão (10%)
const DEFAULT_TOLERANCE = 0.10;

export function useTargetStockAnalysis() {
    /**
     * Define os dados de target stock de um produto pelo EAN
     */
    function setTargetStockData(ean: string, data: TargetStockData) {
        if (!ean) {
            console.warn('⚠️ EAN vazio ao definir dados de target stock');
            return;
        }
        
        targetStockData.value.set(ean, data);
    }

    /**
     * Define múltiplos dados de target stock de uma vez (batch)
     * Usado quando recebe resultados da análise
     */
    function setTargetStockDataBatch(items: Array<{
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
    }>) {
        items.forEach(item => {
            if (item.ean) {
                setTargetStockData(item.ean, item as TargetStockData);
            }
        });
        
        lastAnalysisDate.value = new Date();
    }

    /**
     * Obtém os dados de target stock de um produto pelo EAN
     */
    function getTargetStockData(ean: string | undefined): TargetStockData | undefined {
        if (!ean) return undefined;
        return targetStockData.value.get(ean);
    }

    /**
     * Verifica se existe dados de target stock para um EAN
     */
    function hasTargetStockData(ean: string | undefined): boolean {
        if (!ean) return false;
        return targetStockData.value.has(ean);
    }

    /**
     * Calcula a margem de tolerância para um estoque alvo
     */
    function calculateToleranceMargin(targetStock: number, tolerancePercentage: number = DEFAULT_TOLERANCE): number {
        const percentualMargin = targetStock * tolerancePercentage;
        return Math.max(percentualMargin, 5); // Mínimo de 5 unidades
    }

    /**
     * Determina o status do estoque baseado na capacidade atual vs estoque alvo
     * 
     * @param currentCapacity - Capacidade atual na gôndola (total de unidades)
     * @param targetStock - Estoque alvo desejado
     * @param tolerancePercentage - Percentual de tolerância (padrão 10%)
     * @returns 'increase' | 'decrease' | 'ok' | 'unknown'
     */
    function getStockStatus(
        currentCapacity: number,
        targetStock: number,
        tolerancePercentage: number = DEFAULT_TOLERANCE
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
     * Calcula a capacidade de um segment específico
     * 
     * @param segmentQuantity - Quantidade de frentes do segment
     * @param layerQuantity - Quantidade de produtos por frente (vertical)
     * @param productDepth - Profundidade do produto (cm)
     * @param shelfDepth - Profundidade da prateleira (cm)
     * @returns Capacidade total em unidades
     */
    function calculateSegmentCapacity(
        segmentQuantity: number,
        layerQuantity: number,
        productDepth: number,
        shelfDepth: number
    ): number {
        if (!productDepth || !shelfDepth || productDepth === 0) {
            return 0;
        }

        // Quantos produtos cabem na profundidade
        const itemsInDepth = Math.floor(shelfDepth / productDepth);
        
        // Capacidade total = frentes × altura × profundidade
        const totalCapacity = segmentQuantity * layerQuantity * itemsInDepth;
        
        return totalCapacity;
    }

    /**
     * Remove todos os dados de target stock
     */
    function clearTargetStockData() {
        targetStockData.value.clear();
        lastAnalysisDate.value = null;
    }

    /**
     * Remove dados de um produto específico
     */
    function removeTargetStockData(ean: string) {
        if (!ean) return;
        targetStockData.value.delete(ean);
    }

    /**
     * Retorna estatísticas dos dados de target stock
     */
    const stats = computed(() => {
        const allData = Array.from(targetStockData.value.values());
        
        return {
            total: allData.length,
            classA: allData.filter(d => d.classificacao === 'A').length,
            classB: allData.filter(d => d.classificacao === 'B').length,
            classC: allData.filter(d => d.classificacao === 'C').length,
            avgTargetStock: allData.length > 0 
                ? allData.reduce((sum, d) => sum + d.estoque_alvo, 0) / allData.length 
                : 0,
            lastAnalysis: lastAnalysisDate.value
        };
    });

    /**
     * Verifica se há dados carregados
     */
    const hasData = computed(() => targetStockData.value.size > 0);

    /**
     * Alterna a visibilidade dos indicadores de Target Stock
     */
    function toggleVisibility() {
        isVisible.value = !isVisible.value;
    }

    /**
     * Define a visibilidade dos indicadores de Target Stock
     */
    function setVisibility(visible: boolean) {
        isVisible.value = visible;
    }

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
        toggleVisibility,
        setVisibility,
        
        // Computed
        stats,
        hasData,
        isVisible: computed(() => isVisible.value),
        lastAnalysisDate: computed(() => lastAnalysisDate.value),
        
        // Constantes
        DEFAULT_TOLERANCE,
    };
}

