<?php

namespace Callcocam\LaravelRaptorPlannerate\Sales;

use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Illuminate\Support\Collection;

/**
 * Fachada pública para os cálculos de RESUMO de vendas de um produto.
 *
 * Orquestra SalesQuery + SalesSummary e concentra as agregações auxiliares
 * (vendas por mês e top lojas) que antes viviam dentro do controller do editor.
 * É o ponto de entrada único usado tanto pelo app (ProductController) quanto
 * pelo package (ProductSalesController).
 */
class SalesSummaryService
{
    /**
     * Resumo agregado de vendas do produto para o período/filtro informado.
     */
    public function summaryForProduct(object $product, SalesFilters $filters): SalesSummary
    {
        return SalesQuery::make()
            ->forProduct($product)
            ->applyFilters($filters)
            ->summary();
    }

    /**
     * Resumo de vendas (preço e margem médios) de TODOS os produtos de uma
     * gôndola, agregado por produto numa única query e devolvido keyed por EAN.
     *
     * É a fonte dos selos de indicadores exibidos na frente de cada produto no
     * editor. Reaproveita o mesmo plumbing das análises ABC/Paper
     * (`ProductSalesAggregateQuery`: join em products por codigo_erp +
     * agrupamento por produto) e o value object `SalesSummary` — mantendo as
     * fórmulas de preço/margem numa fonte única.
     *
     * Observação: como a agregação casa vendas por `codigo_erp` (consistente com
     * a análise ABC), os valores podem divergir levemente do resumo por produto
     * do sidebar, que casa por product_id OU ean OU codigo_erp.
     *
     * @return array<int, array{
     *     product_id: string,
     *     ean: string|null,
     *     avg_price: float,
     *     avg_cost: float,
     *     avg_margin: float,
     *     gross_margin_pct: float,
     *     net_margin_pct: float
     * }>
     */
    public function indicatorsForGondola(string $gondolaId, SalesFilters $filters): array
    {
        // IDs dos produtos efetivamente presentes na gôndola (via layers ativos).
        $productIds = Layer::query()
            ->join('segments', 'segments.id', '=', 'layers.segment_id')
            ->join('shelves', 'shelves.id', '=', 'segments.shelf_id')
            ->join('sections', 'sections.id', '=', 'shelves.section_id')
            ->where('sections.gondola_id', $gondolaId)
            ->whereNotNull('layers.product_id')
            ->whereNull('layers.deleted_at')
            ->whereNull('segments.deleted_at')
            ->whereNull('shelves.deleted_at')
            ->whereNull('sections.deleted_at')
            ->distinct()
            ->pluck('layers.product_id')
            ->all();

        return $this->indicatorsForProductIds($productIds, $filters);
    }

