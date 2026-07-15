<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Analysis;

use Callcocam\LaravelRaptorPlannerate\Models\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\Sales\ProductSalesAggregateQuery;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesStatistics;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service para análise ABC de produtos
 *
 * Baseado na lógica do arquivo docs/abc.md
 * Calcula classificação ABC usando média ponderada de quantidade, valor e margem
 */
class AbcAnalysisService
{
    /**
     * Janela de recência do VBA (docs/ABC.md): venda ou compra dentro destes dias conta
     * como movimento recente e mantém o produto Ativo.
     */
    public const RECENCIA_DIAS = 120;

    /**
     * Pesos padrão para cálculo da média ponderada
     */
    private float $pesoQtde = 0.3;

    private float $pesoValor = 0.3;

    private float $pesoMargem = 0.4;

    /**
     * Cortes padrão para classificação ABC
     */
    private float $corteA = 0.80; // 80%

    private float $corteB = 0.85; // 85%

    /**
     * Configura os pesos para o cálculo da média ponderada
     */
    public function setWeights(float $pesoQtde, float $pesoValor, float $pesoMargem): self
    {
        $this->pesoQtde = $pesoQtde;
        $this->pesoValor = $pesoValor;
        $this->pesoMargem = $pesoMargem;

        return $this;
    }

    /**
     * Configura os cortes para classificação ABC
     */
    public function setCuts(float $corteA, float $corteB): self
    {
        $this->corteA = $corteA;
        $this->corteB = $corteB;

        return $this;
    }

    /**
     * Executa análise ABC por categoria (incluindo hierarquia)
     *
     * @param  Category  $category  Categoria e seus pais serão incluídos
     * @param  string  $tableType  'sales' ou 'monthly_summaries'
     * @param  array  $filters  Filtros adicionais (deve incluir tenant_id, pode incluir gondola_id)
     */
    public function analyzeByCategory(
        Category $category,
        string $tableType = 'sales',
        array $filters = []
    ): Collection {
        if (! isset($filters['tenant_id']) || empty($filters['tenant_id'])) {
            Log::error('ABC Analysis - tenant_id é obrigatório para análise por categoria');
            throw new \InvalidArgumentException('tenant_id é obrigatório para análise ABC');
        }

        // Obtém toda a hierarquia da categoria
        $fullHierarchy = $category->getFullHierarchy();

        // Se a categoria selecionada está além do 5º nível, pega apenas os 5 primeiros níveis
        // Exemplo: SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO > MÉDIA
        // Retorna apenas: SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO
        $limitedHierarchy = $fullHierarchy->take(5);

        // Pega a categoria do 5º nível (última da hierarquia limitada) para buscar produtos
        $categoryToUse = $limitedHierarchy->last() ?? $category;

        // Obtém todos os IDs das categorias dos 5 primeiros níveis
        $hierarchyIds = $limitedHierarchy->pluck('id')->toArray();

        // Obtém todos os IDs das categorias filhas da categoria do 5º nível
        $allCategoryIds = array_unique(array_merge(
            $hierarchyIds,
            $categoryToUse->getAllDescendantIds()
        ));

        // Busca produtos das categorias
        $productIds = Product::whereIn('category_id', $allCategoryIds)
            ->pluck('id')
            ->toArray();

        // Se uma gôndola foi especificada, filtra apenas os produtos alocados nela
        if (isset($filters['gondola_id']) && ! empty($filters['gondola_id'])) {
            $gondolaProductIds = $this->getProductIdsByGondola($filters['gondola_id']);
            $productIds = array_intersect($productIds, $gondolaProductIds);

            Log::info('ABC Analysis - Filtrando por gôndola', [
                'tenant_id' => $filters['tenant_id'],
                'gondola_id' => $filters['gondola_id'],
                'products_before_filter' => count(Product::whereIn('category_id', $allCategoryIds)->pluck('id')->toArray()),
                'products_after_filter' => count($productIds),
            ]);
        }

        Log::info('ABC Analysis - analyzeByCategory', [
            'tenant_id' => $filters['tenant_id'],
            'category_id' => $category->id,
            'category_ids' => $allCategoryIds,
            'product_ids_count' => count($productIds),
            'gondola_id' => $filters['gondola_id'] ?? null,
        ]);

        if (empty($productIds)) {
            Log::warning('ABC Analysis - Nenhum produto encontrado para as categorias', [
                'category_ids' => $allCategoryIds,
                'gondola_id' => $filters['gondola_id'] ?? null,
            ]);

            return collect();
        }

        // Busca codigo_erp diretamente da tabela products (campo direto, não precisa de pivot)
        // Usa o Model Product que já tem a conexão tenant configurada via UsesTenantDatabase
        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->toArray();

        Log::info('ABC Analysis - codigos_erp encontrados', [
            'tenant_id' => $filters['tenant_id'],
            'codigos_erp_count' => count($codigosErp),
            'codigos_erp_sample' => array_slice($codigosErp, 0, 5),
        ]);

        if (empty($codigosErp)) {
            Log::warning('ABC Analysis - Nenhum codigo_erp encontrado na tabela products', [
                'tenant_id' => $filters['tenant_id'],
                'product_ids_count' => count($productIds),
            ]);

            return collect();
        }

        // Usa codigo_erp para filtrar vendas
        return $this->analyzeByCodigoErp($codigosErp, $productIds, $tableType, $filters);
    }

