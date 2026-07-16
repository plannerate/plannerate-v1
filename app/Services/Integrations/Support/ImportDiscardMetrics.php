<?php

namespace App\Services\Integrations\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Contadores persistidos (cache) de registros descartados no mapping do import.
 *
 * Antes disso os descartes eram só um Log::warning por página — em escala,
 * produtos/vendas podiam sumir do sync sem nenhum alarme. Os contadores são
 * acumulados por integração/path/dia (TTL de 7 dias) e uma página com taxa de
 * descarte acima de ALERT_RATE vira Log::error (canal monitorado).
 */
class ImportDiscardMetrics
{
    private const TTL_DAYS = 7;

    /** Página com mais da metade dos itens descartados = alarme. */
    private const ALERT_RATE = 0.5;

    /** Campo sintético para rejeições por validação de grupo (sem campo culpado). */
    public const GROUP_VALIDATION_FIELD = '_group_validation';

    /**
     * @param  array<string, int>  $skippedByField
     */
    public static function record(
        string $integrationId,
        string $pathKey,
        ?string $storeId,
        int $mapped,
        int $skipped,
        array $skippedByField = [],
    ): void {
        if ($skipped <= 0) {
            return;
        }

        self::increment(self::key($integrationId, $pathKey, 'total'), $skipped);

        foreach ($skippedByField as $field => $count) {
            self::increment(self::key($integrationId, $pathKey, "field:{$field}"), $count);
        }

        $totalItems = $mapped + $skipped;

        if ($totalItems > 0 && ($skipped / $totalItems) > self::ALERT_RATE) {
            Log::error('ImportDiscardMetrics: taxa de descarte alta na página', [
                'integration_id' => $integrationId,
                'path_key' => $pathKey,
                'store_id' => $storeId,
                'mapped' => $mapped,
                'skipped' => $skipped,
                'skipped_by_field' => $skippedByField,
            ]);
        }
    }

    /** Total descartado hoje para integração+path (p/ diagnóstico e testes). */
    public static function totalForToday(string $integrationId, string $pathKey): int
    {
        return (int) Cache::get(self::key($integrationId, $pathKey, 'total'), 0);
    }

    private static function key(string $integrationId, string $pathKey, string $suffix): string
    {
        return sprintf(
            'integrations:discards:%s:%s:%s:%s',
            $integrationId,
            $pathKey,
            now()->toDateString(),
            $suffix,
        );
    }

    private static function increment(string $key, int $by): void
    {
        // add() garante o TTL na criação; increment() não define expiração.
        Cache::add($key, 0, now()->addDays(self::TTL_DAYS));
        Cache::increment($key, $by);
    }
}
