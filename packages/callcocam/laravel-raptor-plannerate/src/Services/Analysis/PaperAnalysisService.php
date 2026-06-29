<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Analysis;

use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesStatistics;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de Análise de Papel (Product Role Analysis).
 *
 * Calcula o papel estratégico de cada produto dentro de sua categoria,
 * cruzando dois indicadores:
 *   - Market Share   → participação no valor total vendido da categoria no período atual
 *   - Growth Rate    → variação percentual em relação ao período anterior
 *
 * Papéis resultantes (eixo share × crescimento):
 *   - leader   : alto share + alto crescimento  → produto principal, prioridade máxima
 *   - anchor   : alto share + baixo crescimento → âncora de receita, manter exposição
 *   - rising   : baixo share + alto crescimento → produto em ascensão, ampliar frentes
 *   - lagging  : baixo share + baixo crescimento → produto em declínio, revisar mix
 */
class PaperAnalysisService
{
    /**
     * Limiar de crescimento: produtos com growth_rate acima deste valor são "alto crescimento".
     *
     * null (padrão) = limiar relativo: usa a mediana dos crescimentos da categoria
     * (excluindo produtos novos), o que garante que os quatro papéis existam mesmo
     * em períodos de alta generalizada. Um valor explícito via setGrowthThreshold()
     * desativa a mediana e aplica o limiar fixo a todas as categorias.
     */
    private ?float $growthThreshold = null;

    /**
     * Define um limiar de crescimento fixo para classificar "alto crescimento".
     * Quando definido, substitui a mediana por categoria em todas as categorias.
     */
    public function setGrowthThreshold(float $threshold): self
    {
        $this->growthThreshold = $threshold;

        return $this;
    }

    /**
     * Executa a análise de papel para uma lista de produtos da gôndola.
     *
     * @param  array  $productIds  IDs dos produtos fisicamente alocados na gôndola
     * @param  string  $tableType  Fonte dos dados: 'sales' ou 'monthly_summaries'
     * @param  array  $currentFilters  Filtros do período atual (obrigatório: tenant_id)
     * @param  array  $previousFilters  Filtros do período anterior (calculado automaticamente se vazio)
     */
    public function analyzeByProductIds(
        array $productIds,
        string $tableType = 'sales',
        array $currentFilters = [],
        array $previousFilters = []
    ): Collection {
        if (empty($productIds)) {
            Log::warning('PaperAnalysis - Lista de product_ids vazia');

            return collect();
        }

        if (! isset($currentFilters['tenant_id']) || empty($currentFilters['tenant_id'])) {
            Log::error('PaperAnalysis - tenant_id é obrigatório');
            throw new \InvalidArgumentException('tenant_id é obrigatório para a Análise de Papel');
        }

        // Busca codigo_erp dos produtos para join com as tabelas de venda
        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->toArray();

        if (empty($codigosErp)) {
            Log::warning('PaperAnalysis - Nenhum codigo_erp encontrado para os produtos');

            return collect();
        }

        // Calcula automaticamente o período anterior com base na duração do período atual
        if (empty($previousFilters)) {
            $previousFilters = $this->buildPreviousPeriodFilters($currentFilters, $tableType);
        }

        Log::info('PaperAnalysis - Iniciando cálculo', [
            'tenant_id' => $currentFilters['tenant_id'],
            'table_type' => $tableType,
            'product_count' => count($productIds),
            'current_filters' => $currentFilters,
            'previous_filters' => $previousFilters,
        ]);

        Log::info('PaperAnalysis - Produtos e codigos_erp', [
            'product_ids_count' => count($productIds),
            'codigos_erp_count' => count($codigosErp),
            'previous_filters' => $previousFilters,
        ]);

        // Busca dados agregados dos dois períodos
        $currentData = $this->getSalesData($codigosErp, $productIds, $tableType, $currentFilters);
        $previousData = $this->getSalesData($codigosErp, $productIds, $tableType, $previousFilters);

        Log::info('PaperAnalysis - Vendas encontradas', [
            'current_data_rows' => $currentData->count(),
            'previous_data_rows' => $previousData->count(),
        ]);

        // Indexa dados do período anterior por product_id para lookup O(1)
        $previousByProduct = $previousData->keyBy('product_id');

        // Cruza dados dos dois períodos por produto
        $combined = $currentData->map(function ($current) use ($previousByProduct) {
            $previous = $previousByProduct->get($current->product_id);

            return (object) [
                'product_id' => $current->product_id,
                'category_id' => $current->category_id,
                'valor_atual' => (float) ($current->valor ?? 0),
                'valor_anterior' => (float) ($previous?->valor ?? 0),
            ];
        });

        // Inclui produtos sem venda no período atual (valor zero) para não sumir da análise
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

        Log::info('PaperAnalysis - Distribuição de papéis antes da classificação', [
            'combined_count' => $combined->count(),
        ]);

        // Etapa pura: share, crescimento, medianas e papel (testável sem banco)
        $classified = $this->classifyMetrics($combined);

        // Carrega dados completos dos produtos para enriquecer o resultado
        $productsData = Product::with(['category'])->whereIn('id', $productIds)->get()->keyBy('id');

        // Enriquece com dados cadastrais e monta o resultado final
        return $classified->map(function (array $item) use ($productsData) {
            $product = $productsData->get($item['product_id']);

            return array_merge($item, [
                'product_name' => $product?->name ?? '',
                'ean' => $product?->ean ?? '',
                'image_url' => $product?->image_url ?? null,
                'category_name' => $product?->category?->name ?? '',
            ]);
        })->values()->tap(function ($results) {
            Log::info('PaperAnalysis - Resultado final', [
                'total' => $results->count(),
                'leader' => $results->where('role', 'leader')->count(),
                'anchor' => $results->where('role', 'anchor')->count(),
                'rising' => $results->where('role', 'rising')->count(),
                'lagging' => $results->where('role', 'lagging')->count(),
                'novos' => $results->where('is_new', true)->count(),
            ]);
        });
    }

