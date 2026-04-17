<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs\Sync\Sysmo;

use App\Jobs\Sync\ProcessIntegrationDataJob;
use App\Models\Client;
use App\Models\Store;
use App\Notifications\IntegrationSyncFailureNotification;
use App\Services\Api\Sysmo\SysmoApiService;
use App\Services\Sync\IntegrationSyncRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverIntegrationStockJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public Client $client, public Store $store, public array $integration) {}

    public function tags(): array
    {
        return [
            'sync:stock',
            'integration:sysmo',
            "client:{$this->client->id}",
            "store:{$this->store->id}",
            $this->client->name,
        ];
    }

    public function displayName(): string
    {
        return "Estoque Sysmo: {$this->client->name} - {$this->store->name}";
    }

    public function handle(IntegrationSyncRetryService $retryService): void
    {
        $syncType = 'stock';
        $integrationType = $this->integration['integration_type'] ?? 'sysmo';
        $syncDate = now()->format('Y-m-d');

        try {
            $continueCheck = $retryService->shouldContinue($this->client, $this->store, $syncType);

            if (! $continueCheck['should_continue']) {
                Log::error($continueCheck['message']);

                $failedDays = $retryService->getDaysToSkip($this->client, $this->store, $syncType);
                $this->client->notify(new IntegrationSyncFailureNotification(
                    $this->client,
                    $this->store,
                    $syncType,
                    'critical',
                    $failedDays->toArray(),
                    $continueCheck['consecutive_failures']
                ));

                return;
            }

            if ($retryService->shouldSkipDay($this->client, $this->store, $syncType, $syncDate)) {
                Log::warning('Pulando sync de estoque hoje - Max retries atingido');
                $retryService->recordAttempt(
                    $this->client,
                    $this->store,
                    $integrationType,
                    $syncType,
                    $syncDate,
                    'skipped'
                );

                return;
            }

            $type = $this->integration['type'] ?? 'products';
            $maxPages = $this->integration['max_pages'] ?? null;

            $service = new SysmoApiService(array_merge($this->integration, [
                'type' => $type,
            ]));

            if (! method_exists($service, 'validateIntegration') || ! $service->validateIntegration()) {
                Log::error('Integração Sysmo não está configurada corretamente', [
                    'client_id' => $this->client->id,
                    'integration' => $this->integration,
                ]);

                $retryService->recordAttempt(
                    $this->client,
                    $this->store,
                    $integrationType,
                    $syncType,
                    $syncDate,
                    'failed',
                    null,
                    'Integração não configurada'
                );

                return;
            }

            Log::info('Iniciando descoberta de estoque Sysmo', [
                'client' => $this->client->name,
                'store' => $this->store->name,
            ]);

            $result = $service->discoverPagination(
                type: $type,
                params: $this->integration['body'] ?? [],
                maxPages: $maxPages,
                callback: function ($data, $pageNumber) {
                    ProcessIntegrationDataJob::dispatch(
                        client: $this->client,
                        store: $this->store,
                        context: 'stock',
                        data: $data,
                        integration: $this->integration
                    );

                    Log::info('Job de processamento de estoque despachado', [
                        'page' => $pageNumber,
                        'items' => count($data),
                        'client' => $this->client->name,
                        'store' => $this->store->name,
                    ]);
                }
            );

            $retryService->recordAttempt(
                $this->client,
                $this->store,
                $integrationType,
                $syncType,
                $syncDate,
                'success',
                $result['total_items']
            );

            Log::info('Descoberta de estoque Sysmo concluída', [
                'client' => $this->client->name,
                'store' => $this->store->name,
                'total_items' => $result['total_items'],
                'pages_processed' => $result['pages_processed'],
            ]);
        } catch (\Exception $e) {
            $retryService->recordAttempt(
                $this->client,
                $this->store,
                $integrationType,
                $syncType,
                $syncDate,
                'failed',
                null,
                $e->getMessage(),
                ['trace' => $e->getTraceAsString()]
            );

            Log::error('Erro ao conectar com a API Sysmo (estoque): '.$e->getMessage(), [
                'client' => $this->client->name,
                'store' => $this->store->name,
                'trace' => $e->getMessage(),
            ]);
        }
    }
}
