<?php

namespace App\Jobs\Integrations\Imports;

use App\Jobs\Integrations\Maintenance\FinalizeTenantImportsJob;
use App\Models\TenantIntegration;
use App\Services\Integrations\Importers\IntegrationImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ImportSalesJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public string $integrationId,
        public bool $runFinalize = true,
    ) {
        $this->onQueue('imports');
    }

    public function handle(IntegrationImporter $integrationImporter): void
    {
        $integration = TenantIntegration::query()
            ->with('tenant')
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration instanceof TenantIntegration) {
            Log::warning('Importação de vendas ignorada: integração ativa não encontrada.', [
                'integration_id' => $this->integrationId,
            ]);

            return;
        }

        if (! $this->tenantHasProducts($integration)) {
            Log::info('Importação de vendas ignorada: tenant sem produtos para vinculação.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
            ]);

            return;
        }

        $integrationImporter->importSales($integration);

        if ($this->runFinalize) {
            FinalizeTenantImportsJob::dispatch((string) $integration->tenant_id)->delay(now()->addMinutes(2));
        }
    }

    private function tenantHasProducts(TenantIntegration $integration): bool
    {
        $tenant = $integration->tenant;
        if ($tenant === null) {
            return false;
        }

        return $tenant->execute(function () use ($tenant): bool {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

            return DB::connection($connection)
                ->table('products')
                ->where('tenant_id', (string) $tenant->id)
                ->whereNull('deleted_at')
                ->exists();
        });
    }
}
