<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service para cálculo de Estoque Alvo
 *
 * Baseado na lógica do arquivo docs/stoke-alvo.md (VBA)
 * Depende da classificação ABC para funcionar corretamente
 */
class TargetStockService
{
    /**
     * Parâmetros padrão de nível de serviço por classe ABC
     */
    private array $niveisServico = [
        'A' => 0.7,
        'B' => 0.8,
        'C' => 0.9,
    ];

    /**
     * Parâmetros padrão de cobertura em dias por classe ABC
     */
    private array $coberturaDias = [
        'A' => 2,
        'B' => 5,
        'C' => 7,
    ];

    /**
     * Tipo de período: 'daily' ou 'monthly'
     */
    private string $periodType = 'daily';

    /**
     * Configura os níveis de serviço por classe ABC
     */
    public function setServiceLevels(float $nivelA, float $nivelB, float $nivelC): self
    {
        $this->niveisServico = [
            'A' => $nivelA,
            'B' => $nivelB,
            'C' => $nivelC,
        ];

        return $this;
    }

    /**
     * Configura os dias de cobertura por classe ABC
     */
    public function setCoverageDays(int $diasA, int $diasB, int $diasC): self
    {
        $this->coberturaDias = [
            'A' => $diasA,
            'B' => $diasB,
            'C' => $diasC,
        ];

        return $this;
    }

    /**
     * Configura o tipo de período
     */
    public function setPeriodType(string $type): self
    {
        $this->periodType = $type; // 'daily' ou 'monthly'

        return $this;
    }

    /**
     * Calcula estoque alvo para produtos baseado em EANs e classificação ABC
     *
     * @param  array  $abcResults  Resultados da análise ABC (deve conter classificacao)
     * @param  string  $tableType  Tipo de tabela: 'sales' ou 'monthly_summaries'
     * @param  array  $filters  Filtros adicionais (tenant_id, date_from, date_to, etc)
     * @param  array  $currentStock  Array [ean => estoque_atual] opcional
     */
    public function calculateByAbcResults(array $abcResults, string $tableType, array $filters, array $currentStock = []): Collection
    {
        if (empty($abcResults)) {
            return collect();
        }

        // Extrai EANs e classificações dos resultados ABC
        $eans = array_column($abcResults, 'ean');
        $classificacoes = [];
        $abcResultsByEan = [];
        foreach ($abcResults as $result) {
            $classificacoes[$result['ean']] = $result['classificacao'];
            $abcResultsByEan[$result['ean']] = $result;
        }

        // Valida se tenant_id está presente
        if (! isset($filters['tenant_id']) || empty($filters['tenant_id'])) {
            Log::error('TargetStock - calculateByAbcResults: tenant_id não informado nos filtros', [
                'filters' => $filters,
            ]);
            throw new \InvalidArgumentException('tenant_id é obrigatório para calcular estoque alvo');
        }

        // Se não foi passado estoque atual, busca diretamente da tabela products
        if (empty($currentStock)) {
            $currentStock = Product::query()
                ->whereIn('ean', $eans)
                ->whereNotNull('current_stock')
                ->pluck('current_stock', 'ean')
                ->toArray();

            Log::info('TargetStock - current_stock carregado da tabela products', [
                'eans_com_estoque' => count($currentStock),
            ]);
        }

        // Busca estatísticas agregadas diretamente do banco (média e desvio padrão)
        $salesStats = $this->getAggregatedSalesStats($eans, $tableType, $filters);

        Log::info('TargetStock - Estatísticas agregadas encontradas', [
            'total_eans' => count($eans),
            'stats_count' => $salesStats->count(),
        ]);

        // Indexa estatísticas por EAN para acesso O(1)
        $statsByEan = $salesStats->keyBy('ean');

        // Processa resultados
        $results = collect();

        foreach ($abcResults as $abcResult) {
            $ean = $abcResult['ean'];
            $productId = $abcResult['product_id'];
            $productName = $abcResult['product_name'] ?? '';
            $classificacao = $abcResult['classificacao'] ?? 'C';

            // Obtém estatísticas do banco (já calculadas)
            $stats = $statsByEan->get($ean);
            $media = $stats ? (float) $stats->media : 0.0;
            $desvioPadrao = $stats ? (float) $stats->desvio_padrao : 0.0;
            $variabilidade = $media > 0 ? $desvioPadrao / $media : 0.0;

            // Obtém parâmetros baseados na classificação ABC
            $nivelServico = $this->niveisServico[$classificacao] ?? 0.9;
            $coberturaDias = $this->coberturaDias[$classificacao] ?? 7;

            // Valida nível de serviço
            if ($nivelServico < 0.5 || $nivelServico >= 1) {
                continue;
            }

            // Calcula Z-score (inverso da distribuição normal padrão)
            $zScore = $this->calculateZScore($nivelServico);

            // Calcula estoques
            $estoqueSeguranca = round($zScore * $desvioPadrao, 0);
            $estoqueMinimo = round($media * $coberturaDias, 0);
            $estoqueAlvo = round($estoqueMinimo + $estoqueSeguranca, 0);

            // Estoque atual (padrão: 0 se não fornecido)
            $estoqueAtual = $currentStock[$ean] ?? 0;
            $permiteFrentes = $estoqueAtual >= $estoqueAlvo ? 'Sim' : 'Não';

            $results->push([
                'product_id' => $productId,
                'product_name' => $productName,
                'ean' => $ean,
                'classificacao' => $classificacao,
                'demanda_media' => round($media, 2),
                'desvio_padrao' => round($desvioPadrao, 2),
                'variabilidade' => round($variabilidade, 2),
                'cobertura_dias' => $coberturaDias,
                'nivel_servico' => $nivelServico,
                'z_score' => round($zScore, 3),
                'estoque_seguranca' => $estoqueSeguranca,
                'estoque_minimo' => $estoqueMinimo,
                'estoque_alvo' => $estoqueAlvo,
                'estoque_atual' => $estoqueAtual,
                'permite_frentes' => $permiteFrentes,
                'alerta_variabilidade' => $variabilidade > 1, // Alerta se variabilidade > 100%
            ]);
        }

        return $results;
    }

