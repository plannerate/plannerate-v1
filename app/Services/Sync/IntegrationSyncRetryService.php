<?php

namespace App\Services\Sync;

use App\Models\Client;
use App\Models\IntegrationSyncLog;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IntegrationSyncRetryService
{
    const MAX_RETRIES_PER_DAY = 5;

    const MAX_CONSECUTIVE_FAILURES = 10;

    const MAX_SKIPPED_DAYS = 3;

    /**
     * Registra tentativa de sync para um dia
     */
    public function recordAttempt(
        Client $client,
        Store $store,
        string $integrationType,
        string $syncType, // sales, products, purchases
        string $date,
        string $status,
        ?int $totalItems = null,
        ?string $errorMessage = null,
        ?array $errorDetails = null
    ): IntegrationSyncLog {
        $log = IntegrationSyncLog::firstOrNew([
            'client_id' => $client->id,
            'store_id' => $store->id,
            'sync_type' => $syncType,
            'sync_date' => $date,
        ]);

        if ($status === 'failed') {
            $log->retry_count++;
            $log->error_message = $errorMessage;
            $log->error_details = $errorDetails;
        } elseif ($status === 'success') {
            $log->retry_count = 0; // Zera retry count no sucesso
        }

        $log->fill([
            'integration_type' => $integrationType,
            'status' => $status,
            'total_items' => $totalItems,
            'last_attempt_at' => now(),
        ]);

        $log->save();

        return $log;
    }

    /**
     * Verifica se deve continuar sincronizando ou parar
     */
    public function shouldContinue(Client $client, Store $store, string $syncType): array
    {
        // Busca falhas consecutivas recentes (últimas 24h)
        $recentLogs = IntegrationSyncLog::where('client_id', $client->id)
            ->where('store_id', $store->id)
            ->where('sync_type', $syncType)
            ->where('last_attempt_at', '>=', now()->subHours(24))
            ->orderBy('sync_date', 'desc')
            ->get();

        // Conta falhas consecutivas do fim para o início
        $consecutiveFailures = 0;
        foreach ($recentLogs as $log) {
            if ($log->status === 'failed') {
                $consecutiveFailures++;
            } elseif ($log->status === 'success') {
                break; // Para ao encontrar um sucesso
            }
        }

        $shouldStop = $consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES;

        return [
            'should_continue' => ! $shouldStop,
            'consecutive_failures' => $consecutiveFailures,
            'message' => $shouldStop
                ? "Muitas falhas consecutivas ({$consecutiveFailures}) em {$syncType}. Parando sync."
                : "Continuando sync de {$syncType}. Falhas consecutivas: {$consecutiveFailures}",
        ];
    }

    /**
     * Busca dias que precisam de retry
     */
    public function getDaysNeedingRetry(Client $client, Store $store, string $syncType): Collection
    {
        return IntegrationSyncLog::where('client_id', $client->id)
            ->where('store_id', $store->id)
            ->where('sync_type', $syncType)
            ->where('status', 'failed')
            ->where('retry_count', '<', self::MAX_RETRIES_PER_DAY)
            ->orderBy('sync_date')
            ->get();
    }

    /**
     * Busca dias que devem ser pulados
     */
    public function getDaysToSkip(Client $client, Store $store, string $syncType): Collection
    {
        return IntegrationSyncLog::where('client_id', $client->id)
            ->where('store_id', $store->id)
            ->where('sync_type', $syncType)
            ->where('status', 'failed')
            ->where('retry_count', '>=', self::MAX_RETRIES_PER_DAY)
            ->orderBy('sync_date')
            ->get();
    }

    /**
     * Verifica se um dia específico deve ser pulado
     */
    public function shouldSkipDay(Client $client, Store $store, string $syncType, string $date): bool
    {
        $log = IntegrationSyncLog::where([
            'client_id' => $client->id,
            'store_id' => $store->id,
            'sync_type' => $syncType,
            'sync_date' => $date,
        ])->first();

        return $log && $log->shouldSkip();
    }

    /**
     * Busca estatísticas de sync para um cliente/loja
     */
    public function getSyncStats(Client $client, Store $store, string $syncType): array
    {
        $logs = IntegrationSyncLog::where('client_id', $client->id)
            ->where('store_id', $store->id)
            ->where('sync_type', $syncType)
            ->get();

        return [
            'total_days' => $logs->count(),
            'success' => $logs->where('status', 'success')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'skipped' => $logs->where('status', 'skipped')->count(),
            'pending' => $logs->where('status', 'pending')->count(),
            'total_items' => $logs->sum('total_items'),
        ];
    }
}