    /**
     * Executa análise ABC por lista de IDs de produtos
     *
     * @param  array  $productIds  IDs dos produtos
     * @param  string  $tableType  'sales' ou 'monthly_summaries'
     * @param  array  $filters  Filtros adicionais (deve incluir tenant_id)
     */
    public function analyzeByProductIds(
        array $productIds,
        string $tableType = 'sales',
        array $filters = []
    ): Collection {
        if (empty($productIds)) {
            Log::warning('ABC Analysis - Lista de product_ids vazia');

            return collect();
        }

        if (! isset($filters['tenant_id']) || empty($filters['tenant_id'])) {
            Log::error('ABC Analysis - tenant_id é obrigatório para análise por product_ids');
            throw new \InvalidArgumentException('tenant_id é obrigatório para análise ABC');
        }

        // Busca codigo_erp diretamente da tabela products (campo direto, não precisa de pivot)
        // Usa o Model Product que já tem a conexão tenant configurada via UsesTenantDatabase
        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->toArray();

        if (empty($codigosErp)) {
            return collect();
        }

        // Usa codigo_erp para filtrar vendas
        return $this->analyzeByCodigoErp($codigosErp, $productIds, $tableType, $filters);
    }

    /**
     * Executa análise ABC para todos os produtos do tenant (sem filtro de categoria ou EANs)
     *
     * @param  string  $tableType  'sales' ou 'monthly_summaries'
     * @param  array  $filters  Filtros adicionais (datas, store_id)
     */
    public function analyzeAll(string $tableType = 'monthly_summaries', array $filters = []): Collection
    {
        $products = Product::query()
            ->whereNotNull('codigo_erp')
            ->select('id', 'codigo_erp')
            ->get();

        if ($products->isEmpty()) {
            Log::warning('ABC Analysis - Nenhum produto com codigo_erp encontrado no tenant');

            return collect();
        }

        $codigosErp = $products->pluck('codigo_erp')->unique()->toArray();
        $productIds = $products->pluck('id')->toArray();

        Log::info('ABC Analysis - analyzeAll', [
            'table_type' => $tableType,
            'products_count' => count($productIds),
        ]);

        return $this->analyzeByCodigoErp($codigosErp, $productIds, $tableType, $filters);
    }

