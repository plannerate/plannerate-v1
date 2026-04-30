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

class RunLayerProductIdSyncItemJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $tenantId,
        public string $tenantConnectionName,
        public string $layerId,
        public string $legacyProductId,
        public ?string $resolvedGondolaId = null,
        public bool $executeInTenantContext = true,
    ) {}

    public function handle(SyncLayerProductIdsFromLegacyService $syncLayerProductIdsFromLegacyService): void
    {
        if (
            $this->tenantId === ''
            || $this->tenantConnectionName === ''
            || $this->layerId === ''
            || $this->legacyProductId === ''
        ) {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function () use ($syncLayerProductIdsFromLegacyService): void {
            $result = $syncLayerProductIdsFromLegacyService->syncSingleInvalidLayer(
                tenantConnectionName: $this->tenantConnectionName,
                legacyConnectionName: 'mysql_legacy',
                tenantId: $this->tenantId,
                layerId: $this->layerId,
                legacyProductId: $this->legacyProductId,
                resolvedGondolaId: $this->resolvedGondolaId,
            );

            if ($result['updated'] || $result['unresolved_legacy'] || $result['unresolved_tenant']) {
                Log::info('Processamento de layer órfã concluído.', [
                    'tenant_id' => $this->tenantId,
                    'layer_id' => $this->layerId,
                    'legacy_product_id' => $this->legacyProductId,
                    'result' => $result,
                ]);
            }
        };

        // Fluxo temporário: sempre executa em contexto de tenant para evitar
        // jobs antigos enfileirados sem contexto correto.
        $tenant->execute($run);
    }
}
