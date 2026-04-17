<?php

/**
 * Comando para sincronização de produtos da API externa.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Sync;

use App\Console\Commands\Sync\Concerns\IntegrationConfigTrait;
use App\Jobs\Sync\Sysmo\DiscoverIntegrationProductJob as SysmoDiscoverProductJob;
use App\Jobs\Sync\Visao\DiscoverIntegrationProductJob as VisaoDiscoverProductJob;
use App\Models\Client;
use App\Models\ClientIntegration;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductsCommand extends Command
{
    use \App\Concerns\BelongsToConnection;
    use IntegrationConfigTrait;

    protected $signature = 'sync:products 
                            {--client= : ID do cliente específico para sincronizar}
                            {--truncate : Limpar produtos antes de sincronizar}
                            {--debug-config : Exibe a configuração de integração sem executar}';

    protected $description = 'Sincroniza produtos da API externa para o sistema local';

    public function handle(): int
    {
        // Modo debug: exibe configuração e sai
        if ($this->option('debug-config')) {
            return $this->handleDebugConfig();
        }

        // Truncar se solicitado
        if ($this->option('truncate')) {
            $this->handleTruncate();
        }

        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->warn('⚠️  Nenhum cliente encontrado.');

            return self::SUCCESS;
        }

        foreach ($clients as $client) {
            $this->processClient($client);
        }

        $this->info('✅ Sincronização de produtos concluída.');

        return self::SUCCESS;
    }

    /**
     * Exibe configuração de integração para debug
     */
    protected function handleDebugConfig(): int
    {
        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->error('❌ Nenhum cliente encontrado.');

            return self::FAILURE;
        }

        foreach ($clients as $client) {
            $this->debugIntegrationConfig($client);
        }

        return self::SUCCESS;
    }

    /**
     * Processa um cliente específico
     */
    protected function processClient(Client $client): void
    {
        $this->info("🔍 Processando: {$client->name}");

        $integration = $client->client_integration;

        if (! $integration || ! $integration->authentication_headers) {
            $this->warn('   ⚠️  Sem integração configurada. Pulando...');

            return;
        }

        $this->configureTenantContext($client);

        $stores = $client->storesDocument;

        if (! $stores || $stores->isEmpty()) {
            $this->warn('   ⚠️  Sem lojas com documento. Pulando...');

            return;
        }

        $this->info("   ✓ Integração: {$integration->integration_type} | Lojas: {$stores->count()}");

        foreach ($stores as $store) {
            $this->syncProductsForStore($client, $store, $integration);
        }
    }

    /**
     * Sincroniza produtos para uma loja específica
     */
    protected function syncProductsForStore(Client $client, Store $store, ClientIntegration $integration): void
    {
        $document = preg_replace('/\D/', '', $store->document);
        $this->line("   📍 Loja: {$store->name} ({$document})");

        $config = $this->prepareBaseIntegrationConfig($client, $store, $integration);
        $config['type'] = 'products';

        $integrationType = $integration->integration_type;

        try {
            match ($integrationType) {
                'sysmo' => SysmoDiscoverProductJob::dispatch(
                    client: $client,
                    store: $store,
                    integration: $config,
                ),
                'visao' => VisaoDiscoverProductJob::dispatch(
                    client: $client,
                    store: $store,
                    integration: $config,
                ),
                default => $this->warn("      ⚠️  Integração não suportada: {$integrationType}")
            };

            $this->info('      ✓ Job despachado');
        } catch (\Exception $e) {
            $this->error("      ❌ Erro: {$e->getMessage()}");
            Log::error('Erro ao despachar job de produtos', [
                'client_id' => $client->id,
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trunca produtos baseado no escopo
     */
    protected function handleTruncate(): void
    {
        $clientId = $this->option('client');

        if ($clientId) {
            $this->truncateClientProducts($clientId);
        } else {
            $this->truncateAllProducts();
        }
    }

    /**
     * Trunca produtos de um cliente específico
     */
    protected function truncateClientProducts(string $clientId): void
    {
        $client = Client::find($clientId);

        if (! $client) {
            $this->error("❌ Cliente {$clientId} não encontrado.");

            return;
        }

        $this->configureTenantContext($client);

        $productIds = DB::connection($this->getClientConnection())->table('products')->pluck('id');
        $count = $productIds->count();

        if ($count === 0) {
            $this->info("Nenhum produto encontrado para '{$client->name}'.");

            return;
        }

        if (! $this->confirm("⚠️  Excluir {$count} produtos de '{$client->name}'?")) {
            $this->warn('Operação cancelada.');

            return;
        }

        DB::transaction(function () use ($productIds, $client, $count) {
            $conn = $this->getClientConnection();

            DB::connection($conn)->table('product_store')->whereIn('product_id', $productIds)->delete();
            DB::connection($conn)->table('product_provider')->whereIn('product_id', $productIds)->delete();
            DB::connection($conn)->table('products')->whereIn('id', $productIds)->delete();

            $this->info("✅ {$count} produtos excluídos de '{$client->name}'.");

            Log::info('Produtos truncados', [
                'client_id' => $client->id,
                'count' => $count,
            ]);
        });
    }

    /**
     * Trunca produtos de todos os clientes
     */
    protected function truncateAllProducts(): void
    {
        $clients = $this->getClients();

        foreach ($clients as $client) {
            $this->truncateClientProducts($client->id);
        }
    }
}