    /**
     * Executa análise ABC por lista de codigo_erp
     *
     * @param  array  $codigosErp  Lista de códigos ERP
     * @param  array  $productIds  IDs dos produtos (para buscar categoria)
     * @param  string  $tableType  'sales' ou 'monthly_summaries'
     * @param  array  $filters  Filtros adicionais
     */
    public function analyzeByCodigoErp(
        array $codigosErp,
        array $productIds,
        string $tableType = 'sales',
        array $filters = []
    ): Collection {
        if (empty($codigosErp)) {
            Log::warning('ABC Analysis - Lista de codigos_erp vazia');

            return collect();
        }

        // Busca dados agregados de vendas por codigo_erp
        // toBase() converte para Support\Collection para evitar que operações da
        // EloquentCollection (groupBy, etc.) chamem getKey() em itens que não são models
        $salesData = $this->getSalesDataByCodigoErp($codigosErp, $productIds, $tableType, $filters)->toBase();

        // Inclui produtos da gôndola sem vendas com valores zerados
        $productsWithSalesIds = $salesData->pluck('product_id')->toArray();
        $productsWithoutSalesIds = array_diff($productIds, $productsWithSalesIds);

        if (! empty($productsWithoutSalesIds)) {
            $zeroRecords = Product::query()
                ->whereIn('id', $productsWithoutSalesIds)
                ->select('id', 'category_id')
                ->get()
                ->toBase()
                ->map(fn ($p) => (object) [
                    'product_id' => $p->id,
                    'category_id' => $p->category_id,
                    'qtde' => 0,
                    'valor' => 0,
                    'margem' => 0,
                ]);

            $salesData = $salesData->merge($zeroRecords);
        }

        Log::info('ABC Analysis - analyzeByCodigoErp', [
            'tenant_id' => $filters['tenant_id'] ?? 'N/A',
            'table_type' => $tableType,
            'codigos_erp_count' => count($codigosErp),
            'sales_data_count' => $salesData->count(),
            'sem_venda_count' => count($productsWithoutSalesIds),
        ]);

        if ($salesData->isEmpty()) {
            Log::warning('ABC Analysis - Nenhum produto encontrado', [
                'tenant_id' => $filters['tenant_id'] ?? 'N/A',
                'table_type' => $tableType,
                'codigos_erp_count' => count($codigosErp),
            ]);

            return collect();
        }

        // Calcula média ponderada para cada produto
        $productsWithWeight = $this->calculateWeightedAverage($salesData);

        // Busca produtos com suas categorias para determinar o ID do 5º nível
        $productIds = $productsWithWeight->pluck('product_id')->unique()->toArray();
        $products = Product::with(['category'])->whereIn('id', $productIds)->get()->keyBy('id');

        // Busca última venda de todos os produtos de uma vez (otimização N+1)
        $lastSalesByProduct = $this->getLastSalesForProducts($productIds);

        // Mapeia cada produto para o ID do 5º nível da hierarquia
        $productsWithLevel5Category = $productsWithWeight->map(function ($item) use ($products) {
            $product = $products->get($item['product_id']);
            $level5CategoryId = $this->getCategoryIdAtLevel5($product);

            // Se não encontrar categoria no 5º nível, usa o category_id original como fallback
            $item['level5_category_id'] = $level5CategoryId ?? $item['category_id'];

            return $item;
        });

        // Agrupa pelo ID do 5º nível da hierarquia
        $groupedByLevel5Category = $productsWithLevel5Category->groupBy('level5_category_id');

        $result = collect();

        // Processa cada grupo do 5º nível separadamente
        foreach ($groupedByLevel5Category as $level5CategoryId => $categoryProducts) {
            // Ordena por média ponderada (descendente)
            $sorted = $categoryProducts->sortByDesc('media_ponderada');

            // Calcula porcentagens e classificação ABC (passa cache de últimas vendas)
            $processed = $this->calculatePercentagesAndClassification($sorted, $level5CategoryId, $products, $lastSalesByProduct);

            $result = $result->merge($processed);
        }

        Log::info('ABC Analysis - Final results', [
            'total_results' => $result->count(),
            'products_with_sales' => $productsWithWeight->count(),
            'unique_products' => $products->count(),
        ]);

        $retirarDoMixCount = $result->where('retirar_do_mix', true)->count();
        $manter = $result->where('retirar_do_mix', false)->count();
        Log::info('ABC Analysis - Products to remove from mix', [
            'retirar_do_mix_count' => $retirarDoMixCount,
            'manter_count' => $manter,
            'total_count' => $result->count(),
        ]);

        return $result;
    }

    /**
     * Busca a última venda de cada produto de uma vez (evita N+1 queries)
     *
     * @param  array  $productIds  IDs dos produtos
     * @return Collection Coleção indexada por product_id com a data da última venda
     */
    private function getLastSalesForProducts(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        // Busca a última venda de cada produto usando uma única query agregada
        return Sale::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'sales.codigo_erp')
            ->whereIn('products.id', $productIds)
            ->whereNull('sales.deleted_at')
            ->whereNull('products.deleted_at')
            ->select([
                'products.id as product_id',
                DB::raw('MAX(sales.sale_date) as last_sale_date'),
            ])
            ->groupBy('products.id')
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Busca dados agregados de vendas por codigo_erp
     *
     * Usa codigo_erp diretamente da tabela products para filtrar vendas
     */
    private function getSalesDataByCodigoErp(array $codigosErp, array $productIds, string $tableType, array $filters): Collection
    {
        $query = match ($tableType) {
            'monthly_summaries' => $this->getMonthlySummariesQueryByCodigoErp($codigosErp, $productIds, $filters),
            default => $this->getSalesQueryByCodigoErp($codigosErp, $productIds, $filters),
        };

        $salesData = $query->get();

        Log::info('ABC Analysis - getSalesDataByCodigoErp details', [
            'table_type' => $tableType,
            'codigos_erp_count' => count($codigosErp),
            'product_ids_count' => count($productIds),
            'sales_records_count' => $salesData->count(),
            'unique_products_in_sales' => $salesData->pluck('product_id')->unique()->count(),
        ]);

        return $salesData;
    }

