<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs\Sync;

use App\Events\Sync\SyncProgressEvent;
use App\Models\Client;
use App\Models\Store;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job responsável por processar e persistir dados coletados das integrações
 *
 * Suporta 3 contextos diferentes:
 * - products: Processamento de produtos
 * - sales: Processamento de vendas
 * - purchase: Processamento de compras
 */
class ProcessIntegrationDataJob implements ShouldQueue
{
    use \App\Concerns\BelongsToConnection, Queueable;

    /**
     * Número de tentativas do job
     */
    public $tries = 5;

    /**
     * Tempo máximo de execução (5 minutos)
     */
    public $timeout = 300;

    /**
     * Backoff exponencial para deadlocks
     * Aguarda progressivamente mais tempo entre tentativas: 3s, 9s, 27s, 81s
     */
    public $backoff = [3, 9, 27, 81];

    /**
     * @param  Client  $client  Cliente relacionado aos dados
     * @param  string  $context  Contexto dos dados (products, sales, purchase)
     * @param  array  $data  Dados coletados da API
     * @param  array  $integration  Configurações da integração
     */
    public function __construct(
        public Client $client,
        public Store $store,
        public string $context,
        public array $data,
        public array $integration
    ) {}

    public function handle(): void
    {
        $this->setupClientConnection($this->client);
        try {
            // Notifica início do processamento
            event(new SyncProgressEvent(
                userId: null, // null = broadcast para todos os usuários do client
                clientId: $this->client->id,
                clientName: $this->client->name,
                storeName: $this->store->name,
                type: 'started',
                context: $this->context,
                date: $this->integration['single_date'] ?? null,
                totalItems: count($this->data),
                processedItems: 0,
                message: 'Iniciando processamento...'
            ));

            Log::info('Processando dados de integração', [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
                'context' => $this->context,
                'total_items' => count($this->data),
                'integration_type' => $this->integration['integration_type'] ?? 'unknown',
                'attempt' => $this->attempts(),
            ]);

            // Processa de acordo com o contexto
            match ($this->context) {
                'products' => $this->processProducts(),
                'sales' => $this->processSales(),
                'purchase' => $this->processPurchase(),
                'stock' => $this->processStock(),
                default => throw new \InvalidArgumentException("Contexto inválido: {$this->context}")
            };

            // Notifica conclusão
            event(new SyncProgressEvent(
                userId: null,
                clientId: $this->client->id,
                clientName: $this->client->name,
                storeName: $this->store->name,
                type: 'completed',
                context: $this->context,
                date: $this->integration['single_date'] ?? null,
                totalItems: count($this->data),
                processedItems: count($this->data),
                message: 'Processamento concluído com sucesso!'
            ));

            Log::info('Dados processados com sucesso', [
                'client_id' => $this->client->id,
                'context' => $this->context,
                'total_items' => count($this->data),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Detecta deadlock e permite retry
            if ($this->isDeadlock($e)) {
                Log::warning('Deadlock detectado, job será retentado', [
                    'client_id' => $this->client->id,
                    'context' => $this->context,
                    'attempt' => $this->attempts(),
                    'error' => $e->getMessage(),
                ]);

                // Relança a exceção para que o Laravel faça retry
                throw $e;
            }

            // Outros erros de query
            Log::error('Erro de query ao processar dados de integração', [
                'client_id' => $this->client->id,
                'context' => $this->context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            // Notifica falha
            event(new SyncProgressEvent(
                userId: null,
                clientId: $this->client->id,
                clientName: $this->client->name,
                storeName: $this->store->name,
                type: 'failed',
                context: $this->context,
                date: $this->integration['single_date'] ?? null,
                totalItems: count($this->data),
                processedItems: 0,
                message: 'Erro: '.$e->getMessage()
            ));

            Log::error('Erro ao processar dados de integração', [
                'client_id' => $this->client->id,
                'context' => $this->context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Verifica se a exceção é um deadlock do PostgreSQL
     */
    protected function isDeadlock(\Illuminate\Database\QueryException $e): bool
    {
        // PostgreSQL deadlock error code: 40P01
        // MySQL deadlock error code: 1213
        return str_contains($e->getMessage(), 'Deadlock detected')
            || str_contains($e->getMessage(), '40P01')
            || $e->errorInfo[1] ?? null === 1213;
    }

    /**
     * Processa produtos da integração
     * TODO: Implementar lógica de persistência
     */
    protected function processProducts(): void
    {
        Log::info('Processando produtos', [
            'client_id' => $this->client->id,
            'total' => count($this->data),
        ]);
        $data = match ($this->integration['integration_type'] ?? 'unknown') {
            'sysmo' => array_map(fn ($item) => (new \App\DTOs\Sysmo\ProductDTO)->process($item, [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
                'tenant_id' => $this->client->tenant_id ?? config('app.current_tenant_id'),
                'user_id' => $this->client->user_id,
            ]), $this->data),
            'visao' => array_map(fn ($item) => (new \App\DTOs\Visao\ProductDTO)->process($item, [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
                'tenant_id' => $this->client->tenant_id ?? config('app.current_tenant_id'),
                'user_id' => $this->client->user_id,
            ]), $this->data),
            default => [],
        };

        // Persistência dos produtos no banco de dados
        $this->persistProducts($data);
    }

    /**
     * Persiste produtos no banco usando upsert (IDs determinísticos)
     */
    protected function persistProducts(array $productsData): void
    {
        // Filtra produtos nulos (validação falhou)
        $productsData = array_filter($productsData);

        if (empty($productsData)) {
            Log::warning('Nenhum produto válido para persistir', [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
            ]);

            return;
        }

        // Pequeno delay aleatório para reduzir colisões entre jobs paralelos
        if ($this->attempts() > 1) {
            usleep(random_int(100000, 500000)); // 100ms a 500ms
        }

        $products = [];
        $clientProducts = [];
        $productStores = [];
        $providers = [];
        $productProviders = [];

        // Agrupa dados por tabela
        foreach ($productsData as $productData) {
            if (empty($productData)) {
                continue;
            }

            // additional_data agora está mesclado diretamente no product
            // client_id e codigo_erp agora estão diretamente no produto (não precisa de pivot client_product)
            $product = $productData['product'];

            // Mescla client_id e codigo_erp do client_product diretamente no produto
            if (isset($productData['client_product'])) {
                $product['client_id'] = $productData['client_product']['client_id'] ?? null;
                // Se codigo_erp não estiver no produto mas estiver no client_product, usa ele
                if (empty($product['codigo_erp']) && ! empty($productData['client_product']['codigo_erp'])) {
                    $product['codigo_erp'] = $productData['client_product']['codigo_erp'];
                }
            }

            $products[] = $product;
            $productStores[] = $productData['product_store'];

            // Providers retorna array com ['providers' => [], 'pivots' => []]
            if (! empty($productData['providers']['providers'])) {
                $providers = array_merge($providers, $productData['providers']['providers']);
            }
            if (! empty($productData['providers']['pivots'])) {
                $productProviders = array_merge($productProviders, $productData['providers']['pivots']);
            }
        }

        try {
            // 1. Upsert produtos em lotes
            // Nota: product_additional_data foi removida, campos agora estão diretamente em products
            try {
                $productChunks = array_chunk($products, 1000);
                foreach ($productChunks as $index => $chunk) {
                    DB::connection($this->getClientConnection())->table('products')->upsert(
                        $chunk,
                        ['id'], // Chave única
                        [
                            'name',
                            'description',
                            'status',
                            'codigo_erp',
                            'ean',
                            'client_id',
                            'updated_at',
                            // Campos de additional_data (agora diretamente em products)
                            'type',
                            'reference',
                            'fragrance',
                            'flavor',
                            'color',
                            'brand',
                            'subbrand',
                            'packaging_type',
                            'packaging_size',
                            'measurement_unit',
                            'packaging_content',
                            'unit_measure',
                            'auxiliary_description',
                            'additional_information',
                            'sortiment_attribute',
                            'current_stock', // Novo campo para estoque atual
                        ]
                    );

                    Log::info('Lote de produtos processado', [
                        'batch' => $index + 1,
                        'total_batches' => count($productChunks),
                        'batch_size' => count($chunk),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erro específico ao fazer upsert de produtos', [
                    'error' => $e->getMessage(),
                    'products_count' => count($products),
                    'first_product' => $products[0] ?? null,
                ]);
                throw $e;
            }

            // 3. client_id e codigo_erp agora estão diretamente em products (não precisa de pivot client_product)
            // Os valores já foram mesclados no array $products antes do upsert

            // 4. Upsert relação product-store (pivot) em lotes
            if (! empty($productStores)) {
                $productStoreChunks = array_chunk($productStores, 1000);
                foreach ($productStoreChunks as $chunk) {
                    DB::connection($this->getClientConnection())->table('product_store')->upsert(
                        $chunk,
                        ['store_id', 'product_id'],
                        ['sync_date', 'updated_at']
                    );
                }
            }

            // 5. Upsert fornecedores (providers) em lotes - deduplica por ID
            if (! empty($providers)) {
                // Remove duplicatas mantendo o último provider de cada ID
                $uniqueProviders = collect($providers)
                    ->keyBy('id')
                    ->values()
                    ->toArray();

                $providerChunks = array_chunk($uniqueProviders, 1000);
                foreach ($providerChunks as $chunk) {
                    DB::connection($this->getClientConnection())->table('providers')->upsert(
                        $chunk,
                        ['id'],
                        ['name', 'description', 'cnpj', 'code', 'status', 'updated_at']
                    );
                }
            }

            // 6. Upsert relação product-provider (pivot) em lotes - deduplica por chave composta
            if (! empty($productProviders)) {
                // Remove duplicatas mantendo o último registro de cada combinação product_id + provider_id
                $uniqueProductProviders = collect($productProviders)
                    ->map(function ($item) {
                        return array_merge($item, [
                            '_key' => $item['product_id'].'|'.$item['provider_id'],
                        ]);
                    })
                    ->keyBy('_key')
                    ->map(function ($item) {
                        unset($item['_key']);

                        return $item;
                    })
                    ->values()
                    ->toArray();

                $productProviderChunks = array_chunk($uniqueProductProviders, 1000);
                foreach ($productProviderChunks as $chunk) {
                    DB::connection($this->getClientConnection())->table('product_provider')->upsert(
                        $chunk,
                        ['product_id', 'provider_id'],
                        ['codigo_erp', 'updated_at']
                    );
                }
            }

            Log::info('Produtos e dados relacionados persistidos', [
                'products' => count($products),
                'client_products' => count($clientProducts),
                'product_stores' => count($productStores),
                'providers' => count($providers).' (unique: '.count($uniqueProviders ?? []).')',
                'product_providers' => count($productProviders).' (unique: '.count($uniqueProductProviders ?? []).')',
                'client_id' => $this->client->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao persistir produtos', [
                'client_id' => $this->client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Processa vendas da integração
     */
    protected function processSales(): void
    {
        Log::info('Processando vendas', [
            'client_id' => $this->client->id,
            'total' => count($this->data),
        ]);

        $salesData = match ($this->integration['integration_type'] ?? 'unknown') {
            'sysmo' => array_map(fn ($item) => (new \App\DTOs\Sysmo\SaleDTO)->process($item, [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
                'tenant_id' => $this->client->tenant_id ?? config('app.current_tenant_id'),
                'user_id' => $this->client->user_id,
                'integration_id' => $this->integration['id'] ?? null,
            ]), $this->data),
            'visao' => array_map(fn ($item) => (new \App\DTOs\Visao\SaleDTO)->process($item, [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
                'tenant_id' => $this->client->tenant_id ?? config('app.current_tenant_id'),
                'user_id' => $this->client->user_id,
                'integration_id' => $this->integration['id'] ?? null,
            ]), $this->data),
            default => [],
        };

        // Persistência das vendas no banco de dados
        $this->persistSales($salesData);
    }

    /**
     * Persiste vendas no banco usando upsert (IDs determinísticos)
     */
    protected function persistSales(array $salesData): void
    {
        // Filtra vendas nulas (validação falhou)
        $salesData = array_filter($salesData);

        if (empty($salesData)) {
            Log::warning('Nenhuma venda válida para persistir', [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
            ]);

            return;
        }

        try {
            // Divide em lotes de 1000 para evitar limite de 65535 parâmetros do PostgreSQL
            // Cada venda tem ~17 campos, então 1000 vendas = ~17000 parâmetros (seguro)
            $chunks = array_chunk($salesData, 1000);
            $totalProcessed = 0;

            foreach ($chunks as $index => $chunk) {
                // Upsert vendas usando IDs determinísticos
                DB::connection($this->getClientConnection())->table('sales')->upsert(
                    $chunk,
                    ['id'], // Chave única determinística
                    [
                        'product_id',
                        'store_id',
                        'ean',
                        'codigo_erp',
                        'acquisition_cost',
                        'sale_price',
                        'total_profit_margin',
                        'sale_date',
                        'promotion',
                        'total_sale_quantity',
                        'total_sale_value',
                        'margem_contribuicao',
                        'extra_data',
                        'updated_at',
                    ]
                );

                $totalProcessed += count($chunk);

                Log::info('Lote de vendas processado', [
                    'batch' => $index + 1,
                    'total_batches' => count($chunks),
                    'batch_size' => count($chunk),
                    'total_processed' => $totalProcessed,
                    'client_id' => $this->client->id,
                    'store_id' => $this->store->id,
                ]);

                dispatch(new \App\Jobs\Sync\RecalculateMonthlySalesSummariesJob(
                    client: $this->client,
                    chunk: $chunk,
                ));
            }

            Log::info('Vendas persistidas com sucesso', [
                'total_sales' => $totalProcessed,
                'total_batches' => count($chunks),
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao persistir vendas', [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Processa compras da integração
     * TODO: Implementar lógica de persistência
     */
    protected function processPurchase(): void
    {
        Log::info('Processando compras', [
            'client_id' => $this->client->id,
            'total' => count($this->data),
        ]);

        // TODO: Implementar lógica
        // - Validar dados
        // - Transformar formato
        // - Criar/atualizar compras no banco
        // - Associar com cliente e produtos
    }

    /**
     * Processa estoque da integração — atualiza current_stock nos produtos via EAN
     */
    protected function processStock(): void
    {
        Log::info('Processando estoque', [
            'client_id' => $this->client->id,
            'total' => count($this->data),
        ]);

        $data = match ($this->integration['integration_type'] ?? 'unknown') {
            'sysmo' => array_map(fn ($item) => (new \App\DTOs\Sysmo\StockDTO)->process($item, [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
                'tenant_id' => $this->client->tenant_id ?? config('app.current_tenant_id'),
                'user_id' => $this->client->user_id,
            ]), $this->data),
            default => [],
        };

        $this->persistStock($data);
    }

    /**
     * Persiste estoque atualizando current_stock em products via EAN.
     *
     * Usa uma única query UPDATE com CASE WHEN por lote de 1000 registros,
     * evitando N queries individuais para grandes volumes.
     */
    protected function persistStock(array $stockData): void
    {
        $stockData = array_values(array_filter($stockData, fn ($item) => ! empty($item['ean'])));

        if (empty($stockData)) {
            Log::warning('Nenhum dado de estoque válido para persistir', [
                'client_id' => $this->client->id,
                'store_id' => $this->store->id,
            ]);

            return;
        }

        $conn = $this->getClientConnection();
        $updatedAt = now()->format('Y-m-d H:i:s');
        $totalUpdated = 0;
        $chunks = array_chunk($stockData, 1000);

        foreach ($chunks as $chunk) {
            $eans = array_column($chunk, 'ean');
            $stockByEan = array_column($chunk, 'current_stock', 'ean');

            // Monta CASE WHEN ean = ? THEN ?::double precision ... END para atualizar em uma query
            $cases = '';
            $bindings = [];
            foreach ($chunk as $item) {
                $cases .= 'WHEN ean = ? THEN ?::double precision ';
                $bindings[] = $item['ean'];
                $bindings[] = (float) $item['current_stock'];
            }
            $bindings[] = $updatedAt;
            $bindings = array_merge($bindings, $eans);

            $affected = DB::connection($conn)->update(
                "UPDATE products SET current_stock = CASE {$cases}END, updated_at = ? WHERE ean IN (".implode(',', array_fill(0, count($eans), '?')).')',
                $bindings
            );

            $totalUpdated += $affected;
        }

        Log::info('Estoque atualizado em massa', [
            'client_id' => $this->client->id,
            'store_id' => $this->store->id,
            'updated_rows' => $totalUpdated,
            'total_items' => count($stockData),
            'batches' => count($chunks),
        ]);
    }
}
