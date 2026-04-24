import type { Ref } from 'vue';
import { ref } from 'vue';

export interface ProductSalesSummary {
    total_sales: number;
    total_quantity: number;
    total_revenue: number;
    avg_price: number;
    avg_cost: number;
    avg_margin: number;
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
    const salesData: Ref<ProductSalesData | null> = ref(null);
    const isLoading = ref(false);
    const error: Ref<string | null> = ref(null);

    async function loadSales(productId: string) {
        if (!productId) {
            error.value = 'ID do produto é obrigatório';
            return;
        }

        isLoading.value = true;
        error.value = null;
        salesData.value = null;

        try {
            const response = await fetch(
                `/api/plannerate/products/${productId}/sales/summary`,
            );

            if (!response.ok) {
                throw new Error('Erro ao carregar dados de vendas');
            }

            const data: ProductSalesData = await response.json();
            salesData.value = data;
        } catch (err) {
            error.value =
                err instanceof Error ? err.message : 'Erro desconhecido';
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