    /**
     * Query para tabela sales usando codigo_erp
     *
     * No contexto tenant, as tabelas products e sales já estão no banco do tenant,
     * então não precisamos filtrar por tenant_id (a conexão já isola o tenant)
     */
    private function getSalesQueryByCodigoErp(array $codigosErp, array $productIds, array $filters): Builder
    {
        // Plumbing dual-source (join products on codigo_erp + filtros + agrupamento)
        // centralizado em ProductSalesAggregateQuery; aqui ficam só os agregados do
        // ABC (qtde/valor/margem) e o período por data de venda (date_from/date_to).
        $agg = ProductSalesAggregateQuery::for('sales');

        $query = $agg->groupedByProduct($codigosErp, $productIds, $filters)
            ->addSelect([
                $agg->sum('total_sale_quantity', 'qtde'),
                $agg->sum('total_sale_value', 'valor'),
                $agg->sum('margem_contribuicao', 'margem'),
            ]);

        $agg->applyPeriod($query, $filters['date_from'] ?? null, $filters['date_to'] ?? null);

        return $query;
    }

    /**
     * Query para tabela monthly_sales_summaries usando codigo_erp.
     *
     * Mesmo plumbing da query de sales; o período sai de monthPeriod(), que aceita
     * tanto month_from/month_to (auto-planograma, já em data) quanto start_month/
     * end_month (a UI, em Y-m).
     */
    private function getMonthlySummariesQueryByCodigoErp(array $codigosErp, array $productIds, array $filters): Builder
    {
        $agg = ProductSalesAggregateQuery::for('monthly_summaries');

        $query = $agg->groupedByProduct($codigosErp, $productIds, $filters)
            ->addSelect([
                $agg->sum('total_sale_quantity', 'qtde'),
                $agg->sum('total_sale_value', 'valor'),
                $agg->sum('margem_contribuicao', 'margem'),
            ]);

        [$from, $to] = ProductSalesAggregateQuery::monthPeriod($filters);
        $agg->applyPeriod($query, $from, $to);

        return $query;
    }

    /**
     * Calcula média ponderada para cada produto
     */
    private function calculateWeightedAverage(Collection $salesData): Collection
    {
        // Acumula totais por métrica para diagnosticar dominância de escala
        $totalQtde = 0.0;
        $totalValor = 0.0;
        $totalMargem = 0.0;
        $breakdownSample = [];

        $resultado = $salesData->map(function ($item) use (&$totalQtde, &$totalValor, &$totalMargem, &$breakdownSample) {
            $qtde = (float) ($item->qtde ?? 0);
            $valor = (float) ($item->valor ?? 0);
            $margem = (float) ($item->margem ?? 0);

            $somaPesos = 0;

            // Contribuição individual de cada métrica (apenas para a amostra de diagnóstico)
            $contribQtde = 0.0;
            $contribValor = 0.0;
            $contribMargem = 0.0;

            if ($qtde != 0) {
                $somaPesos += $this->pesoQtde;
                $contribQtde = $qtde * $this->pesoQtde;
            }

            if ($valor != 0) {
                $somaPesos += $this->pesoValor;
                $contribValor = $valor * $this->pesoValor;
            }

            if ($margem != 0) {
                $somaPesos += $this->pesoMargem;
                $contribMargem = $margem * $this->pesoMargem;
            }

            // Média ponderada centralizada em SalesStatistics (fonte única da fórmula)
            $mediaPonderadaFinal = SalesStatistics::weightedAverage(
                $qtde,
                $valor,
                $margem,
                $this->pesoQtde,
                $this->pesoValor,
                $this->pesoMargem,
            );

            $totalQtde += $qtde;
            $totalValor += $valor;
            $totalMargem += $margem;

            // Guarda uma amostra das primeiras linhas com a quebra de contribuições
            if (count($breakdownSample) < 10) {
                $breakdownSample[] = [
                    'product_id' => $item->product_id,
                    'qtde' => $qtde,
                    'valor' => $valor,
                    'margem' => $margem,
                    'contrib_qtde' => round($contribQtde, 4),
                    'contrib_valor' => round($contribValor, 4),
                    'contrib_margem' => round($contribMargem, 4),
                    'soma_pesos' => $somaPesos,
                    'media_ponderada' => round($mediaPonderadaFinal, 6),
                ];
            }

            return [
                'product_id' => $item->product_id,
                'category_id' => $item->category_id,
                'qtde' => $qtde,
                'valor' => $valor,
                'margem' => $margem,
                'media_ponderada' => round($mediaPonderadaFinal, 6),
            ];
        });

        // Diagnóstico: revela se uma métrica domina a média ponderada por diferença de escala.
        // Se 'valor' tem ordem de grandeza muito maior que 'qtde'/'margem', os pesos perdem efeito.
        Log::info('ABC Analysis - calculateWeightedAverage diagnóstico', [
            'pesos' => [
                'qtde' => $this->pesoQtde,
                'valor' => $this->pesoValor,
                'margem' => $this->pesoMargem,
            ],
            'totais_brutos' => [
                'qtde' => round($totalQtde, 2),
                'valor' => round($totalValor, 2),
                'margem' => round($totalMargem, 2),
            ],
            'escala_relativa' => [
                'valor_vs_qtde' => $totalQtde > 0 ? round($totalValor / $totalQtde, 2) : null,
                'margem_vs_qtde' => $totalQtde > 0 ? round($totalMargem / $totalQtde, 2) : null,
            ],
            'amostra_breakdown' => $breakdownSample,
        ]);

        return $resultado;
    }

