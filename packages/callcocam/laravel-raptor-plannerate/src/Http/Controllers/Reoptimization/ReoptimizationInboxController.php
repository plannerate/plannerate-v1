<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization;

use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fila de propostas de reotimização aguardando decisão, de todas as gôndolas.
 *
 * Sem esta tela, a proposta só é descoberta abrindo o editor daquela gôndola específica (o banner)
 * ou pela notificação — que o usuário pode ter perdido. Com dezenas de gôndolas reprocessando por
 * mês, as propostas se acumulariam invisíveis e a reotimização morreria por falta de descoberta:
 * o sistema calcularia melhorias que ninguém nunca veria.
 */
class ReoptimizationInboxController extends Controller
{
    /**
     * Contagem de pendentes — alimenta o badge do menu.
     *
     * Estático para ser chamado da definição do menu sem instanciar o controller.
     */
    public static function pendingCount(): int
    {
        return PlanogramReoptimizationProposal::query()->pending()->count();
    }

    public function index(): Response
    {
        // scopeSummary(): os snapshots de layout somam ~150 KB por proposta. Uma listagem sem ele
        // serializaria megabytes de JSON que a tela não usa.
        $proposals = PlanogramReoptimizationProposal::query()
            ->summary()
            ->with(['gondola:id,name,planogram_id'])
            ->pending()
            ->latest('created_at')
            ->get();

        return Inertia::render('tenant/reoptimization/Index', [
            'proposals' => $proposals->map(fn (PlanogramReoptimizationProposal $proposal): array => [
                'id' => $proposal->id,
                'gondola_name' => $proposal->gondola?->name,
                'changes_count' => count($proposal->diff_summary['entries'] ?? []),
                'summary' => $proposal->diff_summary['summary'] ?? [],
                'occupancy_before' => $proposal->occupancy_before,
                'occupancy_after' => $proposal->occupancy_after,
                'sales_period_start' => $proposal->sales_period_start?->toDateString(),
                'sales_period_end' => $proposal->sales_period_end?->toDateString(),
                'created_at' => $proposal->created_at?->toIso8601String(),
                'url' => route('tenant.planograms.reoptimization.show', ['proposal' => $proposal->id], false),
            ])->values(),
            'statusLabel' => ProposalStatus::Pending->label(),
        ]);
    }
}
