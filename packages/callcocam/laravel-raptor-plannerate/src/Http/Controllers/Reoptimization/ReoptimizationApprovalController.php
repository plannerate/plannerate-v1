<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization;

use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Exceptions\StaleProposalException;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\RejectProposalRequest;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\ProposalApplier;
use Illuminate\Http\RedirectResponse;

/**
 * Decisão do usuário sobre uma proposta de reotimização.
 *
 * A aprovação é TUDO-OU-NADA. Escolher mudanças a dedo quebraria a coerência física do layout:
 * o motor calculou cada posição assumindo que TODAS as outras mudanças aconteceriam. Aplicar
 * metade produziria sobreposições e buracos — um planograma que ninguém projetou.
 */
class ReoptimizationApprovalController extends Controller
{
    public function approve(string $proposal, ProposalApplier $applier): RedirectResponse
    {
        $model = PlanogramReoptimizationProposal::query()->findOrFail($proposal);

        $this->authorize('update', $model->gondola);

        try {
            $applier->apply($model, (string) auth()->id());
        } catch (StaleProposalException $e) {
            return back()->withErrors(['proposal' => $e->getMessage()]);
        }

        return back()->with('success', __('plannerate.reoptimization.messages.applied'));
    }

    public function reject(RejectProposalRequest $request, string $proposal): RedirectResponse
    {
        $model = PlanogramReoptimizationProposal::query()->findOrFail($proposal);

        $this->authorize('update', $model->gondola);

        if ($model->status !== ProposalStatus::Pending) {
            return back()->withErrors(['proposal' => __('plannerate.reoptimization.errors.already_decided')]);
        }

        $model->forceFill([
            'status' => ProposalStatus::Rejected,
            'rejection_reason' => $request->validated('reason'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ])->save();

        return back()->with('success', __('plannerate.reoptimization.messages.rejected'));
    }
}
