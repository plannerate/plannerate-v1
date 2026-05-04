<?php

namespace App\Jobs\Integrations\Support;

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncLayerProductIdsFromLegacyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class RunTenantLayerProductIdsSyncJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(
        public string $tenantId,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
        public bool $preview = false,
        public bool $useEanFastPath = true,
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
            if ($this->useEanFastPath) {
                $summary = $syncLayerProductIdsFromLegacyService->syncFromEan(
                    tenantConnectionName: $this->tenantConnectionName,
                    tenantId: $this->tenantId,
                    preview: $this->preview,
                );

                Log::info('Sincronização rápida (ean) de product_id em layers concluída.', [
                    'tenant_id' => $this->tenantId,
                    'tenant_connection' => $this->tenantConnectionName,
                    'preview' => $this->preview,
                    'summary' => $summary,
                ]);

                return;
            }

            $summary = $syncLayerProductIdsFromLegacyService->sync(
                tenantConnectionName: $this->tenantConnectionName,
                legacyConnectionName: 'mysql_legacy',
                tenantId: $this->tenantId,
                preview: $this->preview,
            );

            Log::info('Sincronização de product_id em layers concluída.', [
                'tenant_id' => $this->tenantId,
                'tenant_connection' => $this->tenantConnectionName,
                'preview' => $this->preview,
                'summary' => $summary,
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
