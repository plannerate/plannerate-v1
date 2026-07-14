<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization;

use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunTrigger;
use Callcocam\LaravelRaptorPlannerate\Enums\ReoptimizationFrequency;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\UpdateReoptimizationCadenceRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\ReoptimizationScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Cadência de reotimização da gôndola e disparo sob demanda.
 */
class GondolaReoptimizationController extends Controller
{
    /**
     * Liga/desliga a reotimização e define o ritmo.
     */
    public function updateCadence(UpdateReoptimizationCadenceRequest $request, string $gondola): RedirectResponse
    {
        $model = Gondola::query()->findOrFail($gondola);

        $this->authorize('update', $model);

        if ($model->template_id === null) {
            // Sem template não há como simular: o modo automático sintetiza o template no banco,
            // então o "dry-run" deixaria rastro e a proposta deixaria de ser uma proposta.
            return back()->withErrors([
                'reoptimization_enabled' => __('plannerate.reoptimization.errors.requires_template'),
            ]);
        }

        $enabled = $request->boolean('reoptimization_enabled');
        $frequency = $enabled
            ? ReoptimizationFrequency::from((string) $request->validated('reoptimization_frequency'))
            : null;

        $model->forceFill([
            'reoptimization_enabled' => $enabled,
            'reoptimization_frequency' => $frequency,
            // Ao ligar, a primeira análise é agendada para daqui a um período completo — e não
            // para "agora" — para não disparar uma proposta no instante em que o usuário salva a
            // configuração, sem ele ter pedido nada.
            'reoptimization_next_run_at' => $enabled ? $frequency?->nextRunFrom(now()) : null,
        ])->save();

        return back()->with('success', __('plannerate.reoptimization.messages.cadence_saved'));
    }

    /**
     * "Analisar agora": enfileira a análise fora da cadência.
     */
    public function runNow(string $gondola, ReoptimizationScheduler $scheduler): RedirectResponse
    {
        $model = Gondola::query()->findOrFail($gondola);

        $this->authorize('update', $model);

        if ($model->template_id === null) {
            return back()->withErrors(['reoptimization' => __('plannerate.reoptimization.errors.requires_template')]);
        }

        // eligibleGondolas() com id explícito ignora a cadência (é o usuário pedindo), mas mantém
        // os bloqueios: proposta pendente ou geração em curso.
        if ($scheduler->eligibleGondolas((string) $model->id)->isEmpty()) {
            return back()->withErrors(['reoptimization' => __('plannerate.reoptimization.errors.blocked')]);
        }

        $run = $scheduler->enqueue($model, GenerationRunTrigger::Manual, (string) auth()->id());

        if ($run === null) {
            return back()->withErrors(['reoptimization' => __('plannerate.reoptimization.errors.no_previous_generation')]);
        }

        return back()->with('success', __('plannerate.reoptimization.messages.queued'));
    }

    /**
     * Proposta pendente da gôndola — alimenta o banner do editor.
     */
    public function pending(string $gondola): JsonResponse
    {
        $model = Gondola::query()->findOrFail($gondola);

        $this->authorize('view', $model);

        $proposal = PlanogramReoptimizationProposal::query()
            ->summary()
            ->where('gondola_id', $model->id)
            ->pending()
            ->latest('created_at')
            ->first();

        if ($proposal === null) {
            return response()->json(['proposal' => null]);
        }

        $summary = $proposal->diff_summary['summary'] ?? [];

        return response()->json([
            'proposal' => [
                'id' => $proposal->id,
                'changes_count' => count($proposal->diff_summary['entries'] ?? []),
                'summary' => $summary,
                'created_at' => $proposal->created_at?->toIso8601String(),
                'url' => route('tenant.planograms.reoptimization.show', ['proposal' => $proposal->id], false),
            ],
        ]);
    }
}
