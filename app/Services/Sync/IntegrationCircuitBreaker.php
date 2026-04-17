<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Cache;

/**
 * Circuit Breaker para detectar integrações problemáticas
 * e evitar tentativas desnecessárias
 */
class IntegrationCircuitBreaker
{
    /** Número de falhas consecutivas antes de abrir o circuito */
    private const FAILURE_THRESHOLD = 3;

    /** Tempo (segundos) que o circuito fica aberto antes de tentar novamente */
    private const RESET_TIMEOUT = 3600; // 1 hora

    /** Prefixo das chaves no cache */
    private const CACHE_PREFIX = 'circuit_breaker:';

    /**
     * Verifica se o circuito está aberto (bloqueado) para uma integração
     */
    public static function isOpen(string $clientId, string $storeId, string $integrationType): bool
    {
        $key = self::buildKey($clientId, $storeId, $integrationType);
        $state = Cache::get($key);

        if (! $state) {
            return false;
        }

        // Se está aberto e passou o timeout, reseta
        if ($state['status'] === 'open' && now()->greaterThan($state['open_until'])) {
            self::halfOpen($clientId, $storeId, $integrationType);

            return false;
        }

        return $state['status'] === 'open';
    }

    /**
     * Registra uma falha na integração
     */
    public static function recordFailure(
        string $clientId,
        string $storeId,
        string $integrationType,
        string $errorMessage = ''
    ): void {
        $key = self::buildKey($clientId, $storeId, $integrationType);
        $state = Cache::get($key, [
            'status' => 'closed',
            'failures' => 0,
            'last_error' => null,
            'first_failure_at' => null,
        ]);

        \Log::info('Circuit Breaker: Falha registrada', [
            'key' => $key,
            'current_failures' => $state['failures'],
            'new_failures' => $state['failures'] + 1,
            'threshold' => self::FAILURE_THRESHOLD,
        ]);

        $state['failures']++;
        $state['last_error'] = $errorMessage;
        $state['last_failure_at'] = now();

        if ($state['failures'] === 1) {
            $state['first_failure_at'] = now();
        }

        // Abre o circuito se atingiu o threshold
        if ($state['failures'] >= self::FAILURE_THRESHOLD) {
            $state['status'] = 'open';
            $state['open_until'] = now()->addSeconds(self::RESET_TIMEOUT);
            $state['opened_at'] = now();

            \Log::warning('Circuit Breaker ABERTO - Integração bloqueada temporariamente', [
                'client_id' => $clientId,
                'store_id' => $storeId,
                'integration_type' => $integrationType,
                'failures' => $state['failures'],
                'blocked_until' => $state['open_until']->format('Y-m-d H:i:s'),
                'last_error' => $errorMessage,
            ]);
        }

        Cache::put($key, $state, now()->addHours(24));
    }

    /**
     * Registra um sucesso na integração (reseta contador)
     */
    public static function recordSuccess(string $clientId, string $storeId, string $integrationType): void
    {
        $key = self::buildKey($clientId, $storeId, $integrationType);
        $state = Cache::get($key);

        if ($state && $state['status'] === 'half-open') {
            \Log::info('Circuit Breaker FECHADO - Integração recuperada', [
                'client_id' => $clientId,
                'store_id' => $storeId,
                'integration_type' => $integrationType,
            ]);
        }

        Cache::forget($key);
    }

    /**
     * Coloca o circuito em estado half-open (permite 1 tentativa de teste)
     */
    protected static function halfOpen(string $clientId, string $storeId, string $integrationType): void
    {
        $key = self::buildKey($clientId, $storeId, $integrationType);
        $state = Cache::get($key);

        if ($state) {
            $state['status'] = 'half-open';
            $state['failures'] = 0;
            Cache::put($key, $state, now()->addHours(24));

            \Log::info('Circuit Breaker HALF-OPEN - Permitindo tentativa de teste', [
                'client_id' => $clientId,
                'store_id' => $storeId,
                'integration_type' => $integrationType,
            ]);
        }
    }

    /**
     * Força o reset do circuit breaker para uma integração
     */
    public static function reset(string $clientId, string $storeId, string $integrationType): void
    {
        $key = self::buildKey($clientId, $storeId, $integrationType);
        Cache::forget($key);

        \Log::info('Circuit Breaker RESETADO manualmente', [
            'client_id' => $clientId,
            'store_id' => $storeId,
            'integration_type' => $integrationType,
        ]);
    }

    /**
     * Obtém o estado atual do circuito
     */
    public static function getState(string $clientId, string $storeId, string $integrationType): ?array
    {
        $key = self::buildKey($clientId, $storeId, $integrationType);

        return Cache::get($key);
    }

    /**
     * Lista todas as integrações com circuito aberto
     */
    public static function listOpenCircuits(): array
    {
        // Esta função requer extensão do Laravel Cache para list keys
        // Por simplicidade, retornamos array vazio
        // Em produção, usar Redis SCAN ou database tracking
        return [];
    }

    /**
     * Monta a chave única para cache
     */
    protected static function buildKey(string $clientId, string $storeId, string $integrationType): string
    {
        $key = self::CACHE_PREFIX."{$clientId}:{$storeId}:{$integrationType}";
        
        \Log::debug('Circuit Breaker: Chave gerada', [
            'key' => $key,
            'client_id' => $clientId,
            'store_id' => $storeId,
            'integration_type' => $integrationType,
        ]);
        
        return $key;
    }
}
