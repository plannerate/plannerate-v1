<?php

namespace Callcocam\LaravelRaptorPlannerate\Sales;

/**
 * Value object (imutável) que representa o resumo agregado de vendas de um produto.
 *
 * Esta é a ÚNICA fonte de verdade das métricas derivadas de vendas. Antes, fórmulas
 * como preço médio, custo médio, lucro bruto e percentuais de margem estavam
 * espalhadas e divergentes entre o SQL dos controllers e os componentes Vue
 * (SalesSummaryCards, ProductSalesSummary, ProductIdentityHeader). Aqui elas existem
 * uma única vez, e o frontend apenas formata o resultado.
 *
 * Decisão canônica: "preço médio" é por UNIDADE (total_value / total_quantity),
 * aposentando o antigo AVG(sale_price) por transação.
 */
class SalesSummary
{
    /**
     * @param  int  $totalRecords  Quantidade de registros (transações) de venda
     * @param  float  $totalQuantity  Soma das quantidades vendidas
     * @param  float  $totalValue  Soma do valor total de venda (faturamento)
     * @param  float  $totalAcquisitionCost  Soma do custo de aquisição
     * @param  float  $totalProfitMargin  Soma da margem de lucro unitária
     * @param  float  $totalMargemContribuicao  Soma da margem de contribuição (líquida)
     * @param  float  $avgSalePrice  AVG(sale_price) por transação (legado; preferir avgPrice())
     * @param  int  $promoRecords  Registros marcados como promoção ('S')
     * @param  float  $promoQuantity  Quantidade vendida em promoção
     * @param  float  $promoValue  Valor vendido em promoção
     * @param  float  $regularQuantity  Quantidade vendida fora de promoção
     * @param  float  $regularValue  Valor vendido fora de promoção
     * @param  string|null  $firstSaleDate  Data da primeira venda do período (YYYY-MM-DD)
     * @param  string|null  $lastSaleDate  Data da última venda do período (YYYY-MM-DD)
     */
    public function __construct(
        public readonly int $totalRecords = 0,
        public readonly float $totalQuantity = 0.0,
        public readonly float $totalValue = 0.0,
        public readonly float $totalAcquisitionCost = 0.0,
        public readonly float $totalProfitMargin = 0.0,
        public readonly float $totalMargemContribuicao = 0.0,
        public readonly float $avgSalePrice = 0.0,
        public readonly int $promoRecords = 0,
        public readonly float $promoQuantity = 0.0,
        public readonly float $promoValue = 0.0,
        public readonly float $regularQuantity = 0.0,
        public readonly float $regularValue = 0.0,
        public readonly ?string $firstSaleDate = null,
        public readonly ?string $lastSaleDate = null,
    ) {}

    /**
     * Cria o resumo a partir da linha agregada retornada por SalesQuery::aggregate().
     * Trata null (sem vendas no período) zerando todos os totais.
     */
    public static function fromAggregate(?object $row): self
    {
        if ($row === null) {
            return new self;
        }

        return new self(
            totalRecords: (int) ($row->total_records ?? 0),
            totalQuantity: (float) ($row->total_quantity ?? 0),
            totalValue: (float) ($row->total_value ?? 0),
            totalAcquisitionCost: (float) ($row->total_acquisition_cost ?? 0),
            totalProfitMargin: (float) ($row->total_profit_margin ?? 0),
            totalMargemContribuicao: (float) ($row->total_margem_contribuicao ?? 0),
            avgSalePrice: (float) ($row->avg_sale_price ?? 0),
            promoRecords: (int) ($row->promo_records ?? 0),
            promoQuantity: (float) ($row->promo_quantity ?? 0),
            promoValue: (float) ($row->promo_value ?? 0),
            regularQuantity: (float) ($row->regular_quantity ?? 0),
            regularValue: (float) ($row->regular_value ?? 0),
            firstSaleDate: self::asDateString($row->first_sale_date ?? null),
            lastSaleDate: self::asDateString($row->last_sale_date ?? null),
        );
    }

    /**
     * Preço médio por unidade = faturamento ÷ quantidade.
     */
    public function avgPrice(): float
    {
        return $this->totalQuantity > 0 ? $this->totalValue / $this->totalQuantity : 0.0;
    }

    /**
     * Custo médio por unidade = custo de aquisição total ÷ quantidade.
     */
    public function avgCost(): float
    {
        return $this->totalQuantity > 0 ? $this->totalAcquisitionCost / $this->totalQuantity : 0.0;
    }

    /**
     * Margem líquida média por unidade = margem de contribuição total ÷ quantidade.
     */
    public function avgMargin(): float
    {
        return $this->totalQuantity > 0 ? $this->totalMargemContribuicao / $this->totalQuantity : 0.0;
    }

    /**
     * Lucro bruto total = faturamento − custo de aquisição (não desconta impostos).
     */
    public function grossProfitTotal(): float
    {
        return $this->totalValue - $this->totalAcquisitionCost;
    }

    /**
     * Lucro bruto por unidade = preço médio − custo médio.
     */
    public function grossProfitUnit(): float
    {
        return $this->avgPrice() - $this->avgCost();
    }

    /**
     * Margem bruta (%) = lucro bruto total ÷ faturamento × 100.
     */
    public function grossMarginPct(): float
    {
        return $this->totalValue > 0 ? ($this->grossProfitTotal() / $this->totalValue) * 100 : 0.0;
    }

    /**
     * Margem líquida (%) = margem de contribuição ÷ faturamento × 100.
     */
    public function netMarginPct(): float
    {
        return $this->totalValue > 0 ? ($this->totalMargemContribuicao / $this->totalValue) * 100 : 0.0;
    }

    /**
     * Percentual de registros em promoção = registros promo ÷ total de registros × 100.
     */
    public function promoPercent(): float
    {
        return $this->totalRecords > 0 ? ($this->promoRecords / $this->totalRecords) * 100 : 0.0;
    }

    /**
     * Serializa o resumo completo (somas brutas + métricas derivadas) num shape
     * estável em snake_case. Consumido pela página de vendas (Inertia) e exposto
     * ao frontend, que apenas formata os valores.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            // ── Somas brutas ──
            'total_records' => $this->totalRecords,
            'total_quantity' => (string) $this->totalQuantity,
            'total_value' => (string) $this->totalValue,
            'total_acquisition_cost' => (string) $this->totalAcquisitionCost,
            'total_profit_margin' => (string) $this->totalProfitMargin,
            'total_margem_contribuicao' => (string) $this->totalMargemContribuicao,
            'avg_sale_price' => (string) $this->avgSalePrice,
            'promo_records' => $this->promoRecords,
            'promo_quantity' => (string) $this->promoQuantity,
            'promo_value' => (string) $this->promoValue,
            'regular_quantity' => (string) $this->regularQuantity,
            'regular_value' => (string) $this->regularValue,
            'first_sale_date' => $this->firstSaleDate,
            'last_sale_date' => $this->lastSaleDate,
            // ── Métricas derivadas (fonte única) ──
            'avg_price' => $this->avgPrice(),
            'avg_cost' => $this->avgCost(),
            'avg_margin' => $this->avgMargin(),
            'gross_profit_unit' => $this->grossProfitUnit(),
            'gross_profit_total' => $this->grossProfitTotal(),
            'gross_margin_pct' => $this->grossMarginPct(),
            'net_margin_pct' => $this->netMarginPct(),
            'promo_percent' => $this->promoPercent(),
        ];
    }

    /**
     * Normaliza um valor de data agregado (Carbon/string) para 'YYYY-MM-DD' ou null.
     */
    private static function asDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
