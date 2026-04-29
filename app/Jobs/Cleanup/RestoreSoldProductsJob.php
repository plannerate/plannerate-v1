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

class RestoreSoldProductsJob implements NotTenantAware, ShouldQueue
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
            $totalRestored = 0;

            foreach ($chunks as $chunk) {
                $totalRestored += DB::connection($this->tenantConnectionName)
                    ->table('products')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $chunk)
                    ->whereNotNull('deleted_at')
                    ->update([
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            Log::info('Restauração de produtos com vendas concluída', [
                'tenant_id' => $this->tenantId,
                'total_restored' => $totalRestored,
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
            'restore-products',
            "tenant:{$this->tenantId}",
        ];
    }
}
