<?php

namespace App\Services\AutoPlanogram\Scoring;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Illuminate\Support\Collection;

final class SalesMetricsRepository
{
    use UsesPlannerateTenantDatabase;

    /**
     * Agrega métricas de vendas de monthly_sales_summaries na janela configurada.
     *
     * @param  Collection<int, string>  $productIds
     * @return array<string, array{quantity: int, margem: float, doh: float|null}>
     */
    public function fetchMetrics(
        string $tenantId,
        Collection $productIds,
        int $windowMonths,
        ?string $storeId = null,
    ): array {
        $startMonth = now()->subMonths($windowMonths)->startOfMonth()->toDateString();

        $query = $this->plannerateTenantDatabase()
            ->table('monthly_sales_summaries')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $productIds)
            ->where('sale_month', '>=', $startMonth)
            ->selectRaw('
                product_id,
                SUM(total_sale_quantity) as total_quantity,
                SUM(margem_contribuicao) as total_margem,
                AVG(total_sale_quantity) as avg_monthly_quantity
            ')
            ->groupBy('product_id');

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query->get()
            ->keyBy('product_id')
            ->map(fn ($r) => [
                'quantity' => (int) $r->total_quantity,
                'margem' => (float) $r->total_margem,
                'doh' => null, // integrar com estoque quando disponível
            ])
            ->all();
    }
}