    /**
     * Busca estatísticas agregadas de vendas (média e desvio padrão) diretamente do banco
     * Isso é muito mais eficiente do que buscar todos os registros e calcular em PHP
     */
    private function getAggregatedSalesStats(array $eans, string $tableType, array $filters): Collection
    {
        $query = match ($tableType) {
            'monthly_summaries' => $this->getMonthlySummariesAggregatedQuery($eans, $filters),
            default => $this->getSalesAggregatedQuery($eans, $filters),
        };

        return $query->get();
    }

    /**
     * Query agregada para tabela sales - calcula AVG e STDDEV_POP no banco
     */
    private function getSalesAggregatedQuery(array $eans, array $filters): Builder
    {
        if (empty($eans)) {
            Log::warning('TargetStock - getSalesAggregatedQuery: EANs vazios');
        }

        // Calcula média e desvio padrão diretamente no PostgreSQL
        $query = Sale::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'sales.codigo_erp')
            ->select([
                'products.ean',
                DB::raw('AVG(sales.total_sale_quantity) as media'),
                DB::raw('COALESCE(STDDEV_POP(sales.total_sale_quantity), 0) as desvio_padrao'),
                DB::raw('COUNT(*) as total_registros'),
            ])
            ->whereIn('products.ean', $eans)
            ->groupBy('products.ean');

        if (isset($filters['store_id'])) {
            $query->where('sales.store_id', $filters['store_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('sales.sale_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('sales.sale_date', '<=', $filters['date_to']);
        }

        Log::info('TargetStock - getSalesAggregatedQuery SQL', [
            'sql' => $query->toSql(),
            'eans_count' => count($eans),
        ]);

        return $query;
    }

    /**
     * Query agregada para tabela monthly_sales_summaries - calcula AVG e STDDEV_POP no banco
     */
    private function getMonthlySummariesAggregatedQuery(array $eans, array $filters): Builder
    {
        // Calcula média e desvio padrão diretamente no PostgreSQL
        $query = MonthlySalesSummary::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'monthly_sales_summaries.codigo_erp')
            ->select([
                'products.ean',
                DB::raw('AVG(monthly_sales_summaries.total_sale_quantity) as media'),
                DB::raw('COALESCE(STDDEV_POP(monthly_sales_summaries.total_sale_quantity), 0) as desvio_padrao'),
                DB::raw('COUNT(*) as total_registros'),
            ])
            ->whereIn('products.ean', $eans)
            ->groupBy('products.ean');

        if (isset($filters['store_id'])) {
            $query->where('monthly_sales_summaries.store_id', $filters['store_id']);
        }

        if (isset($filters['month_from'])) {
            $query->where('monthly_sales_summaries.sale_month', '>=', $filters['month_from']);
        }

        if (isset($filters['month_to'])) {
            $query->where('monthly_sales_summaries.sale_month', '<=', $filters['month_to']);
        }

        return $query;
    }

    /**
     * Calcula o Z-score (inverso da distribuição normal padrão)
     * Equivalente ao NormSInv do Excel/VBA
     *
     * @param  float  $probability  Probabilidade (0.5 a 0.999...)
     * @return float Z-score
     */
    private function calculateZScore(float $probability): float
    {
        // Validação
        if ($probability <= 0 || $probability >= 1) {
            return 0.0;
        }

        // Aproximação usando método de Abramowitz e Stegun
        // Para valores comuns de nível de serviço (0.7, 0.8, 0.9, 0.95, etc)

        // Valores exatos para casos comuns
        $commonValues = [
            0.70 => 0.5244,
            0.75 => 0.6745,
            0.80 => 0.8416,
            0.85 => 1.0364,
            0.90 => 1.2816,
            0.95 => 1.6449,
            0.99 => 2.3263,
        ];

        if (isset($commonValues[$probability])) {
            return $commonValues[$probability];
        }

        // Aproximação usando método de Abramowitz e Stegun
        // Para outros valores, usa aproximação numérica
        $t = sqrt(-2.0 * log(1.0 - $probability));
        $c0 = 2.515517;
        $c1 = 0.802853;
        $c2 = 0.010328;
        $d1 = 1.432788;
        $d2 = 0.189269;
        $d3 = 0.001308;

        $z = $t - ($c0 + $c1 * $t + $c2 * $t * $t) / (1.0 + $d1 * $t + $d2 * $t * $t + $d3 * $t * $t * $t);

        return $z;
    }
}
