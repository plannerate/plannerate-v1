<?php

namespace App\Jobs\Integrations\Support;

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncLayerProductIdsFromLegacyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class RunTenantLayerProductIdsSyncJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1200;

    public function __construct(
        public string $tenantId,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
        public bool $preview = false,
    ) {}

    public function handle(SyncLayerProductIdsFromLegacyService $syncLayerProductIdsFromLegacyService): void
    {
        if ($this->tenantId === '' || $this->tenantConnectionName === '') {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function () use ($syncLayerProductIdsFromLegacyService): void {
            if ($this->preview) {
                $summary = $syncLayerProductIdsFromLegacyService->sync(
                    tenantConnectionName: $this->tenantConnectionName,
                    legacyConnectionName: 'mysql_legacy',
                    tenantId: $this->tenantId,
                    preview: true,
                );

                Log::info('Preview de sincronização de product_id em layers concluído.', [
                    'tenant_id' => $this->tenantId,
                    'tenant_connection' => $this->tenantConnectionName,
                    'summary' => $summary,
                ]);

                return;
            }

            $restoredProducts = $syncLayerProductIdsFromLegacyService->restoreSoftDeletedProductsReferencedByLayers(
                tenantConnectionName: $this->tenantConnectionName,
                tenantId: $this->tenantId,
            );

            $dispatchedItems = 0;

            DB::connection($this->tenantConnectionName)
                ->table('layers as l')
                ->leftJoin('products as p', function ($join): void {
                    $join->on('p.id', '=', 'l.product_id')
                        ->where('p.tenant_id', '=', $this->tenantId)
                        ->whereNull('p.deleted_at');
                })
                ->leftJoin('segments as sg', 'sg.id', '=', 'l.segment_id')
                ->leftJoin('shelves as sh', 'sh.id', '=', 'sg.shelf_id')
                ->leftJoin('sections as sc', 'sc.id', '=', 'sh.section_id')
                ->where('l.tenant_id', $this->tenantId)
                ->whereNotNull('l.product_id')
                ->whereNull('l.deleted_at')
                ->whereNull('p.id')
                ->orderBy('l.id')
                ->select([
                    'l.id',
                    'l.product_id',
                    DB::raw('COALESCE(l.gondola_id, sc.gondola_id) as resolved_gondola_id'),
                ])
                ->chunk(500, function ($rows) use (&$dispatchedItems): void {
                    foreach ($rows as $row) {
                        RunLayerProductIdSyncItemJob::dispatch(
                            tenantId: $this->tenantId,
                            tenantConnectionName: $this->tenantConnectionName,
                            layerId: (string) $row->id,
                            legacyProductId: (string) $row->product_id,
                            resolvedGondolaId: is_string($row->resolved_gondola_id) ? $row->resolved_gondola_id : null,
                            executeInTenantContext: true,
                        );

                        $dispatchedItems++;
                    }
                });

            Log::info('Sincronização de product_id em layers enfileirada por item.', [
                'tenant_id' => $this->tenantId,
                'tenant_connection' => $this->tenantConnectionName,
                'restored_products' => $restoredProducts,
                'dispatched_items' => $dispatchedItems,
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
            'integrations',
            'layers-sync',
            "tenant:{$this->tenantId}",
        ];
    }
}
