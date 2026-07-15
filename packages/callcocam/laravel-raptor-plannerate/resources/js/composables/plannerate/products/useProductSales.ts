import { usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref } from 'vue';
import { useT } from '@/composables/useT';

export interface ProductSalesSummary {
    total_sales: number;
    total_quantity: number;
    total_revenue: number;
    /** Soma do custo de aquisição (origina o custo médio) */
    total_cost: number;
    /** Soma da margem de contribuição (origina a margem média) */
    total_margin: number;
    avg_price: number;
    avg_cost: number;
    avg_margin: number;
    /** Margem de contribuição como % do faturamento */
    margin_percentage: number;
    /** Lucro bruto por unidade = avg_price − avg_cost (calculado no backend) */
    gross_profit_unit: number;
    /** Lucro bruto total = total_revenue − total_cost (calculado no backend) */
    gross_profit_total: number;
    /** Margem bruta (%) = gross_profit_total / total_revenue × 100 (calculado no backend) */
    gross_margin_pct: number;
    first_sale_date: string | null;
    last_sale_date: string | null;
}

export interface SalesByMonth {
    month: string;
    sales_count: number;
    quantity: number;
    revenue: number;
}

export interface TopStore {
    store_id: string;
    store_name: string;
    sales_count: number;
    quantity: number;
    revenue: number;
}

export interface ProductSalesData {
    product_id: string;
    product_name: string;
    product_ean: string;
    summary: ProductSalesSummary;
    by_month: SalesByMonth[];
    top_stores: TopStore[];
}

/**
 * Composable para carregar e gerenciar dados de vendas de produtos
 */
export function useProductSales() {
    const { t } = useT();
    const salesData: Ref<ProductSalesData | null> = ref(null);
    const isLoading = ref(false);
    const error: Ref<string | null> = ref(null);

    /**
     * Carrega o resumo de vendas do produto.
     *
     * @param productId ID do produto
     * @param startDate Data inicial do período do planograma (opcional, YYYY-MM-DD)
     * @param endDate Data final do período do planograma (opcional, YYYY-MM-DD)
     */
    async function loadSales(
        productId: string,
        startDate?: string | null,
        endDate?: string | null,
    ) {
        if (!productId) {
            error.value = t('plannerate.composables.product_sales.required_product_id');

            return;
        }

        isLoading.value = true;
        error.value = null;
        salesData.value = null;

        try {
            // Filtra as vendas pelo período do planograma quando informado, e
            // pela loja da gôndola em edição (mesma fonte usada pelas análises
            // ABC/BCG/Estoque Alvo — resolvida no backend a partir do
            // planograma, não confiada do cliente).
            const params = new URLSearchParams();

            if (startDate) {
                params.set('start_date', startDate);
            }

            if (endDate) {
                params.set('end_date', endDate);
            }

            const gondolaId = (usePage().props as { record?: { id?: string } })
                ?.record?.id;

            if (gondolaId) {
                params.set('gondola_id', gondolaId);
            }

            const query = params.toString() ? `?${params.toString()}` : '';

            const response = await fetch(
                `/api/plannerate/products/${productId}/sales/summary${query}`,
            );

            if (!response.ok) {
                throw new Error(
                    t('plannerate.composables.product_sales.load_sales_failed'),
                );
            }

            const data: ProductSalesData = await response.json();
            salesData.value = data;
        } catch (err) {
            error.value =
                err instanceof Error
                    ? err.message
                    : t('plannerate.composables.product_sales.unknown_error');
            console.error('Erro ao carregar vendas:', err);
        } finally {
            isLoading.value = false;
        }
    }

    function clearSales() {
        salesData.value = null;
        error.value = null;
    }

    return {
        salesData,
        isLoading,
        error,
        loadSales,
        clearSales,
    };
}
