<?php

namespace App\Jobs\Cleanup;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Desativa (soft-delete) produtos sem venda desde a data de corte e sem layer
 * ativa. Recebe o CRITÉRIO (cutoff), não a lista de ids: o job re-deriva as
 * linhas em chunks — payload pequeno e as condições são re-avaliadas na
 * execução (uma venda importada entre o despacho e a execução salva o produto).
 */
class DeactivateInactiveProductsJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 500;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public string $tenantId,
        public string $salesCutoffDate,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        if ($this->tenantId === '' || $this->salesCutoffDate === '') {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function (): void {
            // Mesmo guard do comando, re-checado na execução: sem NENHUMA venda,
            // todo produto pareceria inativo.
            $hasSales = DB::connection($this->tenantConnectionName)
                ->table('sales')
                ->where('tenant_id', $this->tenantId)
                ->exists();

            if (! $hasSales) {
                Log::warning('DeactivateInactiveProductsJob: nenhuma venda no tenant; desativação ignorada por segurança', [
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            $totalDeactivated = 0;

            do {
                $ids = $this->inactiveProductsQuery()
                    ->limit(self::CHUNK_SIZE)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $totalDeactivated += DB::connection($this->tenantConnectionName)
                    ->table('products')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $ids->all())
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
            } while (true);

            Log::info('Desativação de produtos inativos concluída', [
                'tenant_id' => $this->tenantId,
                'sales_cutoff_date' => $this->salesCutoffDate,
                'total_deactivated' => $totalDeactivated,
            ]);
        };

        if ($this->executeInTenantContext) {
            $tenant->execute($run);

            return;
        }

        $run();
    }

    private function inactiveProductsQuery(): Builder
    {
        return DB::connection($this->tenantConnectionName)
            ->table('products')
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('sales')
                    ->whereColumn('sales.product_id', 'products.id')
                    ->whereColumn('sales.tenant_id', 'products.tenant_id')
                    ->where('sales.sale_date', '>=', $this->salesCutoffDate);
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('layers')
                    ->whereColumn('layers.product_id', 'products.id')
                    ->whereColumn('layers.tenant_id', 'products.tenant_id')
                    ->whereNull('layers.deleted_at');
            });
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
