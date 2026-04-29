<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de Seleção de Produtos
 *
 * Responsável por:
 * 1. Buscar produtos da categoria do planograma (hierarquia completa)
 * 2. Buscar dados de vendas (ano anterior para sazonalidade)
 * 3. Calcular pontuação baseado na estratégia escolhida
 * 4. Rankear produtos por importância
 */
class ProductSelectionService
{
    use UsesPlannerateTenantDatabase;

    /**
     * Selecionar e rankear produtos para o planograma
     *
     * @return Collection<RankedProductDTO>
     */
    public function selectAndRankProducts(
        Planogram $planogram,
        AutoGenerateConfigDTO $config
    ): Collection {

        // 1. Buscar produtos da categoria (e filhas)
        $products = $this->getProductsFromCategory(data_get($config, 'categoryId', $planogram->category_id));

        if ($products->isEmpty()) {
            return collect();
        }

        // 2. Buscar dados de vendas e análises
        $salesData = $this->getSalesData($products, $planogram, $config);
        $abcAnalyses = $this->getAbcAnalyses($products, $config);

        // Calcular valores máximos para normalização (proteção contra array vazio)
        $salesTotals = array_column($salesData, 'total');
        $marginTotals = array_column($salesData, 'margin');

        $maxSales = ! empty($salesTotals) ? max($salesTotals) : 1.0;
        $maxMargin = ! empty($marginTotals) ? max($marginTotals) : 1.0;

        Log::info('📊 Dados para normalização', [
            'total_products' => $products->count(),
            'products_with_sales' => count($salesData),
            'max_sales' => $maxSales,
            'max_margin' => $maxMargin,
        ]);

        // 3. Calcular pontuação para cada produto
        $rankedProducts = $products->map(function (Product $product) use ($salesData, $abcAnalyses, $config, $maxSales, $maxMargin) {
            $productId = $product->id;

            $salesTotal = $salesData[$productId]['total'] ?? 0;
            $margin = $salesData[$productId]['margin'] ?? 0;

            $analysisData = $abcAnalyses[$productId] ?? null;
            $abcClass = $analysisData['abc'] ?? null;
            $targetStock = $analysisData['target_stock'] ?? null;
            $safetyStock = $analysisData['safety_stock'] ?? null;

            // Calcular score baseado na estratégia (com normalização)
            $score = $this->calculateScore($salesTotal, $margin, $abcClass, $config->strategy, $maxSales, $maxMargin);

            return new RankedProductDTO(
                product: $product,
                abcClass: $abcClass,
                score: $score,
                salesTotal: $salesTotal,
                margin: $margin,
                subcategoryId: $product->category_id,
                targetStock: $targetStock,
                safetyStock: $safetyStock,
            );
        });

        // 4. Filtrar produtos sem vendas (se configurado)
        if (! $config->includeProductsWithoutSales) {
            $rankedProducts = $rankedProducts->filter(fn ($p) => $p->salesTotal > 0);
        }

        // 5. Ordenar por score (maior = mais importante)
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
            ->with(['category'])
            ->get();

        Log::info('🛒 Produtos encontrados', [
            'total' => $products->count(),
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
     * Buscar análises ABC existentes (se configurado)
     *
     * IMPORTANTE: BelongsToConnection já configurou a conexão para o tenant correto
     *
     * @return array ['product_id' => ['abc' => 'A'|'B'|'C', 'target_stock' => float, 'safety_stock' => float]]
     */
    protected function getAbcAnalyses(Collection $products, AutoGenerateConfigDTO $config): array
    {
        if (! $config->useExistingAnalysis) {
            return [];
        }

        $productIds = $products->pluck('id')->toArray();

        $analyses = $this->plannerateTenantTable('product_analyses')
            ->whereIn('product_id', $productIds)
            ->select(['product_id', 'abc_classification', 'target_stock', 'safety_stock'])
            ->get();

        $abcData = [];
        foreach ($analyses as $analysis) {
            $abcData[$analysis->product_id] = [
                'abc' => $analysis->abc_classification,
                'target_stock' => (float) ($analysis->target_stock ?? 0),
                'safety_stock' => (float) ($analysis->safety_stock ?? 0),
            ];
        }

        return $abcData;
    }

    /**
     * Calcular pontuação baseado na estratégia
     *
     * Estratégias:
     * - abc: 100% peso na classificação ABC
     * - sales: 100% peso nas vendas
     * - margin: 100% peso na margem
     * - mix: 40% ABC + 40% vendas + 20% margem
     *
     * IMPORTANTE: Todos os scores são normalizados para escala 0-100
     * para que tenham peso equivalente na estratégia "mix"
     */
    protected function calculateScore(
        float $salesTotal,
        float $margin,
        ?string $abcClass,
        string $strategy,
        float $maxSales = 1.0,
        float $maxMargin = 1.0
    ): float {
        // Normalizar ABC para pontuação (A=100, B=50, C=25, null=0)
        $abcScore = match ($abcClass) {
            'A' => 100,
            'B' => 50,
            'C' => 25,
            default => 0,
        };

        // Normalizar vendas e margem para escala 0-100
        // Evita divisão por zero
        $salesScore = $maxSales > 0 ? ($salesTotal / $maxSales) * 100 : 0;
        $marginScore = $maxMargin > 0 ? ($margin / $maxMargin) * 100 : 0;

        return match ($strategy) {
            'abc' => $abcScore,
            'sales' => $salesScore,
            'margin' => $marginScore,
            'mix' => ($abcScore * 0.4) + ($salesScore * 0.4) + ($marginScore * 0.2),
            default => $salesScore,
        };
    }
}
