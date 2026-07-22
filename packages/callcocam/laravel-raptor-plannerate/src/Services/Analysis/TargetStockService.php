<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Analysis;

use Callcocam\LaravelRaptorPlannerate\Models\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\Sales\ProductSalesAggregateQuery;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesStatistics;
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
     * Parâmetros de nível de serviço por classe ABC.
     * Inicializados a partir de config('plannerate.auto_planogram.target_stock');
     * os setters continuam funcionando como override pontual.
     */
    private array $niveisServico = [
        'A' => 0.7,
        'B' => 0.8,
        'C' => 0.9,
    ];

    /**
     * Parâmetros de cobertura em dias por classe ABC.
     */
    private array $coberturaDias = [
        'A' => 2,
        'B' => 5,
        'C' => 7,
    ];

    public function __construct()
    {
        // Defaults configuráveis por instalação/tenant — fallback preserva os
        // valores da planilha VBA original (docs/ESTOQUE-ALVO.md)
        $configured = config('plannerate.auto_planogram.target_stock', []);

        $this->niveisServico = array_merge($this->niveisServico, $configured['service_levels'] ?? []);
        $this->coberturaDias = array_merge($this->coberturaDias, $configured['coverage_days'] ?? []);
    }

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

        // Se não foi passado estoque atual, busca da pivot `product_store` recortada
        // pela loja dos filtros. Sem loja, `forStore()` consolida todas — o número
        // do tenant, não o de uma loja arbitrária.
        //
        // O nulo é filtrado aqui e não com `whereNotNull` porque a coluna do SELECT
        // é um subselect da pivot: um whereNotNull olharia para `products`.
        if (empty($currentStock)) {
            $storeId = isset($filters['store_id']) ? (string) $filters['store_id'] : null;

            $currentStock = Product::query()
                ->forStore($storeId)
                ->whereIn('ean', $eans)
                ->pluck('current_stock', 'ean')
                ->filter(fn (mixed $stock): bool => $stock !== null)
                ->toArray();

            Log::info('TargetStock - current_stock carregado de product_store', [
                'store_id' => $storeId,
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
        $breakdownSample = [];
        $semEstatistica = 0;
        $puladosNivelServico = 0;

        foreach ($abcResults as $abcResult) {
            $ean = $abcResult['ean'];
            $productId = $abcResult['product_id'];
            $productName = $abcResult['product_name'] ?? '';
            $classificacao = $abcResult['classificacao'] ?? 'C';

            // Obtém estatísticas do banco (já calculadas)
            $stats = $statsByEan->get($ean);
            $media = $stats ? (float) $stats->media : 0.0;
            $desvioPadrao = $stats ? (float) $stats->desvio_padrao : 0.0;
            $variabilidade = SalesStatistics::variability($media, $desvioPadrao);

            if (! $stats) {
                $semEstatistica++;
            }

            // Obtém parâmetros baseados na classificação ABC
            $nivelServico = $this->niveisServico[$classificacao] ?? 0.9;
            $coberturaDias = $this->coberturaDias[$classificacao] ?? 7;

            // Valida nível de serviço
            if ($nivelServico < 0.5 || $nivelServico >= 1) {
                $puladosNivelServico++;

                continue;
            }

            // Calcula Z-score (inverso da distribuição normal padrão)
            $zScore = SalesStatistics::zScore($nivelServico);

            // Calcula estoques (fórmulas centralizadas em SalesStatistics). O alvo
            // arredonda a soma bruta uma única vez — não a soma dos dois abaixo,
            // que já vêm arredondados para exibição (ver targetStock()).
            $estoqueSeguranca = SalesStatistics::safetyStock($zScore, $desvioPadrao);
            $estoqueMinimo = SalesStatistics::minimumStock($media, $coberturaDias);
            $estoqueAlvo = SalesStatistics::targetStock($media, $coberturaDias, $zScore, $desvioPadrao);

            // Estoque atual (padrão: 0 se não fornecido)
            $estoqueAtual = $currentStock[$ean] ?? 0;
            $permiteFrentes = $estoqueAtual >= $estoqueAlvo ? 'Sim' : 'Não';

            // Guarda amostra das primeiras linhas com a quebra completa do cálculo
            if (count($breakdownSample) < 10) {
                $breakdownSample[] = [
                    'ean' => $ean,
                    'classificacao' => $classificacao,
                    'media' => round($media, 4),
                    'desvio_padrao' => round($desvioPadrao, 4),
                    'nivel_servico' => $nivelServico,
                    'z_score' => round($zScore, 4),
                    'cobertura_dias' => $coberturaDias,
                    'estoque_minimo_calc' => "media({$media}) * cobertura({$coberturaDias}) = ".round($media * $coberturaDias, 4),
                    'estoque_seguranca_calc' => "z({$zScore}) * desvio({$desvioPadrao}) = ".round($zScore * $desvioPadrao, 4),
                    'estoque_minimo' => $estoqueMinimo,
                    'estoque_seguranca' => $estoqueSeguranca,
                    'estoque_alvo' => $estoqueAlvo,
                ];
            }

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

        // Diagnóstico: comprova as entradas e a fórmula do estoque alvo.
        // NOTA: a fórmula usa estoque_seguranca = Z * desvio (sem multiplicar por sqrt(cobertura_dias)),
        // ou seja, assume variabilidade de 1 período. Confirmar se bate com a planilha VBA de referência.
        Log::info('TargetStock - calculateByAbcResults diagnóstico', [
            'table_type' => $tableType,
            'abc_results_count' => count($abcResults),
            'resultados_gerados' => $results->count(),
            'sem_estatistica_venda' => $semEstatistica,
            'pulados_nivel_servico_invalido' => $puladosNivelServico,
            'niveis_servico' => $this->niveisServico,
            'cobertura_dias' => $this->coberturaDias,
            'amostra_breakdown' => $breakdownSample,
        ]);

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
            ->whereNull('sales.deleted_at')
            ->whereNull('products.deleted_at')
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
     * Query agregada para tabela monthly_sales_summaries - calcula AVG e STDDEV_POP no banco.
     *
     * O período sai de monthPeriod(), que aceita tanto month_from/month_to
     * (auto-planograma, já em data) quanto start_month/end_month (a UI, em Y-m).
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
            ->whereNull('monthly_sales_summaries.deleted_at')
            ->whereNull('products.deleted_at')
            ->groupBy('products.ean');

        if (isset($filters['store_id'])) {
            $query->where('monthly_sales_summaries.store_id', $filters['store_id']);
        }

        [$from, $to] = ProductSalesAggregateQuery::monthPeriod($filters);

        if ($from !== null) {
            $query->where('monthly_sales_summaries.sale_month', '>=', $from);
        }

        if ($to !== null) {
            $query->where('monthly_sales_summaries.sale_month', '<=', $to);
        }

        return $query;
    }
}