    /**
     * Calcula porcentagens, classificação ABC e ranking
     *
     * @param  Collection  $products  Produtos com média ponderada calculada
     * @param  string  $categoryId  ID da categoria do 5º nível para agrupamento
     * @param  Collection|null  $productsCache  Cache de produtos já carregados (opcional)
     * @param  Collection|null  $lastSalesCache  Cache de últimas vendas por produto (opcional)
     */
    private function calculatePercentagesAndClassification(
        Collection $products,
        string $categoryId,
        ?Collection $productsCache = null,
        ?Collection $lastSalesCache = null
    ): Collection {
        // Calcula total ponderado da categoria
        $totalPonderado = $products->sum('media_ponderada');

        if ($totalPonderado == 0) {
            // Todos sem vendas: lista como classe C sem retirar do mix
            $ranking = 1;
            $noSalesResult = collect();

            foreach ($products as $product) {
                $productModel = $productsCache?->get($product['product_id'])
                    ?? Product::with(['category'])->find($product['product_id']);

                $fullPath = $productModel?->category?->full_path ?? 'Sem categoria';
                $categoryName = $this->limitCategoryPathToFiveLevels($fullPath);
                $level5CategoryId = $this->getCategoryIdAtLevel5($productModel) ?? $categoryId;
                $lastSaleData = $lastSalesCache?->get($product['product_id']);
                $status = $this->calculateProductStatusOptimized($productModel, $lastSaleData);

                $noSalesResult->push([
                    'product_id' => $product['product_id'],
                    'product_name' => $productModel?->name ?? 'Produto não encontrado',
                    'ean' => $productModel?->ean,
                    'image_url' => $productModel?->image_url,
                    'category_id' => $level5CategoryId,
                    'category_name' => $categoryName,
                    'qtde' => 0,
                    'valor' => 0,
                    'margem' => 0,
                    'media_ponderada' => 0,
                    'percentual_individual' => 0,
                    'percentual_acumulado' => 0,
                    'classificacao' => 'C',
                    'ranking' => $ranking,
                    'class_rank' => 'C'.$ranking,
                    'retirar_do_mix' => false,
                    'status' => $status,
                ]);

                $ranking++;
            }

            return $noSalesResult;
        }

        // Classificação pura (sem banco) — ver classifyRankedProducts() para a regra
        $classified = $this->classifyRankedProducts($products, $totalPonderado);

        $result = collect();

        foreach ($classified as $index => $item) {
            $product = $products->values()->get($index);
            $percentualIndividual = $item['percentual_individual'];
            $acumulado = $item['percentual_acumulado'];
            $classificacao = $item['classificacao'];
            $ranking = $item['ranking'];
            $retirarDoMix = $item['retirar_do_mix'];

            // Busca informações do produto (usa cache se disponível)
            $productModel = $productsCache?->get($product['product_id'])
                ?? Product::with(['category'])->find($product['product_id']);

            // Obtém o full_path e limita aos 5 primeiros níveis
            $fullPath = $productModel?->category?->full_path ?? 'Sem categoria';
            $categoryName = $this->limitCategoryPathToFiveLevels($fullPath);

            // Obtém o ID do 5º nível para usar como category_id no resultado
            $level5CategoryId = $this->getCategoryIdAtLevel5($productModel) ?? $categoryId;

            // Calcula status usando cache de últimas vendas (evita N+1)
            $lastSaleData = $lastSalesCache?->get($product['product_id']);
            $status = $this->calculateProductStatusOptimized($productModel, $lastSaleData);

            $result->push([
                'product_id' => $product['product_id'],
                'product_name' => $productModel?->name ?? 'Produto não encontrado',
                'ean' => $productModel?->ean,
                'image_url' => $productModel?->image_url,
                'category_id' => $level5CategoryId,
                'category_name' => $categoryName,
                'qtde' => $product['qtde'],
                'valor' => $product['valor'],
                'margem' => $product['margem'],
                'media_ponderada' => $product['media_ponderada'],
                'percentual_individual' => round($percentualIndividual * 100, 2),
                'percentual_acumulado' => round($acumulado * 100, 2),
                'classificacao' => $classificacao,
                'ranking' => $ranking,
                'class_rank' => $classificacao.$ranking,
                'retirar_do_mix' => $retirarDoMix,
                'status' => $status,
            ]);
        }

        // Referências de diagnóstico derivadas da classificação pura
        $menorPercentualB = $classified->where('classificacao', 'B')->min('percentual_individual') ?? 1.0;
        $acumuladoFinal = $classified->last()['percentual_acumulado'] ?? 0.0;

        // Diagnóstico: confere se os cortes A/B/C e o acumulado fecham 100%
        Log::info('ABC Analysis - classificação por categoria', [
            'category_id' => $categoryId,
            'total_ponderado' => round($totalPonderado, 6),
            'menor_percentual_b' => round($menorPercentualB * 100, 4),
            'corte_a' => $this->corteA,
            'corte_b' => $this->corteB,
            'acumulado_final_pct' => round($acumuladoFinal * 100, 4),
            'produtos' => $result->count(),
            'distribuicao' => [
                'A' => $result->where('classificacao', 'A')->count(),
                'B' => $result->where('classificacao', 'B')->count(),
                'C' => $result->where('classificacao', 'C')->count(),
            ],
            'retirar_do_mix' => $result->where('retirar_do_mix', true)->count(),
        ]);

        return $result;
    }

