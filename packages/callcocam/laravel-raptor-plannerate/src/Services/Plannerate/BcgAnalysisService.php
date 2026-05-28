<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service para análise BCG de produtos
 *
 * Calcula a Matriz BCG (Boston Consulting Group) usando dois períodos:
 * - Período atual: para calcular o market share dentro da categoria
 * - Período anterior: para calcular a taxa de crescimento
 *
 * Quadrantes:
 * - Estrela (star):        alto market share + alto crescimento
 * - Vaca Leiteira (cash_cow):   alto market share + baixo crescimento
 * - Interrogação (question_mark): baixo market share + alto crescimento
 * - Abacaxi (dog):         baixo market share + baixo crescimento
 */
class BcgAnalysisService
{
    /**
     * Limiar de crescimento: produtos com crescimento acima deste valor são "alto crescimento"
     * Padrão 0 = crescimento positivo vs negativo
     */
    private float $growthThreshold = 0.0;

    /**
     * Configura o limiar de crescimento
     */
    public function setGrowthThreshold(float $threshold): self
    {
        $this->growthThreshold = $threshold;

        return $this;
    }

    /**
     * Executa análise BCG por lista de IDs de produtos
     *
     * @param  array  $productIds  IDs dos produtos na gôndola
     * @param  string  $tableType  'sales' ou 'monthly_summaries'
     * @param  array  $currentFilters  Filtros do período atual (deve incluir tenant_id)
     * @param  array  $previousFilters  Filtros do período anterior (calculado automaticamente se vazio)
     */
    public function analyzeByProductIds(
        array $productIds,
        string $tableType = 'sales',
        array $currentFilters = [],
        array $previousFilters = []
    ): Collection {
        if (empty($productIds)) {
            Log::warning('BCG Analysis - Lista de product_ids vazia');

            return collect();
        }

        if (! isset($currentFilters['tenant_id']) || empty($currentFilters['tenant_id'])) {
            Log::error('BCG Analysis - tenant_id é obrigatório');
            throw new \InvalidArgumentException('tenant_id é obrigatório para análise BCG');
        }

        // Busca codigo_erp dos produtos
        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->toArray();

        if (empty($codigosErp)) {
            Log::warning('BCG Analysis - Nenhum codigo_erp encontrado para os produtos');

            return collect();
        }

        // Se não tiver período anterior, calcula automaticamente com base no período atual
        if (empty($previousFilters)) {
            $previousFilters = $this->buildPreviousPeriodFilters($currentFilters, $tableType);
        }

        Log::info('BCG Analysis - Iniciando cálculo', [
            'tenant_id' => $currentFilters['tenant_id'],
            'table_type' => $tableType,
            'product_count' => count($productIds),
            'current_filters' => $currentFilters,
            'previous_filters' => $previousFilters,
        ]);

        // Busca dados dos dois períodos
        $currentData = $this->getSalesData($codigosErp, $productIds, $tableType, $currentFilters);
        $previousData = $this->getSalesData($codigosErp, $productIds, $tableType, $previousFilters);

        // Indexa dados anteriores por product_id para lookup O(1)
        $previousByProduct = $previousData->keyBy('product_id');

        // Monta resultado combinado com os dois períodos
        $combined = $currentData->map(function ($current) use ($previousByProduct) {
            $previous = $previousByProduct->get($current->product_id);

            return (object) [
                'product_id' => $current->product_id,
                'category_id' => $current->category_id,
                'valor_atual' => (float) ($current->valor ?? 0),
                'valor_anterior' => (float) ($previous?->valor ?? 0),
            ];
        });

        // Inclui produtos sem vendas no período atual (com valor zero)
        $productsWithCurrentSales = $currentData->pluck('product_id')->toArray();
        $productsWithoutCurrentSales = array_diff($productIds, $productsWithCurrentSales);

        if (! empty($productsWithoutCurrentSales)) {
            $zeroRecords = Product::query()
                ->whereIn('id', $productsWithoutCurrentSales)
                ->select('id', 'category_id')
                ->get()
                ->toBase()
                ->map(function ($p) use ($previousByProduct) {
                    $previous = $previousByProduct->get($p->id);

                    return (object) [
                        'product_id' => $p->id,
                        'category_id' => $p->category_id,
                        'valor_atual' => 0.0,
                        'valor_anterior' => (float) ($previous?->valor ?? 0),
                    ];
                });

            $combined = $combined->merge($zeroRecords);
        }

        // Calcula totais por categoria (período atual)
        $categoryTotals = $combined->groupBy('category_id')->map(function ($items) {
            return $items->sum('valor_atual');
        });

        // Calcula market_share e growth_rate para cada produto
        $withMetrics = $combined->map(function ($item) use ($categoryTotals) {
            $categoryTotal = (float) ($categoryTotals->get($item->category_id) ?? 0);

            $marketShare = $categoryTotal > 0
                ? ($item->valor_atual / $categoryTotal) * 100
                : 0.0;

            $growthRate = $item->valor_anterior > 0
                ? (($item->valor_atual - $item->valor_anterior) / $item->valor_anterior) * 100
                : ($item->valor_atual > 0 ? 100.0 : 0.0);

            return (object) [
                'product_id' => $item->product_id,
                'category_id' => $item->category_id,
                'valor_atual' => $item->valor_atual,
                'valor_anterior' => $item->valor_anterior,
                'market_share' => round($marketShare, 4),
                'growth_rate' => round($growthRate, 4),
            ];
        });

        // Calcula mediana do market_share por categoria (usada como limiar)
        $shareThresholds = $withMetrics->groupBy('category_id')->map(function ($items) {
            $shares = $items->pluck('market_share')->sort()->values();
            $count = $shares->count();

            if ($count === 0) {
                return 0.0;
            }

            $mid = intdiv($count, 2);

            return $count % 2 === 0
                ? ($shares[$mid - 1] + $shares[$mid]) / 2
                : (float) $shares[$mid];
        });

        // Busca dados completos dos produtos
        $productsData = Product::with(['category'])->whereIn('id', $productIds)->get()->keyBy('id');

        // Classifica nos quadrantes e monta resultado final
        return $withMetrics->map(function ($item) use ($shareThresholds, $productsData) {
            $shareThreshold = (float) ($shareThresholds->get($item->category_id) ?? 0);
            $quadrant = $this->classifyQuadrant($item->market_share, $item->growth_rate, $shareThreshold);

            $product = $productsData->get($item->product_id);

            return [
                'product_id' => $item->product_id,
                'product_name' => $product?->name ?? '',
                'ean' => $product?->ean ?? '',
                'image_url' => $product?->image_url ?? null,
                'category_id' => $item->category_id,
                'category_name' => $product?->category?->name ?? '',
                'quadrant' => $quadrant,
                'market_share' => $item->market_share,
                'growth_rate' => $item->growth_rate,
                'total_value_current' => $item->valor_atual,
                'total_value_previous' => $item->valor_anterior,
                'share_threshold' => round($shareThreshold, 4),
            ];
        })->values();
    }