    /**
     * Núcleo da agregação de indicadores: dado um conjunto de produtos, soma as
     * vendas por produto numa única query e devolve preço/margem keyed por EAN.
     * Separado de `indicatorsForGondola` para ser testável sem montar toda a
     * hierarquia física (gôndola → seção → prateleira → segmento → layer).
     *
     * @param  array<int, string>  $productIds
     * @return array<int, array{
     *     product_id: string,
     *     ean: string|null,
     *     avg_price: float,
     *     avg_cost: float,
     *     avg_margin: float,
     *     gross_margin_pct: float,
     *     net_margin_pct: float
     * }>
     */
    public function indicatorsForProductIds(array $productIds, SalesFilters $filters): array
    {
        if (empty($productIds)) {
            return [];
        }

        // Mapa product_id → ean (chave usada pelos selos no frontend) e os
        // codigos_erp para casar as vendas.
        $eansById = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('ean', 'id');

        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->unique()
            ->all();

        if (empty($codigosErp)) {
            return [];
        }

        // Agregação por produto numa única query, reusando o plumbing das análises.
        $agg = ProductSalesAggregateQuery::for('sales');

        $storeFilter = $filters->storeId !== null ? ['store_id' => $filters->storeId] : [];

        $query = $agg->groupedByProduct($codigosErp, $productIds, $storeFilter)
            ->addSelect([
                $agg->sum('total_sale_quantity', 'total_quantity'),
                $agg->sum('total_sale_value', 'total_value'),
                $agg->sum('acquisition_cost', 'total_acquisition_cost'),
                $agg->sum('margem_contribuicao', 'total_margem_contribuicao'),
            ]);

        $agg->applyPeriod($query, $filters->saleDateFrom, $filters->saleDateTo);

        // Constrói SalesSummary por produto (fórmulas centralizadas) e devolve
        // apenas os indicadores, descartando produtos sem EAN (sem chave de selo).
        return $query->get()
            ->map(function (object $row) use ($eansById): array {
                $summary = SalesSummary::fromAggregate($row);

                return [
                    'product_id' => (string) $row->product_id,
                    'ean' => $eansById[$row->product_id] ?? null,
                    'avg_price' => $summary->avgPrice(),
                    'avg_cost' => $summary->avgCost(),
                    'avg_margin' => $summary->avgMargin(),
                    'gross_margin_pct' => $summary->grossMarginPct(),
                    'net_margin_pct' => $summary->netMarginPct(),
                ];
            })
            ->filter(fn (array $row): bool => $row['ean'] !== null && $row['ean'] !== '')
            ->values()
            ->all();
    }

    /**
     * Evolução mensal das vendas (mês, contagem, quantidade, faturamento).
     *
     * Com período informado, respeita o intervalo; sem período, considera os
     * últimos 12 meses. Resolve a expressão de mês conforme o driver do banco.
     *
     * @return Collection<int, array{month: string, sales_count: int, quantity: float, revenue: float}>
     */
    public function salesByMonth(object $product, SalesFilters $filters): Collection
    {
        $monthExpr = $this->monthExpression();

        $base = SalesQuery::make()->forProduct($product);

        if ($filters->hasPeriod()) {
            $base->betweenDates($filters->saleDateFrom, $filters->saleDateTo);
        } else {
            $base->builder()->where('sale_date', '>=', now()->subMonths(12));
        }

        return $base->builder()
            ->selectRaw("{$monthExpr} as month, COUNT(*) as sales_count, SUM(total_sale_quantity) as quantity, SUM(total_sale_value) as revenue")
            ->groupByRaw($monthExpr)
            ->orderByRaw($monthExpr)
            ->get()
            ->map(fn ($item): array => [
                'month' => (string) $item->month,
                'sales_count' => (int) $item->sales_count,
                'quantity' => (float) $item->quantity,
                'revenue' => (float) $item->revenue,
            ]);
    }

    /**
     * Top lojas por faturamento no período (store_id, contagem, quantidade, faturamento).
     *
     * @return Collection<int, array{store_id: mixed, store_name: string, sales_count: int, quantity: float, revenue: float}>
     */
    public function topStores(object $product, SalesFilters $filters, int $limit = 5): Collection
    {
        return SalesQuery::make()
            ->forProduct($product)
            ->applyFilters($filters)
            ->builder()
            ->selectRaw('store_id, COUNT(*) as sales_count, SUM(total_sale_quantity) as quantity, SUM(total_sale_value) as revenue')
            ->groupBy('store_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'store_id' => $row->store_id,
                'store_name' => $row->store?->name ?? 'Loja não encontrada',
                'sales_count' => (int) $row->sales_count,
                'quantity' => (float) $row->quantity,
                'revenue' => (float) $row->revenue,
            ]);
    }

    /**
     * Expressão SQL para extrair 'YYYY-MM' da data de venda conforme o driver.
     */
    private function monthExpression(): string
    {
        $driver = Sale::getModel()->getConnection()->getDriverName();

        return $driver === 'pgsql'
            ? "TO_CHAR(sale_date, 'YYYY-MM')"
            : "strftime('%Y-%m', sale_date)";
    }
}
