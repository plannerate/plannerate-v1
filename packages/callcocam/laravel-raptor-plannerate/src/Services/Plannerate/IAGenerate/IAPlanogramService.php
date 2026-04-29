<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\IAGenerate;

use Callcocam\LaravelRaptorPlannerate\Concerns\BelongsToConnection;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate\IAGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate\IAGenerateResultDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate\PlanogramContextDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Client;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\ProductSelectionService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prism\Prism\Facades\Prism;

/**
 * Serviço Principal de Geração de Planogramas com IA
 * Orquestra todo o processo usando Prism PHP para comunicação com LLM
 */
class IAPlanogramService
{
    use BelongsToConnection, UsesPlannerateTenantDatabase;

    public function __construct(
        protected ProductSelectionService $productSelectionService,
        protected IAPromptBuilderService $promptBuilderService,
        protected IAResponseParserService $responseParserService,
    ) {}

    /**
     * Gerar planograma usando IA
     */
    public function generate(string $gondolaId, IAGenerateConfigDTO $config): IAGenerateResultDTO
    {
        $startTime = microtime(true);

        try {
            Log::info('🤖 Iniciando geração de planograma com IA', [
                'gondola_id' => $gondolaId,
                'config' => $config->toArray(),
            ]);

            // 1. Setup conexão tenant (multi-tenancy)
            $this->setupTenantConnection();

            // 2. Carregar gôndola e dados relacionados
            $gondola = $this->loadGondola($gondolaId);

            // 3. Selecionar produtos candidatos
            $products = $this->selectProducts($config, $gondola);

            if (empty($products)) {
                throw new \RuntimeException('Nenhum produto encontrado para a categoria selecionada');
            }

            Log::info('📦 Produtos selecionados', [
                'total' => count($products),
                'abc_distribution' => array_count_values(array_column($products, 'abc_class')),
            ]);

            // 4. Construir contexto para IA
            $context = $this->buildContext($gondola, $products, $config);

            // 5. Gerar prompt estruturado
            $prompt = $this->promptBuilderService->buildPrompt($context, $config);

            Log::info('📝 Prompt construído', [
                'prompt_length' => strlen($prompt),
                'preview' => substr($prompt, 0, 200).'...',
            ]);

            // 6. Chamar IA via Prism
            $response = $this->callPrismAI($prompt, $config);

            // 7. Parsear resposta
            $executionTime = microtime(true) - $startTime;
            $shelfMetadata = $this->buildShelfMetadata($gondola);
            $result = $this->responseParserService->parseResponse(
                response: $response['text'],
                executionTime: $executionTime,
                tokensUsed: $response['usage']->totalTokens ?? 0,
                shelfMetadata: $shelfMetadata,
            );

            // 8. Salvar alocações no banco
            $this->saveAllocations($gondola, $result);

            Log::info('✅ Planograma gerado com sucesso via IA', [
                'total_allocated' => $result->totalAllocated,
                'total_unallocated' => $result->totalUnallocated,
                'confidence' => $result->confidence,
                'tokens_used' => $result->tokensUsed,
                'execution_time' => round($executionTime, 2).'s',
            ]);

            return $result;

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('❌ Erro ao gerar planograma com IA', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 2).'s',
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Estimar quantidade de SKUs únicos candidatos para geração IA.
     */
    public function estimateSelectionComplexity(string $gondolaId, IAGenerateConfigDTO $config): array
    {
        $this->setupTenantConnection();

        $gondola = $this->loadGondola($gondolaId);
        $products = $this->selectProducts($config, $gondola);

        $uniqueSkus = collect($products)
            ->pluck('id')
            ->filter()
            ->unique()
            ->count();

        return [
            'unique_skus' => $uniqueSkus,
            'total_selected' => count($products),
        ];
    }

    public function estimateSelectedUniqueSkus(string $gondolaId, IAGenerateConfigDTO $config): int
    {
        $complexity = $this->estimateSelectionComplexity($gondolaId, $config);

        return (int) ($complexity['unique_skus'] ?? 0);
    }

    /**
     * Setup conexão tenant
     */
    protected function setupTenantConnection(): void
    {
        $currentClientId = config('app.current_client_id');

        if (! $currentClientId) {
            throw new \RuntimeException('Cliente atual não definido (current_client_id)');
        }

        $client = Client::find($currentClientId);

        if (! $client) {
            throw new \RuntimeException("Cliente não encontrado: {$currentClientId}");
        }

        $this->setupClientConnection($client);

        Log::info('🔗 Conexão tenant configurada', [
            'client_id' => $client->id,
            'database' => config('database.connections.tenant.database'),
        ]);
    }

    /**
     * Carregar gôndola com relacionamentos
     */
    protected function loadGondola(string $gondolaId): Gondola
    {
        $gondola = Gondola::on($this->plannerateTenantConnectionName())
            ->with(['sections.shelves'])
            ->find($gondolaId);

        if (! $gondola) {
            throw new \RuntimeException("Gôndola não encontrada: {$gondolaId}");
        }

        return $gondola;
    }

    /**
     * Selecionar produtos usando query direta com distribuição ABC balanceada
     */
    protected function selectProducts(IAGenerateConfigDTO $config, Gondola $gondola): array
    {
        // Se category_id não fornecido, inferir da gôndola/planograma
        $categoryId = $config->categoryId;

        if (! $categoryId) {
            $categoryId = $this->inferCategoryFromGondola($gondola);
        }

        $categoryIds = $this->getCategoryIds($categoryId);

        // Distribuição ABC estratégica para melhor mix
        $totalProducts = 80; // Aumentado de 50 para 80 para melhor ocupação
        $targetDistribution = [
            'A' => (int) ($totalProducts * 0.25), // 20 produtos A (25%)
            'B' => (int) ($totalProducts * 0.35), // 28 produtos B (35%)
            'C' => (int) ($totalProducts * 0.40), // 32 produtos C (40%)
        ];

        $selectedProducts = collect();

        // Buscar produtos por classe ABC (somente classe correspondente)
        foreach (['A', 'B', 'C'] as $class) {
            $limit = $targetDistribution[$class];

            $productsInClass = Product::on($this->plannerateTenantConnectionName())
                ->whereIn('category_id', $categoryIds)
                ->leftJoin('product_analyses', function ($join) {
                    $join->on('products.id', '=', 'product_analyses.product_id')
                        ->whereRaw('product_analyses.analysis_date = (
                            SELECT MAX(analysis_date) 
                            FROM product_analyses pa2 
                            WHERE pa2.product_id = products.id
                        )');
                })
                ->where('product_analyses.abc_classification', strtolower($class))
                ->with(['category'])
                ->select([
                    'products.*',
                    'product_analyses.abc_classification',
                    'product_analyses.total_sales',
                    'product_analyses.average_sales',
                    'product_analyses.sales_rank',
                ])
                ->orderByRaw('COALESCE(product_analyses.total_sales, 0) DESC')
                ->orderByRaw('COALESCE(product_analyses.sales_rank, 999999) ASC')
                ->limit($limit)
                ->get();

            $selectedProducts = $selectedProducts->merge($productsInClass);
        }

        // Se não atingiu o total, completar com produtos não classificados
        if ($selectedProducts->count() < $totalProducts) {
            $remaining = $totalProducts - $selectedProducts->count();
            $existingIds = $selectedProducts->pluck('id')->toArray();

            $additionalProducts = Product::on($this->plannerateTenantConnectionName())
                ->whereIn('category_id', $categoryIds)
                ->whereNotIn('id', $existingIds)
                ->leftJoin('product_analyses', function ($join) {
                    $join->on('products.id', '=', 'product_analyses.product_id')
                        ->whereRaw('product_analyses.analysis_date = (
                            SELECT MAX(analysis_date)
                            FROM product_analyses pa2
                            WHERE pa2.product_id = products.id
                        )');
                })
                ->whereNull('product_analyses.abc_classification')
                ->with(['category'])
                ->select([
                    'products.*',
                    'product_analyses.abc_classification',
                    'product_analyses.total_sales',
                    'product_analyses.average_sales',
                    'product_analyses.sales_rank',
                ])
                ->orderByRaw('COALESCE(product_analyses.total_sales, 0) DESC')
                ->orderByRaw('COALESCE(product_analyses.sales_rank, 999999) ASC')
                ->limit($remaining)
                ->get();

            $selectedProducts = $selectedProducts->merge($additionalProducts);
        }

        $selectedProducts = $selectedProducts->unique('id')->values();

        // Converter para array simples
        return $selectedProducts->map(function ($p) {
            return [
                'id' => $p->id,
                'category_id' => $p->category_id,
                'name' => $p->name,
                'ean' => $p->ean,
                'brand' => $p->brand,
                'category' => $p->category?->name,
                'width' => $p->width ?? 0,
                'height' => $p->height ?? 0,
                'depth' => $p->depth ?? 0,
                'facing_min' => $p->facing_min ?? 1,
                'facing_max' => $p->facing_max ?? 6,
                'abc_class' => strtoupper($p->abc_classification ?? 'C'),
                'total_sales' => $p->total_sales ?? 0,
                'average_sales' => $p->average_sales ?? 0,
                'sales_rank' => $p->sales_rank ?? 999999,
            ];
        })->toArray();
    }

    /**
     * Inferir category_id a partir da gôndola
     */
    protected function inferCategoryFromGondola(Gondola $gondola): string
    {
        // Tentar pegar categoria do planograma
        $planogram = $gondola->section?->planogram;

        if ($planogram && $planogram->category_id) {
            return $planogram->category_id;
        }

        // Se não encontrar, buscar categoria raiz mais comum
        $rootCategory = Category::on($this->plannerateTenantConnectionName())
            ->whereNull('category_id')
            ->first();

        if (! $rootCategory) {
            throw new \RuntimeException('Nenhuma categoria raiz encontrada no sistema');
        }

        return $rootCategory->id;
    }

    /**
     * Obter IDs de categoria (incluindo subcategorias via CTE recursiva)
     */
    protected function getCategoryIds(string $categoryId): array
    {
        $connection = $this->plannerateTenantDatabase();

        // Verificar se categoria existe
        if (! $connection->table('categories')->where('id', $categoryId)->exists()) {
            throw new \RuntimeException("Categoria não encontrada: {$categoryId}");
        }

        // CTE recursiva para pegar categoria + subcategorias
        $categories = $connection->select(<<<'SQL'
            WITH RECURSIVE category_tree AS (
                SELECT id, category_id, name
                FROM categories
                WHERE id = ?
                
                UNION ALL
                
                SELECT c.id, c.category_id, c.name
                FROM categories c
                INNER JOIN category_tree ct ON c.category_id = ct.id
            )
            SELECT id FROM category_tree
        SQL, [$categoryId]);

        return array_column($categories, 'id');
    }

    /**
     * Construir contexto completo para IA
     */
    protected function buildContext(
        Gondola $gondola,
        array $products,
        IAGenerateConfigDTO $config
    ): PlanogramContextDTO {
        // Construir hierarquia de categorias
        $categoryIds = array_unique(array_column($products, 'category_id'));
        $categoryHierarchy = $this->buildCategoryHierarchy($categoryIds);

        // Regras de merchandising (pode ser expandido)
        $merchandisingRules = [
            'abc_positioning' => [
                'A' => ['min_height_percent' => 60, 'max_height_percent' => 90],
                'B' => ['min_height_percent' => 30, 'max_height_percent' => 60],
                'C' => ['min_height_percent' => 5, 'max_height_percent' => 30],
            ],
            'facings_rules' => [
                'A' => ['min' => 3, 'max' => 5],
                'B' => ['min' => 2, 'max' => 3],
                'C' => ['min' => 1, 'max' => 2],
            ],
        ];

        return PlanogramContextDTO::fromGondola(
            gondola: $gondola,
            products: $products,
            categoryHierarchy: $categoryHierarchy,
            merchandisingRules: $merchandisingRules,
        );
    }

    /**
     * Construir hierarquia de categorias
     */
    protected function buildCategoryHierarchy(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $categories = Category::on($this->plannerateTenantConnectionName())
            ->whereIn('id', $categoryIds)
            ->get(['id', 'name', 'parent_id'])
            ->toArray();

        return $categories;
    }

    /**
     * Chamar IA via Prism PHP (com cache)
     */
    protected function callPrismAI(string $prompt, IAGenerateConfigDTO $config): array
    {
        try {
            // Mapear modelo para provider + modelo
            [$provider, $model] = $this->parseModelString($config->model);

            Log::info('🔮 Chamando IA via Prism', [
                'provider' => $provider,
                'model' => $model,
                'max_tokens' => $config->maxTokens,
                'temperature' => $config->temperature,
            ]);

            // Gerar chave de cache baseada no prompt e config
            $cacheKey = 'ia_planogram_'.md5($prompt.$model.$config->maxTokens);

            // Verificar cache (1 hora de TTL)
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('💾 Usando resposta em cache', ['cache_key' => $cacheKey]);

                return $cached;
            }

            // Chamar API correta baseado no provider
            if ($provider === 'anthropic') {
                $response = $this->callAnthropicAPI($prompt, $model, $config);
            } else {
                $response = $this->callOpenAIAPI($prompt, $model, $config);
            }

            // Salvar no cache por 1 hora
            Cache::put($cacheKey, $response, now()->addHour());

            // Logar resposta completa para debug
            Log::debug('📝 Resposta completa da IA', [
                'text_length' => strlen($response['text']),
                'text_preview' => substr($response['text'], 0, 1000),
                'text_end' => substr($response['text'], -500),
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('❌ Erro ao chamar Prism', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Erro ao comunicar com IA: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Chamar API da Anthropic (Claude)
     */
    protected function callAnthropicAPI(string $prompt, string $model, IAGenerateConfigDTO $config): array
    {
        $client = new PendingRequest;
        $client->withHeaders([
            'x-api-key' => config('prism.providers.anthropic.api_key'),
            'anthropic-version' => config('prism.providers.anthropic.version', '2023-06-01'),
            'Content-Type' => 'application/json',
        ])->timeout(300);

        $apiResponse = $client->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $config->maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($apiResponse->failed()) {
            throw new \RuntimeException('API Anthropic retornou erro: '.$apiResponse->body());
        }

        $data = $apiResponse->json();

        Log::info('🤖 Anthropic stop reason recebido', [
            'model' => $model,
            'stop_reason' => $data['stop_reason'] ?? 'unknown',
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            'input_tokens' => $data['usage']['input_tokens'] ?? 0,
        ]);

        return [
            'text' => $data['content'][0]['text'] ?? '',
            'usage' => (object) [
                'promptTokens' => $data['usage']['input_tokens'] ?? 0,
                'completionTokens' => $data['usage']['output_tokens'] ?? 0,
                'totalTokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
            'response_data' => $data,
        ];
    }

    /**
     * Chamar API da OpenAI
     */
    protected function callOpenAIAPI(string $prompt, string $model, IAGenerateConfigDTO $config): array
    {
        $client = new PendingRequest;
        $client->withHeaders([
            'Authorization' => 'Bearer '.config('prism.providers.openai.api_key'),
            'Content-Type' => 'application/json',
        ])->timeout(60);

        $apiResponse = $client->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $config->maxTokens,
            'temperature' => $config->temperature,
        ]);

        if ($apiResponse->failed()) {
            throw new \RuntimeException('API OpenAI retornou erro: '.$apiResponse->body());
        }

        $data = $apiResponse->json();

        return [
            'text' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => (object) [
                'promptTokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completionTokens' => $data['usage']['completion_tokens'] ?? 0,
                'totalTokens' => $data['usage']['total_tokens'] ?? 0,
            ],
            'response_data' => $data,
        ];
    }

    /**
     * Mapear nome do modelo para [provider, modelo]
     */
    protected function parseModelString(string $model): array
    {
        // Mapeamento de modelos conhecidos
        $modelMap = [
            'gpt-4o' => ['openai', 'gpt-4o'],
            'gpt-4o-mini' => ['openai', 'gpt-4o-mini'],
            'gpt-4-turbo' => ['openai', 'gpt-4-turbo'],
            'claude-sonnet-4-20250514' => ['anthropic', 'claude-sonnet-4-20250514'],
            'claude-3-5-haiku-latest' => ['anthropic', 'claude-3-5-haiku-latest'],
            'gemini-2.0-flash-exp' => ['gemini', 'gemini-2.0-flash-exp'],
        ];

        if (isset($modelMap[$model])) {
            return $modelMap[$model];
        }

        // Tentar detectar provider pelo prefixo
        if (str_starts_with($model, 'gpt-')) {
            return ['openai', $model];
        }
        if (str_starts_with($model, 'claude-')) {
            return ['anthropic', $model];
        }
        if (str_starts_with($model, 'gemini-')) {
            return ['gemini', $model];
        }

        // Fallback: assume openai
        return ['openai', $model];
    }

    /**
     * Salvar alocações no banco de dados
     */
    protected function saveAllocations(Gondola $gondola, IAGenerateResultDTO $result): void
    {
        $this->plannerateTenantDatabase()->transaction(function () use ($gondola, $result) {
            // 1. Limpar segmentos existentes (igual ao AutoPlanogramService)
            $shelfIds = [];
            foreach ($gondola->sections as $section) {
                foreach ($section->shelves as $shelf) {
                    $shelfIds[] = $shelf->id;
                }
            }

            if (! empty($shelfIds)) {
                Segment::on($this->plannerateTenantConnectionName())->whereIn('shelf_id', $shelfIds)->delete();
            }

            // 2. Criar novos segmentos (seguindo padrão do AutoPlanogramService)
            foreach ($result->shelves as $shelfData) {
                $shelf = Shelf::on($this->plannerateTenantConnectionName())->find($shelfData['shelf_id']);

                if (! $shelf) {
                    Log::warning('⚠️ Prateleira não encontrada', ['shelf_id' => $shelfData['shelf_id']]);

                    continue;
                }

                $ordering = 0;

                // Criar segment + layer para cada produto
                foreach ($shelfData['products'] as $productData) {
                    $product = Product::on($this->plannerateTenantConnectionName())->find($productData['product_id']);

                    if (! $product) {
                        Log::warning('⚠️ Produto não encontrado', ['product_id' => $productData['product_id']]);

                        continue;
                    }

                    // Criar Segment
                    $segment = Segment::on($this->plannerateTenantConnectionName())->create([
                        'id' => (string) Str::ulid(),
                        'shelf_id' => $shelf->id,
                        'quantity' => 1,
                        'ordering' => $ordering++,
                    ]);

                    // Criar Layer
                    Layer::on($this->plannerateTenantConnectionName())->create([
                        'id' => (string) Str::ulid(),
                        'segment_id' => $segment->id,
                        'product_id' => $product->id,
                        'quantity' => $productData['facings'],
                    ]);
                }
            }

            Log::info('💾 Alocações salvas no banco', [
                'gondola_id' => $gondola->id,
                'shelves_updated' => count($result->shelves),
            ]);
        });
    }

    /**
     * Construir metadata de ordenação física das prateleiras
     *
     * @return array<string, array{section_id: string, section_order: int, shelf_order: int, shelf_position: int|null}>
     */
    protected function buildShelfMetadata(Gondola $gondola): array
    {
        $metadata = [];

        foreach ($gondola->sections as $sectionOrder => $section) {
            foreach ($section->shelves as $shelfOrder => $shelf) {
                $metadata[$shelf->id] = [
                    'section_id' => $section->id,
                    'section_order' => $sectionOrder,
                    'shelf_order' => $shelfOrder,
                    'shelf_position' => $shelf->shelf_position,
                ];
            }
        }

        return $metadata;
    }
}
