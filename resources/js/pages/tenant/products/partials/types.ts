import type { Paginator } from '@/types';

/** Identificação resumida do produto exibida no cabeçalho da página de vendas */
export interface ProductInfo {
    id: string;
    name: string | null;
    ean: string | null;
    codigo_erp: string | null;
    image_url: string | null;
}

/** Tipagem de um registro de venda paginado */
export interface SaleRow {
    id: string;
    store: string | null;
    sale_date: string | null;
    promotion: string | null;
    total_sale_quantity: string | null;
    total_sale_value: string | null;
    acquisition_cost: string | null;
    sale_price: string | null;
    total_profit_margin: string | null;
    margem_contribuicao: string | null;
    extra_data: Record<string, unknown> | null;
}

/**
 * Totalizadores (somas brutas) calculados pelo backend para o período/filtro atual.
 *
 * Observação: `avg_sale_price` vem do backend como `AVG(sale_price)` (média por
 * transação). Os cards do resumo NÃO usam esse campo — eles derivam o preço médio
 * por unidade (`total_value / total_quantity`) para bater com o card lateral do editor.
 */
export interface SalesTotals {
    total_records: number;
    total_quantity: string;
    total_value: string;
    total_acquisition_cost: string;
    total_profit_margin: string;
    total_margem_contribuicao: string;
    avg_sale_price: string;
    promo_records: number;
    promo_quantity: string;
    promo_value: string;
    regular_quantity: string;
    regular_value: string;
}

/** Filtros ativos da página de vendas */
export interface SalesFilters {
    sale_date_from: string;
    sale_date_to: string;
    promotion: string;
    store_id: string;
}

/** Opções disponíveis para os selects de filtro */
export interface SalesFilterOptions {
    stores: Array<{ id: string; name: string }>;
}

/** Props completas da página de vendas do produto */
export interface ProductSalesPageProps {
    product: ProductInfo;
    sales: Paginator<SaleRow>;
    totals: SalesTotals;
    filters: SalesFilters;
    filter_options: SalesFilterOptions;
}
