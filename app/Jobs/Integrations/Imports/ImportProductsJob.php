<?php

namespace App\Jobs\Integrations\Imports;

use App\Models\TenantIntegration;
use App\Services\Integrations\Importers\IntegrationImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ImportProductsJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public string $integrationId,
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
            Log::warning('Importação de produtos ignorada: integração ativa não encontrada.', [
                'integration_id' => $this->integrationId,
            ]);

            return;
        }

        $integrationImporter->importProducts($integration);
    }
}
