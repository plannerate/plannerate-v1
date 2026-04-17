<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Sync;

use App\Enums\ClientStatus;
use App\Jobs\Sync\Sysmo\DiscoverIntegrationProductJob as SysmoProductJob;
use App\Jobs\Sync\Sysmo\DiscoverIntegrationSaleJob as SysmoSaleJob;
use App\Jobs\Sync\Visao\DiscoverIntegrationProductJob as VisaoProductJob;
use App\Jobs\Sync\Visao\DiscoverIntegrationSaleJob as VisaoSaleJob;
use App\Models\Client;
use App\Services\Sync\IntegrationSyncRetryService;
use Illuminate\Console\Command;

class RetryIntegrationSyncCommand extends Command
{
    use \App\Concerns\BelongsToConnection;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:retry 
                            {--client= : ID do cliente específico}
                            {--type=sales : Tipo de sincronização (sales, products, purchases)}
                            {--force : Forçar retry mesmo com max_retries atingido}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-tenta sincronizações que falharam anteriormente';

    /**
     * Execute the console command.
     */
    public function handle(IntegrationSyncRetryService $retryService): int
    {
        $syncType = $this->option('type');
        $forceRetry = $this->option('force');
        $clientId = $this->option('client');

        $this->info("🔄 Iniciando retry de sincronizações do tipo: {$syncType}");

        // Busca clientes ativos
        $query = Client::query()
            ->where('status', ClientStatus::Published->value)
            ->with(['client_integration', 'storesDocument']);

        if ($clientId) {
            $query->where('id', $clientId);
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            $this->warn('Nenhum cliente encontrado para retry.');

            return self::SUCCESS;
        }

        $this->info("Clientes encontrados: {$clients->count()}");

        $totalRetries = 0;

        foreach ($clients as $client) {
            // Configura o contexto do tenant/client antes de processar
            config([
                'app.current_tenant_id' => $client->tenant_id,
                'app.current_client_id' => $client->id,
            ]);
            $this->setupClientConnection($client);

            if (! $integration = $client->client_integration) {
                continue;
            }

            $stores = $client->storesDocument;

            if ($stores->isEmpty()) {
                continue;
            }

            foreach ($stores as $store) {
                $this->info("\n📦 Cliente: {$client->name} | Loja: {$store->name}");

                // Verifica se pode continuar (falhas consecutivas)
                if (! $forceRetry) {
                    $continueCheck = $retryService->shouldContinue($client, $store, $syncType);

                    if (! $continueCheck['should_continue']) {
                        $this->error("  ❌ {$continueCheck['message']}");

                        continue;
                    }
                }

                // Busca dias que precisam de retry
                $failedDays = $forceRetry
                    ? $retryService->getDaysToSkip($client, $store, $syncType)
                    : $retryService->getDaysNeedingRetry($client, $store, $syncType);

                if ($failedDays->isEmpty()) {
                    $this->info('  ✅ Nenhum dia com falhas para retry');

                    continue;
                }

                $this->warn("  🔁 {$failedDays->count()} dia(s) precisam de retry");

                // Estatísticas antes do retry
                $statsBefore = $retryService->getSyncStats($client, $store, $syncType);
                $this->table(
                    ['Métrica', 'Valor'],
                    [
                        ['Total de Dias', $statsBefore['total_days']],
                        ['Sucessos', $statsBefore['success']],
                        ['Falhas', $statsBefore['failed']],
                        ['Pulados', $statsBefore['skipped']],
                        ['Pendentes', $statsBefore['pending']],
                    ]
                );

                // Prepara integração baseado no tipo
                if ($syncType === 'products') {
                    // Produtos: re-despacha job de produto
                    $this->retryProducts($client, $store, $integration, $syncType);
                    $totalRetries++;
                } else {
                    // Vendas: re-despacha job por dia
                    $totalRetries += $this->retryDays($client, $store, $integration, $failedDays, $syncType);
                }
            }
        }

        $this->info("\n✅ Retry concluído! Total de jobs despachados: {$totalRetries}");

        return self::SUCCESS;
    }

    /**
     * Re-tenta sincronização de dias específicos (vendas)
     */
    private function retryDays(Client $client, $store, $integration, $failedDays, string $syncType): int
    {
        $count = 0;
        $integrationType = $integration->integration_type;

        // Prepara configuração base
        $config = $this->prepareIntegrationConfig($integration, $store);

        foreach ($failedDays as $log) {
            $date = $log->sync_date;
            $this->line("    ↻ Retry: {$date}");

            // Define data_inicial = data_final = dia específico
            $config['body']['data_inicial'] = $date;
            $config['body']['data_final'] = $date;

            // Despacha job correto baseado na integração
            $jobClass = match ($integrationType) {
                'visao' => VisaoSaleJob::class,
                'sysmo' => SysmoSaleJob::class,
                default => null
            };

            if ($jobClass) {
                $jobClass::dispatch($client, $store, $config);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Re-tenta sincronização de produtos (única tentativa)
     */
    private function retryProducts(Client $client, $store, $integration, string $syncType): void
    {
        $integrationType = $integration->integration_type;
        $config = $this->prepareIntegrationConfig($integration, $store);
        $config['type'] = 'products';

        $this->line('    ↻ Retry: sync completo de produtos');

        // Despacha job de produtos
        $jobClass = match ($integrationType) {
            'visao' => VisaoProductJob::class,
            'sysmo' => SysmoProductJob::class,
            default => null
        };

        if ($jobClass) {
            $jobClass::dispatch($client, $store, $config);
        }
    }

    /**
     * Prepara configuração da integração para dispatch de jobs
     */
    private function prepareIntegrationConfig($integration, $store): array
    {
        $dataIntegration = $integration->toArray();

        // Processa headers de autenticação
        $authHeaders = $this->transformToAssociativeArray($dataIntegration, 'authentication_headers');
        $authBody = $this->transformToAssociativeArray($dataIntegration, 'authentication_body');
        $config = $this->transformToAssociativeArray($dataIntegration, 'config');

        // Adiciona documento da loja
        $documentName = data_get($config['config'], 'document_name', 'document');
        $authBody['authentication_body'][$documentName] = preg_replace('/\D/', '', $store->document);

        return array_merge($dataIntegration, [
            'base_url' => data_get($dataIntegration, 'api_url', ''),
            'headers' => [
                'authorization' => [
                    'username' => $authHeaders['authentication_headers']['auth_username'] ?? null,
                    'password' => $authHeaders['authentication_headers']['auth_password'] ?? null,
                ],
            ],
            'body' => $authBody['authentication_body'],
        ]);
    }

    /**
     * Transforma array de key/value em array associativo
     */
    private function transformToAssociativeArray(array $data, string $field): array
    {
        if (isset($data[$field]) && is_array($data[$field])) {
            $transformed = [];
            foreach ($data[$field] as $item) {
                if (isset($item['key']) && isset($item['value'])) {
                    $transformed[$item['key']] = $item['value'];
                }
            }
            $data[$field] = $transformed;
        }

        return $data;
    }
}
