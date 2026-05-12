<?php

namespace App\Console\Commands\Integrations;

use App\Events\Tenant\IntegrationProcessFinished;
use App\Events\Tenant\IntegrationProcessStarted;
use App\Jobs\Integrations\DiscoverIntegrationPagesJob;
use App\Models\TenantIntegration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RunIntegrationImportCommand extends Command
{
    protected $signature = 'integration:run';

    protected $description = 'Executa importação de dados via integrações ativas';

    public function handle(): int
    {
        $integrations = $this->getActiveIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integração ativa encontrada.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Integrações ativas: %d', $integrations->count()));

        $totalJobs = 0;
        $referenceDate = now()->toDateString();

        foreach ($integrations as $integration) {
            $tenantId = (string) $integration->tenant_id;
            $integrationId = (string) $integration->id;

            event(new IntegrationProcessStarted(
                tenantId: $tenantId,
                integrationId: $integrationId,
                resource: 'integration_import',
                referenceDate: $referenceDate,
            ));

            try {
                $totalJobs += $this->processIntegration($integration);

                event(new IntegrationProcessFinished(
                    tenantId: $tenantId,
                    integrationId: $integrationId,
                    resource: 'integration_import',
                    referenceDate: $referenceDate,
                    status: 'success',
                ));
            } catch (Throwable $exception) {
                event(new IntegrationProcessFinished(
                    tenantId: $tenantId,
                    integrationId: $integrationId,
                    resource: 'integration_import',
                    referenceDate: $referenceDate,
                    status: 'failed',
                    errorMessage: $exception->getMessage(),
                ));

                Log::error('RunIntegrationImportCommand: falha ao processar integração', [
                    'integration_id' => $integrationId,
                    'tenant_id' => $tenantId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('Jobs de descoberta despachados: %d (queue: imports).', $totalJobs));

        return self::SUCCESS;
    }

    /** @return Collection<int, TenantIntegration> */
    private function getActiveIntegrations(): Collection
    {
        return TenantIntegration::query()
            ->with(['api', 'tenant'])
            ->where('is_active', true)
            ->whereHas('api', fn ($q) => $q->where('is_active', true))
            ->get();
    }

    private function processIntegration(TenantIntegration $integration): int
    {
        $api = $integration->api;
        $paths = data_get($api->requests ?? [], 'paths', []);
        $dispatched = 0;

        foreach ($paths as $pathKey => $pathConfig) {
            $dispatched += $this->dispatchPathJobs($integration, (string) $pathKey, (array) $pathConfig);
        }

        $this->line(sprintf(' - %s (%s): %d job(s)', $api->name, (string) $integration->tenant_id, $dispatched));

        return $dispatched;
    }

    /** @param array<string, mixed> $pathConfig */
    private function dispatchPathJobs(TenantIntegration $integration, string $pathKey, array $pathConfig): int
    {
        $dateFields = data_get($pathConfig, 'date_fields', []);
        $initialDays = (int) data_get($pathConfig, 'initial_days', 0);
        $targetTable = (string) data_get($pathConfig, 'target_table', '');
        $hasRecords = $this->tableHasRecords($integration, $targetTable);
        $hasDateRange = isset($dateFields['start']) && isset($dateFields['end']);

        if ($hasDateRange) {
            if ($hasRecords) {
                $yesterday = now()->subDay()->toDateString();
                DiscoverIntegrationPagesJob::dispatch($integration->id, $pathKey, $yesterday, now()->toDateString());
            } else {
                $start = $initialDays > 0 ? now()->subDays($initialDays)->toDateString() : null;
                DiscoverIntegrationPagesJob::dispatch($integration->id, $pathKey, $start, now()->toDateString());
            }

            return 1;
        }

        $changedSince = $this->resolveChangedSince($pathConfig, $hasRecords);

        DiscoverIntegrationPagesJob::dispatch($integration->id, $pathKey, $changedSince, null);

        return 1;
    }

    /**
     * - tabela com registros → ontem
     * - tabela vazia + initial_days > 0 → now() - N dias
     * - tabela vazia + initial_days = 0 → null (sem filtro)
     *
     * @param  array<string, mixed>  $pathConfig
     */
    private function resolveChangedSince(array $pathConfig, bool $hasRecords): ?string
    {
        if (! isset(data_get($pathConfig, 'date_fields', [])['changed_since'])) {
            return null;
        }

        if ($hasRecords) {
            return now()->subDay()->toDateString();
        }

        $initialDays = (int) data_get($pathConfig, 'initial_days', 0);

        return $initialDays > 0 ? now()->subDays($initialDays)->toDateString() : null;
    }

    private function tableHasRecords(TenantIntegration $integration, string $targetTable): bool
    {
        if ($targetTable === '' || $integration->tenant === null) {
            return false;
        }

        return (bool) $integration->tenant->execute(function () use ($targetTable): bool {
            if (! Schema::connection('tenant')->hasTable($targetTable)) {
                return false;
            }

            return DB::connection('tenant')->table($targetTable)->exists();
        });
    }
}
