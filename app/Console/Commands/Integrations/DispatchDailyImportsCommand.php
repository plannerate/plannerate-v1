<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Imports\ImportIntegrationResourceJob;
use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:daily-imports {--no-finalize : Não dispara finalização em jobs que suportam runFinalize}')]
#[Description('Inicia a busca diária para os paths configurados nas integrações ativas')]
class DispatchDailyImportsCommand extends Command
{
    public function handle(ResolvedIntegrationConfigResolver $configResolver): int
    {
        $integrations = TenantIntegration::query()
            ->where('is_active', true)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('integration_apis')
                    ->whereColumn('integration_apis.slug', 'tenant_integrations.integration_type')
                    ->where('integration_apis.is_active', true)
                    ->whereNull('integration_apis.deleted_at');
            })
            ->orderBy('tenant_id')
            ->get(['id', 'tenant_id', 'integration_type', 'config', 'is_active']);

        foreach ($integrations as $integration) {
            $resolvedConfig = $configResolver->resolve($integration);

            foreach (array_keys($resolvedConfig->resourceRequests()) as $resource) {
                $resource = (string) $resource;

                if (! $resolvedConfig->pathIsEnabled($resource)) {
                    continue;
                }

                dispatch(new ImportIntegrationResourceJob(
                    integrationId: (string) $integration->id,
                    resource: $resource,
                    runFinalize: ! (bool) $this->option('no-finalize'),
                ));
            }
        }

        return self::SUCCESS;
    }
}
