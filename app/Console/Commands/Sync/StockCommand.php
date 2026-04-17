<?php

/**
 * Comando para sincronização de estoque da API externa.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Sync;

use App\Console\Commands\Sync\Concerns\IntegrationConfigTrait;
use App\Jobs\Sync\Sysmo\DiscoverIntegrationStockJob as SysmoDiscoverStockJob;
use App\Jobs\Sync\Visao\DiscoverIntegrationStockJob as VisaoDiscoverStockJob;
use App\Models\Client;
use App\Models\ClientIntegration;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StockCommand extends Command
{
    use \App\Concerns\BelongsToConnection;
    use IntegrationConfigTrait;

    protected $signature = 'sync:stock 
                            {--client= : ID do cliente específico para sincronizar}
                            {--debug-config : Exibe a configuração de integração sem executar}';

    protected $description = 'Sincroniza estoque dos produtos da API externa para o sistema local';

    public function handle(): int
    {
        if ($this->option('debug-config')) {
            return $this->handleDebugConfig();
        }

        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->warn('⚠️  Nenhum cliente encontrado.');

            return self::SUCCESS;
        }

        foreach ($clients as $client) {
            $this->processClient($client);
        }

        $this->info('✅ Sincronização de estoque concluída.');

        return self::SUCCESS;
    }

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
            $this->syncStockForStore($client, $store, $integration);
        }
    }

    protected function syncStockForStore(Client $client, Store $store, ClientIntegration $integration): void
    {
        $document = preg_replace('/\D/', '', $store->document);
        $this->line("   📍 Loja: {$store->name} ({$document})");

        $config = $this->prepareBaseIntegrationConfig($client, $store, $integration);
        $config['type'] = 'products';

        $integrationType = $integration->integration_type;

        try {
            match ($integrationType) {
                'sysmo' => SysmoDiscoverStockJob::dispatch(
                    client: $client,
                    store: $store,
                    integration: $config,
                ),
                'visao' => VisaoDiscoverStockJob::dispatch(
                    client: $client,
                    store: $store,
                    integration: $config,
                ),
                default => $this->warn("      ⚠️  Integração não suportada: {$integrationType}")
            };

            $this->info('      ✓ Job despachado');
        } catch (\Exception $e) {
            $this->error("      ❌ Erro: {$e->getMessage()}");
            Log::error('Erro ao despachar job de estoque', [
                'client_id' => $client->id,
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
