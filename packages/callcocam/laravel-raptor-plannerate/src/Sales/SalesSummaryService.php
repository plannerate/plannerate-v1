<?php

namespace Callcocam\LaravelRaptorPlannerate\Sales;

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
