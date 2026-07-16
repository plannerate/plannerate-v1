<?php

namespace App\Services\Integrations\Support;

use Illuminate\Support\Facades\Queue;

/**
 * Consulta o backlog das filas do pipeline de importação.
 *
 * Barreira import → pós-import: o sync:post-import e o sync:cleanup só devem
 * agir quando não há mais páginas sendo buscadas (imports-fetch) ou
 * persistidas (imports-process); caso contrário operam sobre dados parciais.
 */
class ImportQueueMonitor
{
    /** @var array<int, string> */
    public const IMPORT_QUEUES = ['imports-fetch', 'imports-process'];

    /**
     * Jobs pendentes por fila. No driver Redis o size() inclui também os
     * jobs delayed (fetch espaçado por fetch_delay) e reserved.
     *
     * @return array<string, int>
     */
    public static function pendingJobsByQueue(): array
    {
        $pending = [];

        foreach (self::IMPORT_QUEUES as $queue) {
            $pending[$queue] = Queue::size($queue);
        }

        return $pending;
    }

    public static function totalPendingJobs(): int
    {
        return array_sum(self::pendingJobsByQueue());
    }
}
