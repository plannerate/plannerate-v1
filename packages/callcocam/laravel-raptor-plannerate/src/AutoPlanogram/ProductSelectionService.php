<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\PaperAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\TargetStockService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de Seleção de Produtos
 *
 * Responsável por:
 * 1. Buscar produtos da categoria do planograma (hierarquia completa)
 * 2. Buscar dados de vendas e análise ABC
 * 3. Retornar pool ordenado por ABC (A→B→C) para que A's entrem primeiro nos slots
 *
 * IMPORTANTE: o score aqui serve apenas para ordenar o fetch; o placement real
 * é determinado pelo CompositeScorer em AutoPlanogramService::generate().
 * Não altere a estratégia aqui esperando mudar o layout — use os pesos do CompositeScorer.
 */
class ProductSelectionService
{
    use UsesPlannerateTenantDatabase;

    public function __construct(
        private readonly AbcAnalysisService $abcAnalysis,
        private readonly TargetStockService $targetStock,
        private readonly PaperAnalysisService $paperAnalysis,
    ) {}

    /**
     * Selecionar e rankear produtos para o planograma.
     *
     * @param  list<string>|null  $scopeCategoryIds  Modo template: categorias cobertas pelos
     *                                               slots do template. Restringe o pool a elas
     *                                               (e suas descendentes) em vez do departamento
     *                                               inteiro do planograma.
     * @return Collection<RankedProductDTO>
     */
    public function selectAndRankProducts(
        Planogram $planogram,
        AutoGenerateConfigDTO $config,
        bool $requireDimensions = true,
        ?array $scopeCategoryIds = null,
    ): Collection {

        // Resolve store_id para filtro de sortimento: loja direta ou via cluster.
        // null = planograma sem loja definida → sem filtro (compatibilidade com legado).
        $storeId = $this->resolveStoreIdForAssortment($planogram);

        // 1. Buscar produtos da categoria (e filhas).
        // Modo template: pool restrito às categorias dos slots ($scopeCategoryIds).
        // Caso contrário: categoria do formulário ou, como fallback, a categoria-base
        // do planograma (departamento inteiro, expandido recursivamente).
        $products = ($scopeCategoryIds !== null && $scopeCategoryIds !== [])
            ? $this->getProductsFromCategories($scopeCategoryIds, $storeId)
            : $this->getProductsFromCategory(data_get($config, 'categoryId', $planogram->category_id), $storeId);

        if ($products->isEmpty()) {
            return collect();
        }

        // 2. Buscar dados de vendas e análises ABC
        $salesData = $this->getSalesData($products, $planogram, $config);
        $abcAnalyses = $this->getAbcAnalyses($products, $config);

        // 2b. Calcular papéis estratégicos (Análise de Papel — requer dois períodos, fallback silencioso)
        $paperRoleMap = $this->getPaperRoles($products, $planogram, $config);

        // 3. Montar DTOs com ABC, Análise de Papel e dados de venda (score = prioridade ABC para ordenar o fetch)
        $rankedProducts = $products->map(function (Product $product) use ($salesData, $abcAnalyses, $paperRoleMap) {
            $productId = $product->id;

            $analysisData = $abcAnalyses[$productId] ?? null;
            $abcClass = $analysisData['abc'] ?? null;

            // A=3, B=2, C=1, sem ABC=0 — apenas para pré-ordenar o pool
            $score = match ($abcClass) {
                'A' => 3.0,
                'B' => 2.0,
                'C' => 1.0,
                default => 0.0,
            };

            return new RankedProductDTO(
                product: $product,
                abcClass: $abcClass,
                score: $score,
                salesTotal: $salesData[$productId]['total'] ?? 0,
                margin: $salesData[$productId]['margin'] ?? 0,
                subcategoryId: $product->category_id,
                targetStock: $analysisData['target_stock'] ?? null,
                safetyStock: $analysisData['safety_stock'] ?? null,
                paperRole: $paperRoleMap[$productId] ?? null,
            );
        });

        // 4. Filtrar produtos sem vendas (se configurado)
        if (! $config->includeProductsWithoutSales) {
            $rankedProducts = $rankedProducts->filter(fn ($p) => $p->salesTotal > 0);
        }

        // 4b. Excluir curva C do pool (quando habilitado).
        // Produtos sem ABC (abcClass = null) não são afetados — só os C explícitos saem.
        if ($config->excludeClassC) {
            $before = $rankedProducts->count();
            $rankedProducts = $rankedProducts->filter(fn ($p) => $p->abcClass !== 'C');
            Log::info('ProductSelectionService: curva C excluída do pool', [
                'antes' => $before,
                'depois' => $rankedProducts->count(),
                'removidos' => $before - $rankedProducts->count(),
            ]);
        }

        // 5. Filtrar produtos sem dimensões (width ou height)
        // No modo template ($requireDimensions = false) o engine é quem rejeita e loga MissingDimensions
        if ($requireDimensions) {
            $rankedProducts = $rankedProducts->filter(function ($p) {
                $width = (float) ($p->product->width ?? 0);
                $height = (float) ($p->product->height ?? 0);

                return $width > 0 && $height > 0;
            });
        }

        // 6. Ordenar por score (maior = mais importante)
        return $rankedProducts->sortByDesc('score')->values();
    }

