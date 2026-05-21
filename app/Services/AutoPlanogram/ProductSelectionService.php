<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\AutoPlanogram;

use App\Services\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use App\Services\AutoPlanogram\DTO\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AbcAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\TargetStockService;
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
    ) {}

    /**
     * Selecionar e rankear produtos para o planograma
     *
     * @return Collection<RankedProductDTO>
     */
    public function selectAndRankProducts(
        Planogram $planogram,
        AutoGenerateConfigDTO $config,
        bool $requireDimensions = true,
    ): Collection {

        // 1. Buscar produtos da categoria (e filhas)
        $products = $this->getProductsFromCategory(data_get($config, 'categoryId', $planogram->category_id));

        if ($products->isEmpty()) {
            return collect();
        }

        // 2. Buscar dados de vendas e análises ABC
        $salesData = $this->getSalesData($products, $planogram, $config);
        $abcAnalyses = $this->getAbcAnalyses($products, $config);

        // 3. Montar DTOs com ABC e dados de venda (score = prioridade ABC para ordenar o fetch)
        $rankedProducts = $products->map(function (Product $product) use ($salesData, $abcAnalyses) {
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
            );
        });

        // 4. Filtrar produtos sem vendas (se configurado)
        if (! $config->includeProductsWithoutSales) {
            $rankedProducts = $rankedProducts->filter(fn ($p) => $p->salesTotal > 0);
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
    protected function getProductsFromCategory(string $categoryId): Collection
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

        // Buscar produtos dessas categorias usando a conexão tenant
        $products = Product::on($this->plannerateTenantConnectionName())
            ->whereIn('category_id', $categoryIds)
            ->with(['category.parent.parent.parent.parent.parent.parent'])
            ->get();

        $total = $products->count();
        $draftCount = $products->filter(fn ($p) => $p->status === 'draft')->count();
        $products = $products->filter(fn ($p) => $p->status !== 'draft')->values();

        Log::info('🛒 Produtos encontrados', [
            'total_categoria' => $total,
            'excluidos_draft' => $draftCount,
            'candidatos_finais' => $products->count(),
            'tenant_connection' => $connection->getName(),
            'sample_products' => $products->take(3)->pluck('name', 'id')->toArray(),
        ]);

        return $products;
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
