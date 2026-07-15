<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\DiscoverIntegrationPagesJob;
use App\Models\TenantIntegration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Busca o catálogo completo de uma integração, ignorando o filtro incremental
 * (changed_since) que o integration:run aplica sempre que o tenant já tem registros.
 *
 * Uso pontual (backfill único), não é agendado — integration:run continua sendo
 * o sync incremental diário.
 */
class BackfillIntegrationCommand extends Command
{
    protected $signature = 'integration:backfill
        {--integration= : ID da TenantIntegration a rodar (padrão: todas as integrações ativas)}
        {--path= : Path key específico do requests.paths (ex: products); padrão roda todos os paths}';

    protected $description = 'Busca o catálogo completo de uma integração (sem changed_since) para um backfill único';

    public function handle(): int
    {
        $lock = RunIntegrationImportCommand::acquireDispatchLock();

        if ($lock === null) {
            $this->error('Outro despacho de descoberta (integration:run ou integration:backfill) está em andamento; tente novamente em alguns minutos.');

            return self::FAILURE;
        }

        try {
            return $this->dispatchBackfill();
        } finally {
            $lock->release();
        }
    }

    private function dispatchBackfill(): int
    {
        $integrations = $this->resolveIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integração ativa encontrada para os filtros informados.');

            return self::SUCCESS;
        }

        $onlyPath = $this->option('path');
        $dispatched = 0;

        foreach ($integrations as $integration) {
            $paths = data_get($integration->api->requests ?? [], 'paths', []);

            foreach ($paths as $pathKey => $pathConfig) {
                if ($onlyPath !== null && $pathKey !== $onlyPath) {
                    continue;
                }

                DiscoverIntegrationPagesJob::dispatch(
                    (string) $integration->id,
                    (string) $pathKey,
                    null,
                    null,
                    forceFull: true,
                );

                $dispatched++;

                $this->line(sprintf(
                    ' - %s (tenant %s) / %s: descoberta completa disparada',
                    $integration->api->name,
                    $integration->tenant_id,
                    $pathKey,
                ));
            }
        }

        $this->info(sprintf('Jobs de descoberta completa disparados: %d (queue: imports-fetch).', $dispatched));

        return self::SUCCESS;
    }

    /** @return Collection<int, TenantIntegration> */
    private function resolveIntegrations(): Collection
    {
        $query = TenantIntegration::query()
            ->with('api')
            ->where('is_active', true)
            ->whereHas('api', fn ($q) => $q->where('is_active', true));

        $integrationId = $this->option('integration');

        if ($integrationId !== null) {
            $query->whereKey($integrationId);
        }

        return $query->get();
    }
}