    /**
     * Obtém os IDs dos produtos alocados fisicamente em uma gôndola
     */
    public function getProductIdsByGondola(string $gondolaId): array
    {
        return Layer::query()
            ->withoutGlobalScopes()
            ->whereHas('segment.shelf.section', function ($q) use ($gondolaId) {
                $q->where('gondola_id', $gondolaId);
            })
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Classifica o produto em um dos quatro quadrantes da Matriz BCG
     */
    private function classifyQuadrant(float $marketShare, float $growthRate, float $shareThreshold): string
    {
        $isHighShare = $marketShare >= $shareThreshold;
        $isHighGrowth = $growthRate >= $this->growthThreshold;

        return match (true) {
            $isHighShare && $isHighGrowth => 'star',
            $isHighShare && ! $isHighGrowth => 'cash_cow',
            ! $isHighShare && $isHighGrowth => 'question_mark',
            default => 'dog',
        };
    }

    /**
     * Constrói filtros do período anterior com base no período atual
     * O período anterior tem a mesma duração do período atual, deslocado para antes
     */
    private function buildPreviousPeriodFilters(array $currentFilters, string $tableType): array
    {
        $previous = array_filter($currentFilters, fn ($key) => ! in_array($key, [
            'date_from', 'date_to', 'start_month', 'end_month',
        ]), ARRAY_FILTER_USE_KEY);

        if ($tableType === 'monthly_summaries' && isset($currentFilters['start_month'], $currentFilters['end_month'])) {
            $start = Carbon::createFromFormat('Y-m', $currentFilters['start_month']);
            $end = Carbon::createFromFormat('Y-m', $currentFilters['end_month']);
            $diffMonths = $start->diffInMonths($end) + 1;

            $previous['start_month'] = $start->subMonths($diffMonths)->format('Y-m');
            $previous['end_month'] = $end->subMonths($diffMonths)->format('Y-m');
        } elseif (isset($currentFilters['date_from'], $currentFilters['date_to'])) {
            $from = Carbon::parse($currentFilters['date_from']);
            $to = Carbon::parse($currentFilters['date_to']);
            $diffDays = $from->diffInDays($to) + 1;

            $previous['date_from'] = $from->subDays($diffDays)->format('Y-m-d');
            $previous['date_to'] = $to->subDays($diffDays)->format('Y-m-d');
        }

        return $previous;
    }

    /**
     * Busca dados agregados de vendas por período
     */
    private function getSalesData(array $codigosErp, array $productIds, string $tableType, array $filters): Collection
    {
        if (empty($filters)) {
            return collect();
        }

        $query = match ($tableType) {
            'monthly_summaries' => $this->getMonthlySummariesQuery($codigosErp, $productIds, $filters),
            default => $this->getSalesQuery($codigosErp, $productIds, $filters),
        };

        return $query->get()->toBase();
    }

    /**
     * Query para tabela sales
     */
    private function getSalesQuery(array $codigosErp, array $productIds, array $filters): Builder
    {
        $query = Sale::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'sales.codigo_erp')
            ->select([
                'products.id as product_id',
                'products.category_id',
                DB::raw('SUM(sales.total_sale_value) as valor'),
            ])
            ->whereIn('sales.codigo_erp', $codigosErp)
            ->whereIn('products.id', $productIds)
            ->groupBy('products.id', 'products.category_id');

        if (isset($filters['store_id'])) {
            $query->where('sales.store_id', $filters['store_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('sales.sale_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('sales.sale_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Query para tabela monthly_sales_summaries
     */
    private function getMonthlySummariesQuery(array $codigosErp, array $productIds, array $filters): Builder
    {
        $query = MonthlySalesSummary::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'monthly_sales_summaries.codigo_erp')
            ->select([
                'products.id as product_id',
                'products.category_id',
                DB::raw('SUM(monthly_sales_summaries.total_sale_value) as valor'),
            ])
            ->whereIn('monthly_sales_summaries.codigo_erp', $codigosErp)
            ->whereIn('products.id', $productIds)
            ->groupBy('products.id', 'products.category_id');

        if (isset($filters['store_id'])) {
            $query->where('monthly_sales_summaries.store_id', $filters['store_id']);
        }

        if (isset($filters['start_month'])) {
            $query->where('monthly_sales_summaries.sale_month', '>=', $filters['start_month'].'-01');
        }

        if (isset($filters['end_month'])) {
            // Usa o último dia do mês final
            $endDate = Carbon::createFromFormat('Y-m', $filters['end_month'])->endOfMonth()->format('Y-m-d');
            $query->where('monthly_sales_summaries.sale_month', '<=', $endDate);
        }

        return $query;
    }
}
