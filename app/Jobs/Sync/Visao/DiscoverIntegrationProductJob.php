<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs\Sync\Visao;

use App\Jobs\Sync\ProcessIntegrationDataJob;
use App\Models\Client;
use App\Models\Store;
use App\Notifications\IntegrationSyncFailureNotification;
use App\Services\Api\Visao\VisaoApiService;
use App\Services\Sync\IntegrationSyncRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverIntegrationProductJob implements ShouldQueue
{
    use Queueable;

    /**
     * Timeout do job em segundos (5 minutos)
     * Produtos podem demorar devido a múltiplas páginas
     */
    public int $timeout = 300;

    public function __construct(public Client $client, public Store $store, public array $integration) {}

    /**
     * Tags para organizar no Horizon
     */
    public function tags(): array
    {
        return [
            'sync:products',
            'integration:visao',
            "client:{$this->client->id}",
            "store:{$this->store->id}",
            $this->client->name,
        ];
    }

    /**
     * Nome descritivo para o Horizon
     */
    public function displayName(): string
    {
        return "Produtos Visão: {$this->client->name} - {$this->store->name}";
    }

    public function handle(IntegrationSyncRetryService $retryService): void
    {
        $syncType = 'products';
        $integrationType = $this->integration['integration_type'] ?? 'visao';
        $syncDate = now()->format('Y-m-d'); // Produtos: data do sync

        try {
            // 1. VERIFICA SE DEVE CONTINUAR (falhas consecutivas)
            $continueCheck = $retryService->shouldContinue($this->client, $this->store, $syncType);

            if (! $continueCheck['should_continue']) {
                Log::error($continueCheck['message']);

                // Notifica usuário sobre falha crítica
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

            // 2. VERIFICA SE DEVE PULAR HOJE (max retries)
            if ($retryService->shouldSkipDay($this->client, $this->store, $syncType, $syncDate)) {
                Log::warning("Pulando sync de produtos hoje - Max retries atingido");
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

            // Cria o serviço
            $service = new VisaoApiService(array_merge($this->integration, [
                'type' => $type,
            ]));

            // Valida a integração antes de fazer requisições
            if (! method_exists($service, 'validateIntegration') || ! $service->validateIntegration()) {
                Log::error('Integração Visão não está configurada corretamente', [
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

            Log::info('Iniciando descoberta de produtos Visão', [
                'client' => $this->client->name,
                'store' => $this->store->name,
            ]);

            // 3. TENTA SINCRONIZAR
            $result = $service->discoverPagination(
                type: $type,
                params: $this->integration['body'] ?? [],
                maxPages: $maxPages,
                callback: function ($data, $pageNumber) use ($type) {
                    ProcessIntegrationDataJob::dispatch(
                        client: $this->client,
                        store: $this->store,
                        context: $type,
                        data: $data,
                        integration: $this->integration
                    );

                    Log::info('Job de processamento de produtos despachado', [
                        'page' => $pageNumber,
                        'items' => count($data),
                        'client' => $this->client->name,
                    ]);
                }
            );

            // SUCESSO: Registra
            $retryService->recordAttempt(
                $this->client,
                $this->store,
                $integrationType,
                $syncType,
                $syncDate,
                'success',
                $result['total_items']
            );

            Log::info('Descoberta de produtos Visão concluída', [
                'client' => $this->client->name,
                'total_items' => $result['total_items'],
                'pages_processed' => $result['pages_processed'],
            ]);
        } catch (\Exception $e) {
            // FALHA: Registra
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

            Log::error('Erro ao conectar com a API Visão (produtos): '.$e->getMessage(), [
                'client_id' => $this->client->name,
                'trace' => $e->getMessage(),
            ]);
        }
    }
}
