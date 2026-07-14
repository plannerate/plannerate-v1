import { createEanAnalysisStore } from './useEanAnalysisStore';

/**
 * Métricas de vendas exibíveis na frente do produto, derivadas no backend
 * (fonte única: SalesSummary). Todas por unidade, exceto os percentuais.
 */
export interface SalesIndicatorData {
    /** Preço médio por unidade (faturamento ÷ quantidade) */
    avgPrice: number;
    /** Custo médio por unidade (custo de aquisição ÷ quantidade) */
    avgCost: number;
    /** Margem líquida média por unidade (margem de contribuição ÷ quantidade) */
    avgMargin: number;
    /** Margem bruta (%) */
    grossMarginPct: number;
    /** Margem líquida (%) */
    netMarginPct: number;
}

/** Shape de cada item retornado pelo endpoint de indicadores da gôndola. */
interface SalesIndicatorResult {
    ean: string | null;
    product_id: string;
    avg_price: number;
    avg_cost: number;
    avg_margin: number;
    gross_margin_pct: number;
    net_margin_pct: number;
}

/**
 * Store singleton de indicadores de vendas por EAN. Compartilha o mesmo padrão
 * das análises (ABC/Paper): um `Map<EAN, SalesIndicatorData>` reativo, populado
 * em lote a partir do endpoint da gôndola.
 */
const useSalesIndicatorsStore = createEanAnalysisStore<SalesIndicatorData>('sales-indicators');

/**
 * Composable para carregar e consultar os indicadores de vendas (preço/margem)
 * dos produtos de uma gôndola.
 *
 * - `loadForGondola`: busca o lote no backend e popula a store por EAN.
 * - `getIndicators`: lê os dados de um produto pelo EAN (usado pelos selos).
 */
export function useSalesIndicators() {
    const store = useSalesIndicatorsStore();

    /**
     * Carrega em lote os indicadores de vendas de todos os produtos da gôndola.
     * Filtra pelo período do planograma quando informado. Idempotente o bastante
     * para ser chamado ao selecionar um indicador baseado em vendas.
     *
     * @param gondolaId ID da gôndola atual
     * @param startDate Início do período do planograma (opcional, YYYY-MM-DD)
     * @param endDate   Fim do período do planograma (opcional, YYYY-MM-DD)
     */
    async function loadForGondola(
        gondolaId: string,
        startDate?: string | null,
        endDate?: string | null,
    ): Promise<void> {
        if (!gondolaId) {
            return;
        }

        const params = new URLSearchParams();

        if (startDate) {
            params.set('start_date', startDate);
        }

        if (endDate) {
            params.set('end_date', endDate);
        }

        const query = params.toString() ? `?${params.toString()}` : '';

        try {
            const response = await fetch(
                `/api/editor/gondolas/${gondolaId}/sales/indicators${query}`,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data: { results?: SalesIndicatorResult[] } = await response.json();

            store.setBatch(
                (data.results ?? [])
                    .filter((item) => !!item.ean)
                    .map((item) => ({
                        ean: item.ean as string,
                        value: {
                            avgPrice: Number(item.avg_price) || 0,
                            avgCost: Number(item.avg_cost) || 0,
                            avgMargin: Number(item.avg_margin) || 0,
                            grossMarginPct: Number(item.gross_margin_pct) || 0,
                            netMarginPct: Number(item.net_margin_pct) || 0,
                        },
                    })),
            );
        } catch (err) {
            console.error('Erro ao carregar indicadores de vendas:', err);
        }
    }

    /** Lê os indicadores de vendas de um produto pelo EAN. */
    function getIndicators(ean: string | undefined): SalesIndicatorData | undefined {
        return store.get(ean);
    }

    return {
        loadForGondola,
        getIndicators,
        hasData: store.hasData,
        clear: store.clear,
    };
}