    /**
     * Etapa pura da análise de papel: calcula market share, crescimento, medianas
     * por categoria e classifica o papel de cada produto. Não consulta o banco.
     *
     * Regras:
     *   - market_share = participação do produto no valor da categoria (período atual)
     *   - growth_rate  = variação % vs período anterior; null quando não há base de
     *     comparação (produto novo ou sem venda nos dois períodos)
     *   - produto novo (sem venda anterior, com venda atual) → papel 'rising' com
     *     is_new = true (item em introdução: ganha exposição, mas sinalizado à parte)
     *   - limiar de crescimento = mediana dos growth_rate da categoria (produtos novos
     *     ficam fora do cálculo), salvo limiar fixo definido via setGrowthThreshold()
     *
     * @param  Collection  $combined  Itens com product_id, category_id, valor_atual, valor_anterior
     * @return Collection<int, array{product_id: mixed, category_id: mixed, role: string, is_new: bool, market_share: float, growth_rate: float|null, growth_threshold: float, share_threshold: float, total_value_current: float, total_value_previous: float}>
     */
    public function classifyMetrics(Collection $combined): Collection
    {
        // Totais por categoria no período atual (denominador do market share)
        $categoryTotals = $combined->groupBy('category_id')->map(
            fn ($items) => $items->sum('valor_atual')
        );

        // Calcula market_share, growth_rate e flag de produto novo por produto
        $withMetrics = $combined->map(function ($item) use ($categoryTotals) {
            $categoryTotal = (float) ($categoryTotals->get($item->category_id) ?? 0);

            $marketShare = SalesStatistics::marketShare((float) $item->valor_atual, $categoryTotal);

            // Produto novo: vendeu no período atual sem histórico anterior — não há
            // base para calcular crescimento (growth_rate = null, tratado à parte)
            $isNew = $item->valor_anterior <= 0 && $item->valor_atual > 0;

            $growthRate = SalesStatistics::growthRate((float) $item->valor_atual, (float) $item->valor_anterior);

            return (object) [
                'product_id' => $item->product_id,
                'category_id' => $item->category_id,
                'valor_atual' => $item->valor_atual,
                'valor_anterior' => $item->valor_anterior,
                'market_share' => round($marketShare, 4),
                'growth_rate' => $growthRate,
                'is_new' => $isNew,
            ];
        });

        // Mediana do market_share por categoria — divisor entre alto e baixo share
        $shareThresholds = $withMetrics->groupBy('category_id')->map(
            fn ($items) => SalesStatistics::median($items->pluck('market_share')) ?? 0.0
        );

        // Mediana do growth_rate por categoria (produtos sem base de comparação ficam
        // fora) — divisor entre alto e baixo crescimento quando não há limiar fixo
        $growthThresholds = $withMetrics->groupBy('category_id')->map(
            fn ($items) => SalesStatistics::median($items->pluck('growth_rate')->filter(fn ($g) => $g !== null)) ?? 0.0
        );

        // Classifica cada produto
        return $withMetrics->map(function ($item) use ($shareThresholds, $growthThresholds) {
            $shareThreshold = (float) ($shareThresholds->get($item->category_id) ?? 0);
            $growthThreshold = $this->growthThreshold
                ?? (float) ($growthThresholds->get($item->category_id) ?? 0);

            $role = $item->is_new
                ? 'rising'
                : $this->classifyRole($item->market_share, $item->growth_rate, $shareThreshold, $growthThreshold);

            return [
                'product_id' => $item->product_id,
                'category_id' => $item->category_id,
                'role' => $role,
                'is_new' => $item->is_new,
                'market_share' => $item->market_share,
                'growth_rate' => $item->growth_rate,
                'growth_threshold' => round($growthThreshold, 4),
                'share_threshold' => round($shareThreshold, 4),
                'total_value_current' => $item->valor_atual,
                'total_value_previous' => $item->valor_anterior,
            ];
        })->values();
    }

