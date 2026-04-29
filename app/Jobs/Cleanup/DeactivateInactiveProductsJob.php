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

class DeactivateInactiveProductsJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  array<int, string>  $productIds
     */
    public function __construct(
        public string $tenantId,
        public array $productIds,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
    ) {}

    public function handle(): void
    {
        if ($this->tenantId === '' || $this->productIds === []) {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function (): void {
            $chunks = array_chunk($this->productIds, 500);
            $totalDeactivated = 0;

            foreach ($chunks as $chunk) {
                $totalDeactivated += DB::connection($this->tenantConnectionName)
                    ->table('products')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $chunk)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            Log::info('Desativação de produtos inativos concluída', [
                'tenant_id' => $this->tenantId,
                'total_deactivated' => $totalDeactivated,
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
            'inactive-products',
            "tenant:{$this->tenantId}",
        ];
    }
}
