<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Reoptimization;

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\PlanogramWriterInterface;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\RejectedProductsWriter;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\GondolaLayoutReader;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\LayoutHasher;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\LayoutSnapshotSerializer;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunKind;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Exceptions\StaleProposalException;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramGenerationRun;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Aplica uma proposta aprovada: escreve na gôndola EXATAMENTE o layout que o usuário revisou.
 *
 * Não recalcula. Recalcular na hora da aprovação parece equivalente, mas não é: as vendas mudam
 * entre a análise e a decisão, e o usuário receberia um layout diferente do diff que aprovou —
 * o pior tipo de bug, porque o sistema pareceria estar funcionando.
 *
 * Duas defesas antes de escrever:
 *  - LOCK, porque escrever é destrutivo (apaga e recria todos os segments). Duas aprovações
 *    concorrentes intercalariam deletes e inserts e deixariam a gôndola num estado inválido.
 *  - HASH do baseline, porque a gôndola pode ter mudado desde a análise. Se mudou, o diff
 *    aprovado mente — a proposta é marcada `superseded` e nada é escrito.
 */
final class ProposalApplier
{
    use UsesPlannerateTenantDatabase;

    public function __construct(
        private readonly PlanogramWriterInterface $writer,
        private readonly RejectedProductsWriter $rejectedWriter,
        private readonly GondolaLayoutReader $layoutReader,
        private readonly LayoutSnapshotSerializer $serializer,
        private readonly LayoutHasher $hasher,
    ) {}

    /**
     * @throws StaleProposalException quando a proposta já foi decidida ou a gôndola mudou.
     */
    public function apply(PlanogramReoptimizationProposal $proposal, ?string $userId = null): PlanogramReoptimizationProposal
    {
        $lock = Cache::lock("reoptimization-apply:{$proposal->gondola_id}", 30);

        if (! $lock->get()) {
            throw new StaleProposalException(__('plannerate.reoptimization.errors.locked'));
        }

        try {
            // A verificação roda FORA da transação de propósito: ela pode marcar a proposta como
            // `superseded`, e essa marcação tem que sobreviver — se estivesse dentro, o rollback
            // disparado pela exceção desfaria a própria recusa, e a proposta voltaria a aparecer
            // como pendente na próxima tentativa.
            $gondola = $this->guardApplicable($proposal, $userId);

            return $this->plannerateTenantDatabase()->transaction(
                fn (): PlanogramReoptimizationProposal => $this->write($proposal, $gondola, $userId)
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws StaleProposalException
     */
    private function guardApplicable(PlanogramReoptimizationProposal $proposal, ?string $userId): Gondola
    {
        $proposal->refresh();

        if ($proposal->status !== ProposalStatus::Pending) {
            throw new StaleProposalException(__('plannerate.reoptimization.errors.already_decided'));
        }

        $gondola = Gondola::with(['sections.shelves'])->findOrFail($proposal->gondola_id);

        $currentHash = $this->hasher->hash($this->layoutReader->read($gondola, excludeLockedShelves: true));

        if ($currentHash !== $proposal->baseline_hash) {
            $proposal->forceFill([
                'status' => ProposalStatus::Superseded,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ])->save();

            Log::info('Proposta de reotimização desatualizada — aplicação recusada', [
                'proposal_id' => $proposal->id,
                'gondola_id' => $gondola->id,
            ]);

            throw new StaleProposalException(__('plannerate.reoptimization.errors.stale'));
        }

        return $gondola;
    }

    private function write(PlanogramReoptimizationProposal $proposal, Gondola $gondola, ?string $userId): PlanogramReoptimizationProposal
    {
        $segments = $this->serializer->fromArray($proposal->proposed_layout ?? []);
        $tenantId = (string) (Tenant::current()?->getKey() ?? $proposal->tenant_id);

        // A gôndola precisa ser recarregada com as SEÇÕES que o writer usa para achar as shelves.
        $this->writer->write((string) $gondola->id, $gondola->sections, $segments);

        $this->rejectedWriter->writeRows(
            (string) $proposal->planogram_id,
            (string) $gondola->id,
            $tenantId,
            $proposal->proposed_rejected ?? [],
        );

        // Um run `apply` registra que a gôndola mudou. Sem ele, o editor continuaria mostrando a
        // última geração manual como "a geração corrente" — e o relatório não bateria com o que
        // está na prateleira.
        $appliedRun = $this->recordAppliedRun($proposal, $userId);

        $proposal->forceFill([
            'status' => ProposalStatus::Applied,
            'applied_run_id' => $appliedRun->id,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'applied_at' => now(),
        ])->save();

        Log::info('Proposta de reotimização aplicada', [
            'proposal_id' => $proposal->id,
            'gondola_id' => $gondola->id,
            'segments' => $segments->count(),
        ]);

        return $proposal;
    }

    /**
     * Copia os relatórios do run da proposta para um run `apply`: o cálculo é o mesmo, só o
     * desfecho é diferente (agora está na gôndola).
     */
    private function recordAppliedRun(PlanogramReoptimizationProposal $proposal, ?string $userId): PlanogramGenerationRun
    {
        $proposalRun = $proposal->generationRun;

        return PlanogramGenerationRun::create([
            'planogram_id' => $proposal->planogram_id,
            'gondola_id' => $proposal->gondola_id,
            'user_id' => $userId,
            'status' => GenerationRunStatus::Completed,
            'mode' => 'template',
            'kind' => GenerationRunKind::Apply,
            'trigger' => $proposal->trigger,
            'config_snapshot' => $proposal->config_snapshot,
            'template_id' => $proposalRun?->template_id,
            'started_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 0,
            'occupancy_avg' => $proposal->occupancy_after,
            'occupancy_min' => $proposalRun?->occupancy_min,
            'occupancy_max' => $proposalRun?->occupancy_max,
            'capacity_report' => $proposalRun?->capacity_report,
            'validation_report' => $proposalRun?->validation_report,
        ]);
    }
}