    /**
     * Classifica produtos já ordenados (desc por media_ponderada) em A/B/C — lógica pura, sem banco.
     *
     * A classificação usa o percentual acumulado ANTES de somar o item (melhor prática
     * de mercado): o primeiro do ranking é sempre A — inclusive em categorias com um
     * único produto — e um item que cruza um corte ainda pertence à classe anterior.
     * O percentual_acumulado retornado continua sendo o "após" (usado para exibição).
     *
     * @param  Collection  $products  Lista ordenada com 'product_id' e 'media_ponderada'
     * @param  float  $totalPonderado  Soma de media_ponderada da categoria (deve ser > 0)
     * @return Collection<int, array{product_id: mixed, percentual_individual: float, percentual_acumulado: float, classificacao: string, ranking: int, retirar_do_mix: bool}>
     */
    public function classifyRankedProducts(Collection $products, float $totalPonderado): Collection
    {
        $products = $products->values();

        // Categoria inteira sem venda: não há evidência para promover ninguém —
        // todos C, sem retirar do mix (espelha o ramo "sem vendas" do chamador).
        if ($totalPonderado <= 0) {
            return $products->map(fn ($product, $index) => [
                'product_id' => $product['product_id'],
                'percentual_individual' => 0.0,
                'percentual_acumulado' => 0.0,
                'classificacao' => 'C',
                'ranking' => $index + 1,
                'retirar_do_mix' => false,
            ]);
        }

        // Primeiro passe: encontra o menor percentual individual da classe B, que é a
        // referência da regra de retirar_do_mix.
        //
        // O default 1.0 NÃO é um valor neutro — é a regra do VBA (docs/ABC.md): quando a
        // categoria não tem nenhum B, menorPercentualB fica em 1 e o corte vira "< 50% de
        // participação", então praticamente todo C é marcado para sair. É o que a planilha
        // do cliente faz no grupo AÇÚCAR CRISTAL (A,A,C,C, sem B): os dois C saem com "Sim".
        $acumulado = 0.0;
        $menorPercentualB = 1.0;
        $ranking = 1;

        foreach ($products as $product) {
            $percentualIndividual = $product['media_ponderada'] / $totalPonderado;
            $acumulado += $percentualIndividual;

            if ($this->classifyAtRank($acumulado, $ranking) === 'B' && $percentualIndividual < $menorPercentualB) {
                $menorPercentualB = $percentualIndividual;
            }

            $ranking++;
        }

        // Segundo passe: classificação final + retirar_do_mix
        $acumulado = 0.0;
        $ranking = 1;
        $result = collect();

        foreach ($products as $product) {
            $percentualIndividual = $product['media_ponderada'] / $totalPonderado;
            $acumulado += $percentualIndividual;

            $classificacao = $this->classifyAtRank($acumulado, $ranking);

            $result->push([
                'product_id' => $product['product_id'],
                'percentual_individual' => $percentualIndividual,
                'percentual_acumulado' => $acumulado,
                'classificacao' => $classificacao,
                'ranking' => $ranking,
                'retirar_do_mix' => $this->shouldRemoveFromMix($classificacao, $percentualIndividual, $menorPercentualB),
            ]);

            $ranking++;
        }

        return $result;
    }

