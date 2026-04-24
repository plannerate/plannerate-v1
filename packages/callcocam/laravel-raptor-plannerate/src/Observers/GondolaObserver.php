<?php

namespace Callcocam\LaravelRaptorPlannerate\Observers;

use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Services\FlowManager;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\GondolaWorkflow;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\PlanogramWorkflow;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Illuminate\Support\Facades\Log;

class GondolaObserver
{
    public function created(Gondola $gondola): void
    {
        if (! $gondola->planogram_id) {
            return;
        }

        $this->ensureFlowExecutionForGondola($gondola);
    }

    public function updated(Gondola $gondola): void
    {
        if (! $gondola->planogram_id) {
            return;
        }

        $existing = FlowExecution::query()
            ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $gondola->id)
            ->first();

        if ($existing) {
            return;
        }

        $this->ensureFlowExecutionForGondola($gondola);
    }

    public function deleted(Gondola $gondola): void
    {
        FlowExecution::query()
            ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $gondola->id)
            ->delete();
    }

    public function restored(Gondola $gondola): void
    {
        FlowExecution::onlyTrashed()
            ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $gondola->id)
            ->restore();
    }

    public function forceDeleted(Gondola $gondola): void
    {
        FlowExecution::query()
            ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $gondola->id)
            ->forceDelete();
    }

    protected function ensureFlowExecutionForGondola(Gondola $gondola): void
    {
        $planogramWorkflow = PlanogramWorkflow::find($gondola->planogram_id);
        if (! $planogramWorkflow) {
            return;
        }

        $flowManager = app(FlowManager::class);
        $steps = $flowManager->getStepsFor($planogramWorkflow);
        if ($steps->isEmpty()) {
            Log::debug('GondolaObserver: Nenhuma etapa de workflow para o planograma', [
                'planogram_id' => $gondola->planogram_id,
                'gondola_id' => $gondola->id,
            ]);

            return;
        }

        $gondolaWorkflow = GondolaWorkflow::find($gondola->id);
        if (! $gondolaWorkflow) {
            return;
        }

        try {
            $flowManager->createPendingExecution($gondolaWorkflow, $planogramWorkflow);
        } catch (\Throwable $e) {
            Log::warning('GondolaObserver: Erro ao criar FlowExecution', [
                'gondola_id' => $gondola->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
