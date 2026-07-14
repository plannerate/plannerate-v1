<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Reoptimization;

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunKind;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunTrigger;
use Callcocam\LaravelRaptorPlannerate\Jobs\GenerateReoptimizationProposalJob;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Decide QUAIS gôndolas reprocessar e enfileira a análise.
 *
 * Roda dentro do contexto de um tenant (o comando faz o switch); todas as consultas aqui já
 * estão na conexão do tenant corrente.
 */
final class ReoptimizationScheduler
{
    public function __construct(private readonly SalesWindowShifter $windowShifter) {}

    /**
     * Gôndolas prontas para reprocessar agora.
     *
     * `template_id` não nulo é requisito duro, não preferência: o dry-run só é seguro em modo
     * template. No modo automático o motor SINTETIZA o template no banco — uma "simulação"
     * deixaria rastro, e a promessa da proposta (nada muda até você aprovar) seria falsa.
     *
     * @return Collection<int, Gondola>
     */
    public function eligibleGondolas(?string $onlyGondolaId = null): Collection
    {
        $query = Gondola::query()
            ->where('reoptimization_enabled', true)
            ->whereNotNull('template_id')
            ->whereNotNull('reoptimization_frequency');

        if ($onlyGondolaId !== null) {
            // "Analisar agora" ignora a cadência (é o usuário pedindo), mas não os requisitos
            // estruturais acima nem os bloqueios abaixo.
            $query->whereKey($onlyGondolaId);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('reoptimization_next_run_at')
                    ->orWhere('reoptimization_next_run_at', '<=', now());
            });
        }

        return $query->get()->reject(fn (Gondola $gondola): bool => $this->isBlocked($gondola))->values();
    }

    /**
     * Enfileira a análise de uma gôndola e avança a cadência.
     *
     * Devolve o run criado, ou null quando a gôndola não tem geração anterior — sem ela não há
     * configuração para reusar, e inventar uma (com defaults) produziria um planograma que o
     * usuário nunca pediu, apresentado como se fosse "a evolução do dele".
     */
    public function enqueue(
        Gondola $gondola,
        GenerationRunTrigger $trigger = GenerationRunTrigger::Scheduled,
        ?string $userId = null,
    ): ?PlanogramGenerationRun {
        $lastRun = PlanogramGenerationRun::query()
            ->applied()
            ->where('gondola_id', $gondola->id)
            ->where('status', GenerationRunStatus::Completed)
            ->latest('created_at')
            ->first();

        if ($lastRun === null || ! is_array($lastRun->config_snapshot) || $lastRun->config_snapshot === []) {
            Log::info('Reotimização ignorada: gôndola sem geração anterior', ['gondola_id' => $gondola->id]);

            return null;
        }

        $config = $this->windowShifter->shift(
            $lastRun->config_snapshot,
            configuredAt: $lastRun->created_at ?? now(),
            now: now(),
        );

        $run = PlanogramGenerationRun::create([
            'planogram_id' => $gondola->planogram_id,
            'gondola_id' => $gondola->id,
            'user_id' => $userId,
            'status' => GenerationRunStatus::Queued,
            'mode' => 'template',
            'kind' => GenerationRunKind::Proposal,
            'trigger' => $trigger,
            'config_snapshot' => $config,
            'template_id' => $gondola->template_id,
        ]);

        // A cadência avança AQUI, no enfileiramento — não no fim do job. Se o job falhar, a
        // gôndola não fica presa disparando uma análise nova a cada rodada do agendador.
        $this->advanceCadence($gondola);

        GenerateReoptimizationProposalJob::dispatch(
            gondolaId: (string) $gondola->id,
            planogramId: (string) $gondola->planogram_id,
            config: $config,
            templateId: (string) $gondola->template_id,
            userId: $userId,
            tenantId: (string) Tenant::current()?->getKey(),
            runId: (string) $run->id,
            trigger: $trigger,
        );

        Log::info('Reotimização enfileirada', [
            'run_id' => $run->id,
            'gondola_id' => $gondola->id,
            'trigger' => $trigger->value,
            'sales_window' => [$config['start_date'] ?? null, $config['end_date'] ?? null],
        ]);

        return $run;
    }

    /**
     * Bloqueios que valem tanto para o agendador quanto para o "analisar agora": não faz sentido
     * calcular uma proposta contra um layout que está prestes a mudar, nem empilhar uma segunda
     * proposta sobre uma que ainda não foi decidida (as duas partiriam do mesmo baseline e
     * aprovar as duas aplicaria só a última, sem o usuário perceber).
     */
    private function isBlocked(Gondola $gondola): bool
    {
        $hasPendingProposal = PlanogramReoptimizationProposal::query()
            ->where('gondola_id', $gondola->id)
            ->pending()
            ->exists();

        if ($hasPendingProposal) {
            return true;
        }

        return PlanogramGenerationRun::query()
            ->where('gondola_id', $gondola->id)
            ->whereIn('status', [GenerationRunStatus::Queued, GenerationRunStatus::Running])
            ->exists();
    }

    private function advanceCadence(Gondola $gondola): void
    {
        $frequency = $gondola->reoptimization_frequency;

        $gondola->forceFill([
            'reoptimization_last_run_at' => now(),
            'reoptimization_next_run_at' => $frequency?->nextRunFrom(now()),
        ])->save();
    }
}
