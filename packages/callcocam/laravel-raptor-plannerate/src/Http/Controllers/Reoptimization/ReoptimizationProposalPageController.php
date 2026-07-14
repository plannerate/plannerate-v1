<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página onde o usuário revisa uma proposta de reotimização e decide.
 *
 * Carrega o `diff_summary` (o que mudaria) mas NÃO os snapshots de layout: são ~150 KB de JSON
 * que a tela não usa — quem os lê é o ProposalApplier, no servidor.
 */
class ReoptimizationProposalPageController extends Controller
{
    public function show(string $proposal): Response
    {
        $model = PlanogramReoptimizationProposal::query()
            ->with(['gondola:id,name,planogram_id,generation_mode', 'reviewer:id,name'])
            ->findOrFail($proposal);

        $this->authorize('view', $model->gondola);

        return Inertia::render('tenant/editor/ReoptimizationProposal', [
            'proposal' => [
                'id' => $model->id,
                'status' => $model->status->value,
                'status_label' => $model->status->label(),
                'trigger' => $model->trigger?->value,
                'diff' => $model->diff_summary,
                'sales_period_start' => $model->sales_period_start?->toDateString(),
                'sales_period_end' => $model->sales_period_end?->toDateString(),
                'occupancy_before' => $model->occupancy_before,
                'occupancy_after' => $model->occupancy_after,
                'rejection_reason' => $model->rejection_reason,
                'error_message' => $model->error_message,
                'reviewed_at' => $model->reviewed_at?->toIso8601String(),
                'reviewer_name' => $model->reviewer?->name,
                'created_at' => $model->created_at?->toIso8601String(),
            ],
            'gondola' => [
                'id' => $model->gondola?->id,
                'name' => $model->gondola?->name,
                'planogram_id' => $model->planogram_id,
            ],
            'editorUrl' => route('tenant.planograms.gondolas.editor', ['record' => $model->gondola_id], false),
        ]);
    }
}