    /**
     * Classificação final: a regra do acumulado, com a exceção do líder do grupo.
     *
     * O 1º do ranking é SEMPRE A. Pela regra pura do acumulado, um produto que sozinho
     * responde por mais que o corte A (ou o único produto de uma categoria, com 100%)
     * cairia em C — o dono da categoria classificado como o pior dela. A exceção fica
     * confinada aqui, no caso degenerado, em vez de distorcer a regra para todo mundo
     * (que foi o erro do commit 01da121b: promover TODO item que cruza um corte).
     *
     * Não afeta a paridade com a planilha de referência: nos dois grupos de açúcar o
     * líder já é A pelo acumulado (43,03% e 43,75%).
     */
    private function classifyAtRank(float $acumulado, int $ranking): string
    {
        if ($ranking === 1) {
            return 'A';
        }

        return $this->classify($acumulado);
    }

    /**
     * Classifica produto em A, B ou C pelo percentual acumulado APÓS somar o item.
     *
     * Os cortes são INCLUSIVOS (<=): o item cujo acumulado fecha exatamente sobre o
     * corte ainda pertence à classe. É a regra da planilha de referência do cliente
     * (ver AbcSpreadsheetParityTest) e a que o sistema usava até 12/06/2026.
     *
     * Classificar pelo acumulado ANTES do item — como passou a fazer o commit
     * 01da121b — promove indevidamente quem cruza o corte e dá um A a mais em CADA
     * grupo, além de exibir na tela um acumulado diferente do que decidiu a classe.
     */
    private function classify(float $acumulado): string
    {
        if ($acumulado <= $this->corteA) {
            return 'A';
        } elseif ($acumulado <= $this->corteB) {
            return 'B';
        }

        return 'C';
    }

    /**
     * Determina se o produto deve ser retirado do mix — regra do VBA (docs/ABC.md):
     *
     *   If N = "C" And L < menorPercentualB / 2 Then "Sim" Else "Não"
     *
     * Classe C cuja participação individual é menor que METADE da participação do menor
     * classe B da categoria.
     *
     * Categoria SEM classe B não é exceção: o VBA deixa menorPercentualB no default 1,
     * então o corte vira "< 50% de participação" e praticamente todo C é marcado. Isso é
     * proposital — uma categoria que nem chega a ter um B tem uma cauda fraca de verdade.
     *
     * Tínhamos um guard `if (! $hasClassB) return false` que NÃO existe no VBA e zerava o
     * retirar_do_mix nessas categorias: o grupo AÇÚCAR CRISTAL da planilha do cliente
     * (A,A,C,C, sem B) marca os dois C com "Sim", e nós devolvíamos "Não" nos dois.
     */
    private function shouldRemoveFromMix(string $classificacao, float $percentualIndividual, float $menorPercentualB): bool
    {
        if ($classificacao !== 'C') {
            return false;
        }

        return $percentualIndividual < ($menorPercentualB / 2);
    }

    /**
     * Limita o caminho da categoria aos 5 primeiros níveis
     *
     * Exemplo: "SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO > MÉDIA"
     * Retorna: "SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO"
     *
     * @param  string  $fullPath  Caminho completo da categoria
     * @return string Caminho limitado aos 5 primeiros níveis
     */
    private function limitCategoryPathToFiveLevels(string $fullPath): string
    {
        if (empty($fullPath) || $fullPath === 'Sem categoria') {
            return $fullPath;
        }

        // Divide o caminho por " > " e pega apenas os 5 primeiros níveis
        $levels = explode(' > ', $fullPath);
        $limitedLevels = array_slice($levels, 0, 5);

        return implode(' > ', $limitedLevels);
    }

    /**
     * Obtém o ID da categoria no 5º nível da hierarquia (ou o mais alto disponível)
     *
     * Exemplo: Se a hierarquia tem 7 níveis, retorna o ID do 5º nível
     * Se a hierarquia tem 3 níveis, retorna o ID do 3º nível (o mais alto disponível)
     *
     * @param  Product|null  $product  Produto para buscar a categoria
     * @return string|null ID da categoria no 5º nível ou null se não houver categoria
     */
    private function getCategoryIdAtLevel5(?Product $product): ?string
    {
        if (! $product || ! $product->category) {
            return null;
        }

        $category = $product->category;
        $hierarchy = $category->getFullHierarchy();

        // Se a hierarquia tem 5 ou mais níveis, pega o 5º (índice 4)
        // Se tem menos de 5 níveis, pega o último (o mais alto disponível)
        if ($hierarchy->count() >= 5) {
            return $hierarchy->get(4)?->id ?? $hierarchy->last()?->id;
        }

        // Retorna o ID do último nível (o mais alto disponível)
        return $hierarchy->last()?->id;
    }

