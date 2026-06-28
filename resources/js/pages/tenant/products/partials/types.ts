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
 * Totalizadores e métricas derivadas calculados pelo backend (SalesSummary) para o
 * período/filtro atual. O frontend apenas formata — todas as fórmulas (preço médio,
 * custo médio, lucro bruto, percentuais) vêm prontas da fonte única de verdade.
 *
 * Observação: `avg_sale_price` é o legado `AVG(sale_price)` (média por transação) e
 * NÃO deve ser usado; o preço médio canônico por unidade é `avg_price`.
 */
export interface SalesTotals {
    // ── Somas brutas ──
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
    // ── Métricas derivadas (fonte única no backend) ──
    /** Preço médio por unidade = total_value / total_quantity */
    avg_price: number;
    /** Custo médio por unidade = total_acquisition_cost / total_quantity */
    avg_cost: number;
    /** Margem líquida média por unidade = total_margem_contribuicao / total_quantity */
    avg_margin: number;
    /** Lucro bruto por unidade = avg_price − avg_cost */
    gross_profit_unit: number;
    /** Lucro bruto total = total_value − total_acquisition_cost */
    gross_profit_total: number;
    /** Margem bruta (%) = gross_profit_total / total_value × 100 */
    gross_margin_pct: number;
    /** Margem líquida (%) = total_margem_contribuicao / total_value × 100 */
    net_margin_pct: number;
    /** Percentual de registros em promoção = promo_records / total_records × 100 */
    promo_percent: number;
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