    /**
     * Buscar produtos da categoria do planograma (incluindo TODAS categorias filhas recursivamente)
     *
     * Usa CTE recursiva do PostgreSQL para eficiência
     * IMPORTANTE: BelongsToConnection já configurou a conexão para o tenant correto
     */
    protected function getProductsFromCategory(string $categoryId, ?string $storeId = null): Collection
    {
        $connection = $this->plannerateTenantDatabase();

        // Primeiro verificar se a categoria existe
        $categoryExists = $connection->table('categories')->where('id', $categoryId)->exists();

        Log::info('🔎 Verificando categoria', [
            'category_id' => $categoryId,
            'exists' => $categoryExists,
            'connection' => $connection->getName(),
            'database' => $connection->getDatabaseName(),
        ]);

        if (! $categoryExists) {
            Log::warning('⚠️  Categoria não encontrada no banco', [
                'category_id' => $categoryId,
                'database' => $connection->getDatabaseName(),
            ]);
            throw new \RuntimeException(
                'Categoria do planograma não encontrada no banco do tenant. '
                .'Verifique se o planograma está com uma categoria válida e se as categorias foram importadas/sincronizadas no tenant.'
            );
        }

        // Query recursiva SQL para buscar TODAS as categorias descendentes
        $categoryIds = $connection->select('
            WITH RECURSIVE category_tree AS (
                -- Categoria inicial
                SELECT id, category_id, name
                FROM categories
                WHERE id = ?

                UNION ALL

                -- Categorias filhas recursivamente
                SELECT c.id, c.category_id, c.name
                FROM categories c
                INNER JOIN category_tree ct ON c.category_id = ct.id
            )
            SELECT id FROM category_tree
        ', [$categoryId]);

        // Extrair IDs
        $categoryIds = array_map(fn ($cat) => $cat->id, $categoryIds);

        Log::info('📂 Categorias encontradas (recursivo)', [
            'total' => count($categoryIds),
            'category_id' => $categoryId,
            'category_ids' => $categoryIds,
            'tenant_connection' => $connection->getName(),
            'database' => $connection->getDatabaseName(),
        ]);

        return $this->fetchProductsByCategoryIds($categoryIds, $storeId);
    }

    /**
     * Buscar produtos de múltiplas categorias-raiz (e suas descendentes), usado no modo
     * template onde os slots definem o escopo. Categorias inexistentes são ignoradas
     * silenciosamente — diferente de getProductsFromCategory(), que lança exceção.
     *
     * @param  list<string>  $rootCategoryIds
     * @return Collection<int, Product>
     */
    protected function getProductsFromCategories(array $rootCategoryIds, ?string $storeId = null): Collection
    {
        $connection = $this->plannerateTenantDatabase();
        $roots = array_values(array_unique(array_filter($rootCategoryIds)));

        if ($roots === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($roots), '?'));

        // CTE recursiva com múltiplas raízes: expande cada categoria do template até as folhas.
        $rows = $connection->select("
            WITH RECURSIVE category_tree AS (
                SELECT id, category_id, name
                FROM categories
                WHERE id IN ({$placeholders})

                UNION ALL

                SELECT c.id, c.category_id, c.name
                FROM categories c
                INNER JOIN category_tree ct ON c.category_id = ct.id
            )
            SELECT DISTINCT id FROM category_tree
        ", $roots);

        $categoryIds = array_map(fn ($cat) => $cat->id, $rows);

        Log::info('📂 Categorias do template (recursivo)', [
            'roots' => $roots,
            'total' => count($categoryIds),
            'tenant_connection' => $connection->getName(),
            'database' => $connection->getDatabaseName(),
        ]);

        return $this->fetchProductsByCategoryIds($categoryIds, $storeId);
    }

    /**
     * Busca os produtos não-draft das categorias informadas (conexão tenant) e loga o resultado.
     *
     * Quando $storeId é informado, aplica filtro de sortimento via product_store:
     * apenas produtos autorizados para aquela loja entram no pool.
     *
     * @param  list<string>  $categoryIds
     * @param  string|null  $storeId  ID da loja para filtro de sortimento; null = sem filtro
     * @return Collection<int, Product>
     */
    protected function fetchProductsByCategoryIds(array $categoryIds, ?string $storeId = null): Collection
    {
        if ($categoryIds === []) {
            return collect();
        }

        $query = Product::on($this->plannerateTenantConnectionName())
            ->whereIn('category_id', $categoryIds)
            ->with(['category.parent.parent.parent.parent.parent.parent']);

        if ($storeId !== null) {
            $query->whereExists(function ($sub) use ($storeId): void {
                $sub->select(DB::raw(1))
                    ->from('product_store')
                    ->whereColumn('product_store.product_id', 'products.id')
                    ->where('product_store.store_id', $storeId);
            });
        }

        $products = $query->get();

        $total = $products->count();
        $draftCount = $products->filter(fn ($p) => $p->status === 'draft')->count();
        $products = $products->filter(fn ($p) => $p->status !== 'draft')->values();

        Log::info('🛒 Produtos encontrados', [
            'total_categoria' => $total,
            'excluidos_draft' => $draftCount,
            'candidatos_finais' => $products->count(),
            'filtro_sortimento' => $storeId !== null ? 'ativo' : 'inativo',
            'store_id' => $storeId,
            'tenant_connection' => $this->plannerateTenantConnectionName(),
            'sample_products' => $products->take(3)->pluck('name', 'id')->toArray(),
        ]);

        return $products;
    }

    /**
     * Calcula o papel estratégico de cada produto via Análise de Papel.
     *
     * Usa o período do config como período atual e calcula o anterior automaticamente
     * (mesmo intervalo deslocado). Retorna mapa vazio silenciosamente se sem dados.
     *
     * @return array<string, string> [product_id => 'leader'|'anchor'|'rising'|'lagging']
     */
    protected function getPaperRoles(
        Collection $products,
        Planogram $planogram,
        AutoGenerateConfigDTO $config,
    ): array {
        $productIds = $products->pluck('id')->toArray();

        if (empty($productIds)) {
            return [];
        }

        $tenantId = app('currentTenant')?->getKey() ?? '';

        $currentFilters = ['tenant_id' => $tenantId];

        if ($config->tableType === 'monthly_summaries') {
            $startDate = $config->startDate
                ? Carbon::parse($config->startDate)->startOfMonth()->format('Y-m')
                : Carbon::parse($planogram->start_date)->subYear()->startOfMonth()->format('Y-m');
            $endDate = $config->endDate
                ? Carbon::parse($config->endDate)->endOfMonth()->format('Y-m')
                : Carbon::parse($planogram->end_date)->subYear()->endOfMonth()->format('Y-m');

            $currentFilters['start_month'] = $startDate;
            $currentFilters['end_month'] = $endDate;
        } else {
            $currentFilters['date_from'] = $config->startDate
                ?? Carbon::parse($planogram->start_date)->subYear()->toDateString();
            $currentFilters['date_to'] = $config->endDate
                ?? Carbon::parse($planogram->end_date)->subYear()->toDateString();
        }

        try {
            $results = $this->paperAnalysis->analyzeByProductIds(
                productIds: $productIds,
                tableType: $config->tableType ?: 'sales',
                currentFilters: $currentFilters,
            );

            if ($results->isEmpty()) {
                return [];
            }

            $map = [];
            foreach ($results as $item) {
                $map[$item['product_id']] = $item['role'];
            }

            Log::info('ProductSelectionService: Análise de Papel calculada', [
                'total' => count($map),
                'leader' => count(array_filter($map, fn ($r) => $r === 'leader')),
                'anchor' => count(array_filter($map, fn ($r) => $r === 'anchor')),
                'rising' => count(array_filter($map, fn ($r) => $r === 'rising')),
                'lagging' => count(array_filter($map, fn ($r) => $r === 'lagging')),
            ]);

            return $map;
        } catch (\Throwable $e) {
            Log::warning('ProductSelectionService: Análise de Papel falhou (fallback silencioso)', [
                'erro' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Resolve o store_id para filtro de sortimento a partir do planograma.
     *
     * - planogram.store_id preenchido → usa diretamente
     * - planogram.cluster_id preenchido → busca store_id do cluster (herda sortimento da loja)
     * - nenhum dos dois → retorna null (sem filtro, compatibilidade com planogramas legados)
     */
    private function resolveStoreIdForAssortment(Planogram $planogram): ?string
    {
        if ($planogram->store_id !== null) {
            return (string) $planogram->store_id;
        }

        if ($planogram->cluster_id !== null) {
            $storeId = $this->plannerateTenantDatabase()
                ->table('clusters')
                ->where('id', $planogram->cluster_id)
                ->whereNull('deleted_at')
                ->value('store_id');

            return $storeId !== null ? (string) $storeId : null;
        }

        return null;
    }

    /**
     * Buscar dados de vendas do ano anterior (sazonalidade)
     *
     * IMPORTANTE: BelongsToConnection já configurou a conexão para o tenant correto
     *
     * @return array ['product_id' => ['total' => float, 'margin' => float]]
     */
    protected function getSalesData(
        Collection $products,
        Planogram $planogram,
        AutoGenerateConfigDTO $config
    ): array {
        $productIds = $products->pluck('id')->toArray();

        // Prioriza período enviado no request; fallback mantém comportamento anterior.
        $startDate = $config->startDate
            ? Carbon::parse($config->startDate)
            : Carbon::parse($planogram->start_date)->subYear();
        $endDate = $config->endDate
            ? Carbon::parse($config->endDate)
            : Carbon::parse($planogram->end_date)->subYear();

        $tableType = $config->tableType ?: 'sales';
        $connection = $this->plannerateTenantDatabase();

        Log::info('📅 Filtro de vendas para ranking', [
            'table_type' => $tableType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'products_count' => count($productIds),
        ]);

        // Usa join por codigo_erp para compatibilidade com importações em que sales não possui product_id.
        if ($tableType === 'monthly_summaries') {
            // sale_month no tenant é date/timestamp; usar datas completas evita erro de cast (ex.: "2025-11").
            $monthFrom = $startDate->copy()->startOfMonth()->toDateString();
            $monthTo = $endDate->copy()->endOfMonth()->toDateString();

            $query = $connection->table('monthly_sales_summaries')
                ->join('products', 'products.codigo_erp', '=', 'monthly_sales_summaries.codigo_erp')
                ->whereIn('products.id', $productIds)
                ->whereBetween('monthly_sales_summaries.sale_month', [$monthFrom, $monthTo])
                ->select([
                    'products.id as product_id',
                    DB::raw('SUM(monthly_sales_summaries.total_sale_quantity) as total_quantity'),
                    DB::raw('SUM(monthly_sales_summaries.total_sale_value) as total_sales'),
                    DB::raw('SUM(monthly_sales_summaries.margem_contribuicao) as total_margin'),
                ])
                ->groupBy('products.id');
        } else {
            $query = $connection->table('sales')
                ->join('products', 'products.codigo_erp', '=', 'sales.codigo_erp')
                ->whereIn('products.id', $productIds)
                ->whereBetween('sales.sale_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->select([
                    'products.id as product_id',
                    DB::raw('SUM(sales.total_sale_quantity) as total_quantity'),
                    DB::raw('SUM(sales.total_sale_value) as total_sales'),
                    DB::raw('SUM(sales.margem_contribuicao) as total_margin'),
                ])
                ->groupBy('products.id');
        }

        $results = $query->get();

        $salesData = [];
        foreach ($results as $row) {
            $salesData[$row->product_id] = [
                'total' => (float) $row->total_sales,
                'margin' => (float) $row->total_margin,
            ];
        }

        Log::info('📈 Vendas agregadas para ranking', [
            'table_type' => $tableType,
            'rows' => count($salesData),
        ]);

        return $salesData;
    }

    /**
     * Buscar análises ABC — tenta cache em product_analyses, senão computa on-the-fly.
     *
     * @return array<string, array{abc: string, target_stock: float, safety_stock: float}>
     */
    protected function getAbcAnalyses(Collection $products, AutoGenerateConfigDTO $config): array
    {
        $productIds = $products->pluck('id')->toArray();

        if ($config->useExistingAnalysis) {
            $cached = $this->plannerateTenantTable('product_analyses')
                ->whereIn('product_id', $productIds)
                ->select(['product_id', 'abc_classification', 'target_stock', 'safety_stock'])
                ->get();

            if ($cached->isNotEmpty()) {
                $result = [];
                foreach ($cached as $row) {
                    $result[$row->product_id] = [
                        'abc' => $row->abc_classification,
                        'target_stock' => (float) ($row->target_stock ?? 0),
                        'safety_stock' => (float) ($row->safety_stock ?? 0),
                    ];
                }

                return $result;
            }
        }

        return $this->computeAbcOnTheFly($productIds, $config);
    }

    /**
     * Computa ABC + Estoque Alvo on-the-fly usando os services do pacote.
     *
     * @param  array<string>  $productIds
     * @return array<string, array{abc: string, target_stock: float, safety_stock: float}>
     */
    private function computeAbcOnTheFly(array $productIds, AutoGenerateConfigDTO $config): array
    {
        if (empty($productIds)) {
            return [];
        }

        $tenantId = app('currentTenant')?->getKey() ?? '';
        $filters = ['tenant_id' => $tenantId];

        if ($config->tableType === 'monthly_summaries') {
            if ($config->startDate) {
                $filters['month_from'] = Carbon::parse($config->startDate)->startOfMonth()->toDateString();
            }
            if ($config->endDate) {
                $filters['month_to'] = Carbon::parse($config->endDate)->endOfMonth()->toDateString();
            }
        } else {
            if ($config->startDate) {
                $filters['date_from'] = $config->startDate;
            }
            if ($config->endDate) {
                $filters['date_to'] = $config->endDate;
            }
        }

        // Aplica cortes ABC configuráveis antes de executar a análise
        $this->abcAnalysis->setCuts($config->abcCutoffA, $config->abcCutoffB);

        $abcResults = $this->abcAnalysis->analyzeByProductIds($productIds, $config->tableType, $filters);

        if ($abcResults->isEmpty()) {
            Log::info('ProductSelectionService: ABC on-the-fly sem resultados (sem vendas no período)');

            return [];
        }

        $targetResults = $this->targetStock->calculateByAbcResults(
            abcResults: $abcResults->toArray(),
            tableType: $config->tableType,
            filters: $filters,
        );

        $targetByProductId = $targetResults->keyBy('product_id');

        Log::info('ProductSelectionService: ABC on-the-fly concluído', [
            'products_com_abc' => $abcResults->count(),
            'A' => $abcResults->where('classificacao', 'A')->count(),
            'B' => $abcResults->where('classificacao', 'B')->count(),
            'C' => $abcResults->where('classificacao', 'C')->count(),
        ]);

        $result = [];
        foreach ($abcResults as $item) {
            $pid = $item['product_id'];
            $target = $targetByProductId->get($pid);
            $result[$pid] = [
                'abc' => $item['classificacao'],
                'target_stock' => (float) ($target['estoque_alvo'] ?? 0),
                'safety_stock' => (float) ($target['estoque_seguranca'] ?? 0),
            ];
        }

        return $result;
    }
}
