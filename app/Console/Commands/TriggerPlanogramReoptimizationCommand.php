<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunTrigger;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\ReoptimizationScheduler;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Enfileira a análise de reotimização das gôndolas cuja cadência venceu.
 *
 * Roda diariamente, mas isso não significa uma proposta por dia: cada gôndola tem sua própria
 * cadência (`reoptimization_next_run_at`), e o comando só pega as vencidas. O agendador diário é
 * só a "batida do relógio" — quem decide o ritmo é a gôndola.
 *
 * Tenant-aware: percorre os tenants ativos e executa dentro do contexto de cada um, como o
 * TriggerPeriodicReviewCommand.
 */
class TriggerPlanogramReoptimizationCommand extends Command
{
    protected $signature = 'planograms:trigger-reoptimization
                            {--tenant= : ID do tenant específico}
                            {--gondola= : ID de uma gôndola específica (ignora a cadência)}
                            {--dry-run : Apenas lista o que seria enfileirado, sem escrever}';

    protected $description = 'Enfileira a análise de reotimização das gôndolas cuja cadência venceu';

    public function handle(ReoptimizationScheduler $scheduler): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('MODO DRY-RUN ativo: nada será enfileirado.');
        }

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $scheduler, $dryRun);
        }

        $this->newLine();
        $this->info('✅ Verificação de reotimização concluída.');

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

    protected function processTenant(Tenant $tenant, ReoptimizationScheduler $scheduler, bool $dryRun): void
    {
        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $run = fn (): int => $this->enqueueEligible($tenant, $scheduler, $dryRun);

        $enqueued = $shouldSwitchTenantContext
            ? $tenant->execute($run)
            : $run();

        if (! $dryRun && $enqueued > 0) {
            $this->info("   ✓ [{$tenant->name}] {$enqueued} gôndola(s) enfileirada(s) para reotimização.");
        }
    }

    protected function enqueueEligible(Tenant $tenant, ReoptimizationScheduler $scheduler, bool $dryRun): int
    {
        $gondolaId = $this->option('gondola');
        $gondolaId = is_string($gondolaId) && $gondolaId !== '' ? $gondolaId : null;

        $eligible = $scheduler->eligibleGondolas($gondolaId);

        if ($eligible->isEmpty()) {
            return 0;
        }

        if ($dryRun) {
            $this->line("   [{$tenant->name}] elegíveis:");
            $eligible->each(fn (Gondola $gondola) => $this->line("     • {$gondola->name} ({$gondola->id})"));

            return 0;
        }

        $enqueued = 0;

        foreach ($eligible as $gondola) {
            if ($scheduler->enqueue($gondola, GenerationRunTrigger::Scheduled) !== null) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
