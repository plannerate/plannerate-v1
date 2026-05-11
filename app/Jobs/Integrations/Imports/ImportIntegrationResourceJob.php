<?php

namespace App\Jobs\Integrations\Imports;

use App\Jobs\Integrations\Maintenance\FinalizeTenantImportsJob;
use App\Models\TenantIntegration;
use App\Services\Integrations\Importers\IntegrationImporter;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ImportIntegrationResourceJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public string $integrationId,
        public string $resource,
        public string $targetTable,
        public bool $runFinalize = true,
    ) {
        $this->onQueue('imports');
    }

    public function handle(
        IntegrationImporter $integrationImporter,
        ResolvedIntegrationConfigResolver $configResolver,
    ): void {
        $integration = TenantIntegration::query()
            ->with('tenant')
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration instanceof TenantIntegration) {
            Log::warning('Importação de recurso ignorada: integração ativa não encontrada.', [
                'integration_id' => $this->integrationId,
                'resource' => $this->resource,
                'target_table' => $this->targetTable,
            ]);

            return;
        }

        $resolvedConfig = $configResolver->resolve($integration);
        if (! $resolvedConfig->pathIsEnabled($this->resource)) {
            Log::info('Importação de recurso ignorada: path desativado.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'resource' => $this->resource,
                'target_table' => $this->targetTable,
            ]);

            return;
        }

        $integrationImporter->importResource($integration, $this->resource, $this->targetTable);

        $request = $resolvedConfig->request($this->resource);
        if ($this->runFinalize && (bool) ($request['run_finalize'] ?? false)) {
            FinalizeTenantImportsJob::dispatch((string) $integration->tenant_id)->delay(now()->addMinutes(2));
        }
    }
}
