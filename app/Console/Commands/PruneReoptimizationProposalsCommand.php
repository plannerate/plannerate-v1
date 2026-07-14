<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\ProposalPruner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Expira propostas de reotimização abandonadas e descarta os snapshots já inúteis.
 *
 * Expirar não é faxina: o agendador não analisa gôndola com proposta pendente, então uma proposta
 * que ninguém decide trava a reotimização daquela gôndola indefinidamente.
 */
class PruneReoptimizationProposalsCommand extends Command
{
    protected $signature = 'planograms:prune-reoptimization-proposals
                            {--tenant= : ID do tenant específico}';

    protected $description = 'Expira propostas de reotimização abandonadas e descarta snapshots de propostas já decididas';

    public function handle(ProposalPruner $pruner): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $pruner);
        }

        $this->newLine();
        $this->info('✅ Manutenção das propostas concluída.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    protected function getTenants(): Collection
    {
        $query = Tenant::query()->where('status', 'active');

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        return $query->get(['id', 'name', 'database']);
    }

    protected function processTenant(Tenant $tenant, ProposalPruner $pruner): void
    {
        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $run = fn (): array => $pruner->prune();

        $result = $shouldSwitchTenantContext
            ? $tenant->execute($run)
            : $run();

        if ($result['expired'] > 0 || $result['snapshots_discarded'] > 0) {
            $this->info(sprintf(
                '   ✓ [%s] %d proposta(s) expirada(s), %d snapshot(s) descartado(s).',
                $tenant->name,
                $result['expired'],
                $result['snapshots_discarded'],
            ));
        }
    }
}
