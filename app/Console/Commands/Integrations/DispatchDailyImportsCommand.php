<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Imports\ImportIntegrationResourceJob;
use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:daily-imports {--no-finalize : Não dispara finalização em jobs que suportam runFinalize} {--type= : Filtra integrações por tipo, por exemplo products}')]
#[Description('Inicia a busca diária para os paths configurados nas integrações ativas')]
class DispatchDailyImportsCommand extends Command
{
    public function handle(ResolvedIntegrationConfigResolver $configResolver): int
    {
        $integrations = TenantIntegration::query()
            ->where('is_active', true)
            ->orderBy('tenant_id')
            ->get(['id', 'tenant_id', 'integration_type', 'config', 'is_active']);

        $type = $this->option('type');
        $resourceFilter = is_string($type) && $type !== '' ? $type : null;

        foreach ($integrations as $integration) {
            $resolvedConfig = $configResolver->resolve($integration);

            foreach (array_keys($resolvedConfig->resourceRequests()) as $resource) {
                $resource = (string) $resource;

                if ($resourceFilter !== null && $resource !== $resourceFilter) {
                    continue;
                }

                if (! $resolvedConfig->pathIsEnabled($resource)) {
                    continue;
                }

                dispatch(new ImportIntegrationResourceJob(
                    integrationId: (string) $integration->id,
                    resource: $resource,
                    targetTable: $resolvedConfig->targetTable($resource),
                    runFinalize: ! (bool) $this->option('no-finalize'),
                ));
            }
        }

        return self::SUCCESS;
    }
}
