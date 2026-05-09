<?php

namespace App\Jobs\Integrations\Imports;

use App\Models\TenantIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ImportSalesJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public string $integrationId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration instanceof TenantIntegration) {
            Log::warning('Importação de vendas ignorada: integração ativa não encontrada.', [
                'integration_id' => $this->integrationId,
            ]);

            return;
        }

        Log::info('Importação de vendas enfileirada para implementação.', [
            'integration_id' => $this->integrationId,
            'tenant_id' => (string) $integration->tenant_id,
            'provider' => (string) $integration->integration_type,
        ]);
    }
}
