<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\DiscoverIntegrationPagesJob;
use App\Models\TenantIntegration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $this->info(sprintf('Encontradas %d integração(ões) ativa(s).', $integrations->count()));
        $this->newLine();

        foreach ($integrations as $integration) {
            $this->processIntegration($integration);
        }

        $this->newLine();
        $this->info('Jobs despachados na queue [imports].');

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

    private function processIntegration(TenantIntegration $integration): void
    {
        $api = $integration->api;
        $paths = data_get($api->requests ?? [], 'paths', []);

        $this->info("Integração: {$api->name} | Tenant: {$integration->tenant_id}");

        foreach ($paths as $pathKey => $pathConfig) {
            $this->dispatchPathJobs($integration, (string) $pathKey, (array) $pathConfig);
        }

        $this->newLine();
    }

    /** @param array<string, mixed> $pathConfig */
    private function dispatchPathJobs(TenantIntegration $integration, string $pathKey, array $pathConfig): void
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
                $this->line(sprintf('   [%s] 1 job incremental [%s]', $pathKey, $yesterday));
            } else {
                $start = $initialDays > 0 ? now()->subDays($initialDays)->toDateString() : null;
                DiscoverIntegrationPagesJob::dispatch($integration->id, $pathKey, $start, now()->toDateString());
                $label = $start ?? 'sem limite de data';
                $this->line(sprintf('   [%s] 1 job setup [%s]', $pathKey, $label));
            }

            return;
        }

        $changedSince = $this->resolveChangedSince($pathConfig, $hasRecords);

        DiscoverIntegrationPagesJob::dispatch($integration->id, $pathKey, $changedSince, null);

        $label = $changedSince !== null ? "desde {$changedSince}" : 'completo (sem filtro de data)';
        $this->line(sprintf('   [%s] 1 job [%s]', $pathKey, $label));
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