    // Mediana centralizada em SalesStatistics::median().

    /**
     * Retorna os IDs dos produtos fisicamente alocados em uma gôndola.
     * Usa joins explícitos porque o modelo Layer não define relacionamentos para cima na hierarquia.
     */
    public function getProductIdsByGondola(string $gondolaId): array
    {
        return Layer::query()
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
            ->toArray();
    }

    /**
     * Classifica o produto em um dos quatro papéis estratégicos.
     *
     *   leader : share ≥ mediana AND crescimento ≥ limiar  → prioridade máxima
     *   anchor : share ≥ mediana AND crescimento < limiar  → receita estável
     *   rising : share < mediana AND crescimento ≥ limiar  → potencial de crescimento
     *   lagging: share < mediana AND crescimento < limiar  → candidato à revisão de mix
     *
     * growth_rate null aqui significa produto sem venda nos dois períodos (produto
     * novo é tratado antes, em classifyMetrics) → baixo crescimento por definição.
     */
    private function classifyRole(float $marketShare, ?float $growthRate, float $shareThreshold, float $growthThreshold): string
    {
        $isHighShare = $marketShare >= $shareThreshold;
        $isHighGrowth = $growthRate !== null && $growthRate >= $growthThreshold;

        return match (true) {
            $isHighShare && $isHighGrowth => 'leader',
            $isHighShare && ! $isHighGrowth => 'anchor',
            ! $isHighShare && $isHighGrowth => 'rising',
            default => 'lagging',
        };
    }

    /**
     * Calcula os filtros do período anterior com base na duração do período atual.
     * O período anterior tem a mesma duração, deslocado para antes do início do período atual.
     * Protected para permitir teste isolado via subclasse anônima.
     */
    protected function buildPreviousPeriodFilters(array $currentFilters, string $tableType): array
    {
        // Mantém todos os filtros exceto as datas — serão recalculadas
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
     * Busca dados agregados de vendas por período, delegando para a query correta
     * conforme o tipo de tabela (transacional vs. sumários mensais).
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
     * Query para tabela de vendas transacionais (sales).
     * Agrega por produto filtrando por intervalo de datas diárias.
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
     * Query para tabela de sumários mensais (monthly_sales_summaries).
     * Agrega por produto filtrando por intervalo de meses.
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
            // Usa o último dia do mês para garantir que o mês inteiro seja incluído
            $endDate = Carbon::createFromFormat('Y-m', $filters['end_month'])->endOfMonth()->format('Y-m-d');
            $query->where('monthly_sales_summaries.sale_month', '<=', $endDate);
        }

        return $query;
    }
}
