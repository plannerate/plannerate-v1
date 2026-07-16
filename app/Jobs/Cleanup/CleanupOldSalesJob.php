<?php

namespace App\Jobs\Cleanup;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Apaga vendas anteriores à data de corte. Recebe o CRITÉRIO (cutoff), não a
 * lista de ids: o job re-deriva as linhas em chunks — o payload da fila fica
 * pequeno e a seleção reflete o estado do banco no momento da execução.
 */
class CleanupOldSalesJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 1000;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public string $tenantId,
        public string $cutoffDate,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        if ($this->tenantId === '' || $this->cutoffDate === '') {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function (): void {
            $totalDeleted = 0;

            do {
                $ids = DB::connection($this->tenantConnectionName)
                    ->table('sales')
                    ->where('tenant_id', $this->tenantId)
                    ->where('sale_date', '<', $this->cutoffDate)
                    ->limit(self::CHUNK_SIZE)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $totalDeleted += DB::connection($this->tenantConnectionName)
                    ->table('sales')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $ids->all())
                    ->delete();
            } while (true);

            Log::info('Limpeza de vendas antigas concluída', [
                'tenant_id' => $this->tenantId,
                'cutoff_date' => $this->cutoffDate,
                'total_deleted' => $totalDeleted,
            ]);
        };

        if ($this->executeInTenantContext) {
            $tenant->execute($run);

            return;
        }

        $run();
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'cleanup',
            'old-sales',
            "tenant:{$this->tenantId}",
        ];
    }
}
