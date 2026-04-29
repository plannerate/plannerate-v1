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

class CleanupOldSalesJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    /**
     * @param  array<int, string>  $saleIds
     */
    public function __construct(
        public string $tenantId,
        public array $saleIds,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
    ) {}

    public function handle(): void
    {
        if ($this->tenantId === '' || $this->saleIds === []) {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function (): void {
            $chunks = array_chunk($this->saleIds, 1000);
            $totalDeleted = 0;

            foreach ($chunks as $chunk) {
                $totalDeleted += DB::connection($this->tenantConnectionName)
                    ->table('sales')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $chunk)
                    ->delete();
            }

            Log::info('Limpeza de vendas antigas concluída', [
                'tenant_id' => $this->tenantId,
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