    /**
     * Status e justificativa do produto — regra do VBA do cliente (docs/ABC.md, bloco
     * ">>> Status do Produto"). São as colunas Q (Status) e S (Justificativa) da planilha.
     *
     * Cruza três sinais, com a janela de RECENCIA_DIAS (120 dias) do VBA:
     *
     *   última venda recente + última compra recente  → Ativo   / Venda e compra recentes
     *   última venda recente + sem compra recente     → Ativo   / Venda recente, sem compra
     *   última compra recente + sem venda recente     → Ativo   / Compra recente, sem venda
     *   nenhum dos dois                               → Sem venda e sem compra
     *                                                   Inativo se estoque = 0, Ativo se > 0
     *
     * A implementação anterior era uma "lógica simplificada": só olhava a última venda e
     * tratava o estoque como 0 fixo, então nunca chegava aos casos de compra. As datas
     * saem de `products.last_purchase_date` e o estoque de `products.current_stock`.
     *
     * ATENÇÃO: `last_purchase_date` ainda NÃO é populada pela importação (0 de 8.592
     * produtos no tenant de dev). Enquanto isso, os dois casos que dependem de compra não
     * disparam e todo produto vendido recentemente cai em "Venda recente, sem compra" —
     * que é exatamente o que o VBA faz quando a data de compra vem vazia. A lógica está
     * correta; falta o dado (a tabela `purchases` do legado tem `last_purchase_date` e
     * `current_stock` por produto).
     *
     * @param  Product|null  $product  Modelo do produto (traz last_purchase_date e current_stock)
     * @param  object|null  $lastSaleData  Última venda pré-carregada (evita N+1)
     * @return array{status: string, motivo: string}
     */
    private function calculateProductStatusOptimized(?Product $product, ?object $lastSaleData): array
    {
        if (! $product) {
            return [
                'status' => trans('plannerate.analysis.product_status.inactive'),
                'motivo' => trans('plannerate.analysis.product_status.not_found'),
            ];
        }

        return $this->productStatus(
            $lastSaleData?->last_sale_date ?? null,
            $product->last_purchase_date,
            (float) ($product->current_stock ?? 0),
        );
    }

    /**
     * A decisão de status em si — pura, sem banco, testável isolada.
     *
     * @param  mixed  $ultimaVenda  Data da última venda (null = nunca vendeu)
     * @param  mixed  $ultimaCompra  Data da última compra (null = sem registro de compra)
     * @param  float  $estoqueAtual  Estoque atual; só decide no caso sem movimento nenhum
     * @return array{status: string, motivo: string}
     */
    public function productStatus(mixed $ultimaVenda, mixed $ultimaCompra, float $estoqueAtual): array
    {
        $ativo = trans('plannerate.analysis.product_status.active');
        $vendaRecente = $this->isRecent($ultimaVenda);
        $compraRecente = $this->isRecent($ultimaCompra);

        if ($vendaRecente && $compraRecente) {
            return [
                'status' => $ativo,
                'motivo' => trans('plannerate.analysis.product_status.recent_sale_and_purchase'),
            ];
        }

        if ($vendaRecente) {
            return [
                'status' => $ativo,
                'motivo' => trans('plannerate.analysis.product_status.recent_sale_no_purchase'),
            ];
        }

        if ($compraRecente) {
            return [
                'status' => $ativo,
                'motivo' => trans('plannerate.analysis.product_status.recent_purchase_no_sale'),
            ];
        }

        // Sem movimento nos dois lados: só o estoque decide se o produto ainda está vivo.
        return [
            'status' => $estoqueAtual > 0
                ? $ativo
                : trans('plannerate.analysis.product_status.inactive'),
            'motivo' => trans('plannerate.analysis.product_status.no_sale_no_purchase'),
        ];
    }

    /**
     * A data caiu dentro da janela de recência do VBA (120 dias)?
     *
     * Data ausente é "não recente" — é o `ultimaVenda <> 0` / `ultimaCompra <> 0` do VBA,
     * que trata célula vazia como ausência de movimento, não como movimento antigo.
     */
    private function isRecent(mixed $data): bool
    {
        if (blank($data)) {
            return false;
        }

        try {
            $dias = Carbon::parse($data)->startOfDay()->diffInDays(now()->startOfDay());
        } catch (\Throwable) {
            return false;
        }

        return $dias <= self::RECENCIA_DIAS;
    }

    /**
     * Obtém os IDs de todos os produtos alocados em uma gôndola específica
     *
     * Navega pela hierarquia: Gondola → Sections → Shelves → Segments → Layers → Products
     *
     * @param  string  $gondolaId  ID da gôndola
     * @return array Array de IDs de produtos
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
}
