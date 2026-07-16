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
 * Apaga vendas cujo product_id não existe mais em products. Recebe o CRITÉRIO,
 * não a lista de ids: o job re-deriva as linhas em chunks — payload pequeno e
 * seleção refletindo o estado do banco na execução (não no despacho).
 */
class CleanupOrphanSalesJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 500;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public string $tenantId,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        if ($this->tenantId === '') {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function (): void {
            // Mesmo guard do comando, re-checado na execução: com products vazio
            // (import ainda não rodou), TODA venda pareceria órfã.
            $hasProducts = DB::connection($this->tenantConnectionName)
                ->table('products')
                ->where('tenant_id', $this->tenantId)
                ->exists();

            if (! $hasProducts) {
                Log::warning('CleanupOrphanSalesJob: nenhum produto no tenant; deleção ignorada por segurança', [
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            $totalDeleted = 0;

            do {
                $ids = DB::connection($this->tenantConnectionName)
                    ->table('sales')
                    ->where('tenant_id', $this->tenantId)
                    ->whereNotNull('product_id')
                    ->whereNotExists(function ($query): void {
                        $query->select(DB::raw(1))
                            ->from('products')
                            ->whereColumn('products.id', 'sales.product_id')
                            ->whereColumn('products.tenant_id', 'sales.tenant_id');
                    })
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

            Log::info('Limpeza de vendas órfãs concluída', [
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
            'orphan-sales',
            "tenant:{$this->tenantId}",
        ];
    }
}
