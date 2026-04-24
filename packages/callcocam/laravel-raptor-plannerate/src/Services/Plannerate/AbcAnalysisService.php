<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Sale;
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
     * @param  array  $filters  Filtros adicionais (deve incluir client_id)
     */
    public function analyzeByCategory(
        Category $category,
        string $tableType = 'sales',
        array $filters = []
    ): Collection {
        if (! isset($filters['client_id']) || empty($filters['client_id'])) {
            Log::error('ABC Analysis - client_id é obrigatório para análise por categoria');
            throw new \InvalidArgumentException('client_id é obrigatório para análise ABC');
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

        Log::info('ABC Analysis - analyzeByCategory', [
            'client_id' => $filters['client_id'],
            'category_id' => $category->id,
            'category_ids' => $allCategoryIds,
            'product_ids_count' => count($productIds),
        ]);

        if (empty($productIds)) {
            Log::warning('ABC Analysis - Nenhum produto encontrado para as categorias', [
                'category_ids' => $allCategoryIds,
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
            'client_id' => $filters['client_id'],
            'codigos_erp_count' => count($codigosErp),
            'codigos_erp_sample' => array_slice($codigosErp, 0, 5),
        ]);

        if (empty($codigosErp)) {
            Log::warning('ABC Analysis - Nenhum codigo_erp encontrado na tabela products', [
                'client_id' => $filters['client_id'],
                'product_ids_count' => count($productIds),
            ]);

            return collect();
        }

        // Usa codigo_erp para filtrar vendas
        return $this->analyzeByCodigoErp($codigosErp, $productIds, $tableType, $filters);
    }

    /**
     * Executa análise ABC por lista de EANs
     *
     * @param  array  $eans  Lista de EANs
     * @param  string  $tableType  'sales' ou 'monthly_summaries'
     * @param  array  $filters  Filtros adicionais (deve incluir client_id)
     */
    public function analyzeByEans(
        array $eans,
        string $tableType = 'sales',
        array $filters = []
    ): Collection {
        if (empty($eans)) {
            Log::warning('ABC Analysis - Lista de EANs vazia');

            return collect();
        }

        if (! isset($filters['client_id']) || empty($filters['client_id'])) {
            Log::error('ABC Analysis - client_id é obrigatório para análise por EANs');
            throw new \InvalidArgumentException('client_id é obrigatório para análise ABC');
        }

        // Busca produtos pelos EANs
        $products = Product::whereIn('ean', $eans)->get();

        Log::info('ABC Analysis - analyzeByEans', [
            'client_id' => $filters['client_id'],
            'eans_count' => count($eans),
            'products_found' => $products->count(),
        ]);

        if ($products->isEmpty()) {
            Log::warning('ABC Analysis - Nenhum produto encontrado para os EANs', [
                'eans' => $eans,
            ]);

            return collect();
        }

        // Busca codigo_erp diretamente da tabela products (campo direto, não precisa de pivot)
        // Usa o Model Product que já tem a conexão tenant configurada via UsesTenantDatabase
        $productIds = $products->pluck('id')->toArray();
        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->toArray();

        Log::info('ABC Analysis - codigos_erp encontrados (EANs)', [
            'client_id' => $filters['client_id'],
            'codigos_erp_count' => count($codigosErp),
            'codigos_erp_sample' => array_slice($codigosErp, 0, 5),
        ]);

        if (empty($codigosErp)) {
            Log::warning('ABC Analysis - Nenhum codigo_erp encontrado na tabela products (EANs)', [
                'client_id' => $filters['client_id'],
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
     * @param  array  $filters  Filtros adicionais (deve incluir client_id)
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

        if (! isset($filters['client_id']) || empty($filters['client_id'])) {
            Log::error('ABC Analysis - client_id é obrigatório para análise por product_ids');
            throw new \InvalidArgumentException('client_id é obrigatório para análise ABC');
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
        $salesData = $this->getSalesDataByCodigoErp($codigosErp, $productIds, $tableType, $filters);

        Log::info('ABC Analysis - analyzeByCodigoErp', [
            'client_id' => $filters['client_id'] ?? 'N/A',
            'table_type' => $tableType,
            'codigos_erp_count' => count($codigosErp),
            'sales_data_count' => $salesData->count(),
        ]);

        if ($salesData->isEmpty()) {
            Log::warning('ABC Analysis - Nenhuma venda encontrada', [
                'client_id' => $filters['client_id'] ?? 'N/A',
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
            'monthly_summaries' => $this->getMonthlySummariesQueryByCodigoErp($codigosErp, $filters),
            default => $this->getSalesQueryByCodigoErp($codigosErp, $filters),
        };

        return $query->get();
    }

    /**
     * Query para tabela sales usando codigo_erp
     *
     * No contexto tenant, as tabelas products e sales já estão no banco do client,
     * então não precisamos filtrar por client_id (a coluna nem existe no banco tenant)
     */
    private function getSalesQueryByCodigoErp(array $codigosErp, array $filters): Builder
    {
        // No banco tenant, products e sales já pertencem ao client
        // Usa codigo_erp para fazer o join entre products e sales
        $query = Sale::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'sales.codigo_erp')
            ->select([
                'products.id as product_id',
                'products.category_id',
                DB::raw('SUM(sales.total_sale_quantity) as qtde'),
                DB::raw('SUM(sales.total_sale_value) as valor'),
                DB::raw('SUM(sales.margem_contribuicao) as margem'),
            ])
            ->whereIn('sales.codigo_erp', $codigosErp)
            ->groupBy('products.id', 'products.category_id');

        Log::info('ABC Analysis - getSalesQueryByCodigoErp SQL', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        // Aplica filtros adicionais

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
     * Query para tabela monthly_sales_summaries usando codigo_erp
     *
     * No contexto tenant, as tabelas products e monthly_sales_summaries já estão no banco do client,
     * então não precisamos filtrar por client_id (a coluna nem existe no banco tenant)
     */
    private function getMonthlySummariesQueryByCodigoErp(array $codigosErp, array $filters): Builder
    {
        // No banco tenant, products e monthly_sales_summaries já pertencem ao client
        // Usa codigo_erp para fazer o join entre products e monthly_sales_summaries
        $query = MonthlySalesSummary::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'monthly_sales_summaries.codigo_erp')
            ->select([
                'products.id as product_id',
                'products.category_id',
                DB::raw('SUM(monthly_sales_summaries.total_sale_quantity) as qtde'),
                DB::raw('SUM(monthly_sales_summaries.total_sale_value) as valor'),
                DB::raw('SUM(monthly_sales_summaries.margem_contribuicao) as margem'),
            ])
            ->whereIn('monthly_sales_summaries.codigo_erp', $codigosErp)
            ->groupBy('products.id', 'products.category_id');

        Log::info('ABC Analysis - getMonthlySummariesQueryByCodigoErp SQL', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        // Aplica filtros adicionais

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
     * Calcula média ponderada para cada produto
     */
    private function calculateWeightedAverage(Collection $salesData): Collection
    {
        return $salesData->map(function ($item) {
            $qtde = (float) ($item->qtde ?? 0);
            $valor = (float) ($item->valor ?? 0);
            $margem = (float) ($item->margem ?? 0);

            $somaPesos = 0;
            $mediaPonderada = 0;

            if ($qtde != 0) {
                $somaPesos += $this->pesoQtde;
                $mediaPonderada += ($qtde * $this->pesoQtde);
            }

            if ($valor != 0) {
                $somaPesos += $this->pesoValor;
                $mediaPonderada += ($valor * $this->pesoValor);
            }

            if ($margem != 0) {
                $somaPesos += $this->pesoMargem;
                $mediaPonderada += ($margem * $this->pesoMargem);
            }

            $mediaPonderadaFinal = $somaPesos != 0 ? ($mediaPonderada / $somaPesos) : 0;

            return [
                'product_id' => $item->product_id,
                'category_id' => $item->category_id,
                'qtde' => $qtde,
                'valor' => $valor,
                'margem' => $margem,
                'media_ponderada' => round($mediaPonderadaFinal, 6),
            ];
        });
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
            return collect();
        }

        $acumulado = 0;
        $ranking = 1;
        $menorPercentualB = 1.0;

        // Primeiro, encontra o menor percentual da classe B
        foreach ($products as $product) {
            $percentualIndividual = $product['media_ponderada'] / $totalPonderado;
            $acumulado += $percentualIndividual;

            // Classifica temporariamente para encontrar menor B
            $classificacao = $this->classify($acumulado);
            if ($classificacao === 'B' && $percentualIndividual < $menorPercentualB) {
                $menorPercentualB = $percentualIndividual;
            }
        }

        // Agora processa todos os produtos com classificação final
        $acumulado = 0;
        $ranking = 1;
        $result = collect();

        foreach ($products as $product) {
            $percentualIndividual = $product['media_ponderada'] / $totalPonderado;
            $acumulado += $percentualIndividual;

            $classificacao = $this->classify($acumulado);

            // Determina se deve retirar do mix
            $retirarDoMix = $this->shouldRemoveFromMix($classificacao, $percentualIndividual, $menorPercentualB);

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

            $ranking++;
        }

        return $result;
    }

    /**
     * Classifica produto em A, B ou C baseado no percentual acumulado
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
     * Determina se produto deve ser retirado do mix
     *
     * Regra: Classe C com percentual individual menor que metade do menor percentual B
     */
    private function shouldRemoveFromMix(string $classificacao, float $percentualIndividual, float $menorPercentualB): bool
    {
        if ($classificacao === 'C' && $menorPercentualB > 0) {
            return $percentualIndividual < ($menorPercentualB / 2);
        }

        return false;
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
     * Calcula status do produto (Ativo/Inativo) - versão otimizada
     *
     * Usa dados pré-carregados em vez de fazer query individual
     *
     * @param  Product|null  $product  Modelo do produto
     * @param  object|null  $lastSaleData  Dados da última venda (pré-carregados)
     */
    private function calculateProductStatusOptimized(?Product $product, ?object $lastSaleData): array
    {
        if (! $product) {
            return [
                'status' => 'Inativo',
                'motivo' => 'Produto não encontrado',
            ];
        }

        $dataHoje = now();

        // Usa dados pré-carregados da última venda
        $ultimaVendaDate = $lastSaleData?->last_sale_date ?? null;
        $diasSemVenda = $ultimaVendaDate ? $dataHoje->diffInDays($ultimaVendaDate) : null;

        // TODO: Implementar busca de última compra quando houver tabela
        $diasSemCompra = null;
        $estoqueAtual = 0;

        // Lógica simplificada de status baseada apenas em vendas
        if ($diasSemVenda !== null && $diasSemVenda <= 120) {
            return [
                'status' => 'Ativo',
                'motivo' => 'Venda recente',
            ];
        }

        if ($estoqueAtual > 0) {
            return [
                'status' => 'Ativo',
                'motivo' => 'Com estoque',
            ];
        }

        return [
            'status' => 'Inativo',
            'motivo' => $diasSemVenda === null ? 'Sem vendas' : 'Sem venda há mais de 120 dias',
        ];
    }
}
