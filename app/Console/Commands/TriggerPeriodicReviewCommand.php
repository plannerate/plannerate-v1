<?php

namespace App\Console\Commands;

use App\Models\Planogram;
use App\Models\Tenant;
use App\Services\PeriodicReviewService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Promove planogramas concluídos para Revisão Periódica quando vence o período
 * de análise. Tenant-aware: percorre os tenants ativos e, em cada contexto,
 * promove os planogramas elegíveis (idempotente via `periodic_review_started_at`).
 */
class TriggerPeriodicReviewCommand extends Command
{
    protected $signature = 'planograms:trigger-periodic-review
                            {--tenant= : ID do tenant específico}
                            {--dry-run : Apenas lista o que seria promovido, sem escrever}';

    protected $description = 'Promove planogramas concluídos para Revisão Periódica quando vence o período de análise';

    public function handle(PeriodicReviewService $service): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('MODO DRY-RUN ativo: nenhuma alteração será aplicada.');
        }

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $service, $dryRun);
        }

        $this->newLine();
        $this->info('✅ Verificação de revisão periódica concluída.');

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

    /**
     * Processa um tenant: resolve elegíveis e promove (ou lista, no dry-run).
     */
    protected function processTenant(Tenant $tenant, PeriodicReviewService $service, bool $dryRun): void
    {
        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $run = fn (): int => $this->promoteEligible($tenant, $service, $dryRun);

        $promoted = $shouldSwitchTenantContext
            ? $tenant->execute($run)
            : $run();

        if (! $dryRun && $promoted > 0) {
            $this->info("   ✓ [{$tenant->name}] {$promoted} planograma(s) promovido(s) para Revisão Periódica.");
        }
    }

    /**
     * Promove os planogramas elegíveis do tenant atual. Retorna a quantidade
     * efetivamente promovida (0 no dry-run).
     */
    protected function promoteEligible(Tenant $tenant, PeriodicReviewService $service, bool $dryRun): int
    {
        $eligible = $service->eligibleForPromotion();

        if ($eligible->isEmpty()) {
            return 0;
        }

        if ($dryRun) {
            /** @var Planogram $planogram */
            foreach ($eligible as $planogram) {
                $this->line("   • [{$tenant->name}] Promoveria: {$planogram->name} (vencimento {$planogram->periodic_review_due_at}).");
            }

            return 0;
        }

        $promoted = 0;
        /** @var Planogram $planogram */
        foreach ($eligible as $planogram) {
            if ($service->promote($planogram)) {
                $promoted++;
            }
        }

        return $promoted;
    }
}
