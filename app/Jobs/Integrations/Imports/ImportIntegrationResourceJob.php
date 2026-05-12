<?php

namespace App\Jobs\Integrations\Imports;

use App\Jobs\Integrations\Maintenance\FinalizeTenantImportsJob;
use App\Models\TenantIntegration;
use App\Services\Integrations\Importers\IntegrationImporter;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
            return;
        }

        $resolvedConfig = $configResolver->resolve($integration);
       

        $integrationImporter->importResource($resolvedConfig, $this->resource, $this->targetTable);

        $request = $resolvedConfig->request($this->resource);
        if ($this->runFinalize && (bool) ($request['run_finalize'] ?? false)) {
            FinalizeTenantImportsJob::dispatch((string) $integration->tenant_id)->delay(now()->addMinutes(2));
        }
    }
}
