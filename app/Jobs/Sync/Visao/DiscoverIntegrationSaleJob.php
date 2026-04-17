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
use App\Services\Sync\IntegrationCircuitBreaker;
use App\Services\Sync\IntegrationSyncRetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverIntegrationSaleJob implements ShouldQueue
{
    use Queueable;

    /**
     * Timeout do job em segundos (30 minutos)
     * Vendas podem ter múltiplos dias e muitos registros
     */
    public int $timeout = 1800;

    /**
     * Se true, processa de forma síncrona (modo sequencial)
     */
    public bool $sequential = false;

    public function __construct(public Client $client, public Store $store, public array $integration, bool $sequential = false)
    {
        $this->sequential = $sequential;
    }

    /**
     * Tags para organizar no Horizon
     */
    public function tags(): array
    {
        return [
            'sync:sales',
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
        return "Vendas Visão: {$this->client->name} - {$this->store->name}";
    }

    public function handle(IntegrationSyncRetryService $retryService): void
    {
        $syncType = 'sales';
        $integrationType = $this->integration['integration_type'] ?? 'visao';

        try {
            // 0. VERIFICA CIRCUIT BREAKER (proteção contra integrações fora do ar)
            if (IntegrationCircuitBreaker::isOpen($this->client->id, $this->store->id, $integrationType)) {
                Log::warning('Job cancelado por Circuit Breaker', [
                    'client' => $this->client->name,
                    'store' => $this->store->name,
                    'integration_type' => $integrationType,
                ]);

                return;
            }

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

            $type = $this->integration['type'] ?? 'sales';
            $body = $this->integration['body'] ?? [];

            // Verifica se é um job de data única (otimização)
            $singleDate = $this->integration['single_date'] ?? null;

            if ($singleDate) {
                // Modo otimizado: uma única data
                $startDate = $singleDate;
                $endDate = $singleDate;
                Log::info('Job de data única - modo otimizado', [
                    'client' => $this->client->name,
                    'store' => $this->store->name,
                    'date' => $singleDate,
                ]);
            } else {
                // Modo range: intervalo de datas (data_inicial ate data_final)
                $startDate = $body['data_inicial'] ?? now()->subDay()->format('Y-m-d');
                $endDate = $body['data_final'] ?? now()->subDay()->format('Y-m-d');
                Log::info('Job de range de datas - modo intervalo', [
                    'client' => $this->client->name,
                    'store' => $this->store->name,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
            }

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

                return;
            }

            Log::info('Iniciando descoberta de vendas Visão', [
                'client' => $this->client->name,
                'store' => $this->store->name,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            // 2. ITERA DIA POR DIA COM RETRY LOGIC
            $currentDate = \Carbon\Carbon::parse($startDate);
            $endDateCarbon = \Carbon\Carbon::parse($endDate);
            $consecutiveFailures = 0;

            while ($currentDate->lte($endDateCarbon)) {
                $dateStr = $currentDate->format('Y-m-d');

                // Verifica se deve pular esse dia (max retries atingido)
                if ($retryService->shouldSkipDay($this->client, $this->store, $syncType, $dateStr)) {
                    Log::warning("Pulando dia {$dateStr} - Max retries atingido");
                    $retryService->recordAttempt(
                        $this->client,
                        $this->store,
                        $integrationType,
                        $syncType,
                        $dateStr,
                        'skipped'
                    );
                    $currentDate->addDay();

                    continue;
                }

                try {
                    // Tenta buscar vendas do dia
                    $result = $service->discoverDay(
                        type: $type,
                        dateField: 'data_venda',
                        date: $dateStr,
                        params: $body,
                        callback: function ($data, $date, $pageNumber) use ($type) {
                            $job = new ProcessIntegrationDataJob(
                                client: $this->client,
                                store: $this->store,
                                context: $type,
                                data: $data,
                                integration: $this->integration
                            );

                            // Se estiver em modo sequencial, processa sincronamente
                            if ($this->sequential) {
                                $job->handle();
                            } else {
                                ProcessIntegrationDataJob::dispatch(
                                    client: $this->client,
                                    store: $this->store,
                                    context: $type,
                                    data: $data,
                                    integration: $this->integration
                                );
                            }

                        }
                    );

                    // SUCESSO: Registra e zera contador de falhas consecutivas
                    $retryService->recordAttempt(
                        $this->client,
                        $this->store,
                        $integrationType,
                        $syncType,
                        $dateStr,
                        'success',
                        $result['total_items']
                    );

                    // Circuit Breaker: Registra sucesso (reseta contador)
                    IntegrationCircuitBreaker::recordSuccess(
                        $this->client->id,
                        $this->store->id,
                        $integrationType
                    );

                    $consecutiveFailures = 0;

                } catch (\Exception $e) {
                    // FALHA: Registra e incrementa contador
                    $retryService->recordAttempt(
                        $this->client,
                        $this->store,
                        $integrationType,
                        $syncType,
                        $dateStr,
                        'failed',
                        null,
                        $e->getMessage(),
                        ['trace' => $e->getTraceAsString()]
                    );

                    // Circuit Breaker: Registra falha
                    IntegrationCircuitBreaker::recordFailure(
                        $this->client->id,
                        $this->store->id,
                        $integrationType,
                        $e->getMessage()
                    );

                    $consecutiveFailures++;

                    Log::error("Falha no dia {$dateStr}", [
                        'error' => $e->getMessage(),
                        'consecutive_failures' => $consecutiveFailures,
                    ]);

                    // Se atingiu limite de falhas consecutivas, para tudo
                    if ($consecutiveFailures >= IntegrationSyncRetryService::MAX_CONSECUTIVE_FAILURES) {
                        Log::error('Limite de falhas consecutivas atingido. Parando sync.');

                        $this->client->notify(new IntegrationSyncFailureNotification(
                            $this->client,
                            $this->store,
                            $syncType,
                            'critical',
                            [],
                            $consecutiveFailures
                        ));

                        break;
                    }
                }

                $currentDate->addDay();
                usleep(500000); // 500ms entre dias
            }

            Log::info('Descoberta Visão concluída', [
                'client' => $this->client->name,
                'store' => $this->store->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro crítico no job de sync de vendas Visão: '.$e->getMessage(), [
                'client_id' => $this->client->name,
                'trace' => $e->getMessage(),
            ]);
        }
    }
}
