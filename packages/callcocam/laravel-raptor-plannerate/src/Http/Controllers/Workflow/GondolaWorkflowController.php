<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Workflow;

use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowHistory;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\User;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exibe o detalhe do workflow de uma gôndola e delega ações ao FlowManager (pacote flow).
 */
class GondolaWorkflowController extends Controller
{
    /**
     * Show gondola details with flow execution and history.
     */
    public function show(Gondola $gondola): Response
    {
        $gondola->load('planogram:id,name');

        $flowExecution = FlowExecution::query()
            ->with(['configStep.stepTemplate', 'stepTemplate'])
            ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $gondola->id)
            ->first();

        $history = FlowHistory::query()
            ->whereIn('workable_type', WorkflowMorphMap::gondolaWorkflowTypes())
            ->where('workable_id', $gondola->id)
            ->orderBy('performed_at', 'desc')
            ->limit(50)
            ->get();

        $userIds = $history->pluck('user_id')
            ->merge($history->pluck('previous_responsible_id'))
            ->merge($history->pluck('new_responsible_id'))
            ->unique()->filter()->values();
        $users = $userIds->isNotEmpty() ? User::whereIn('id', $userIds)->get()->keyBy('id') : collect();
        $historyArray = $history->map(fn ($h) => array_merge($h->toArray(), [
            'user' => $users->get($h->user_id)?->only('id', 'name'),
            'previous_assigned_user' => $users->get($h->previous_responsible_id)?->only('id', 'name'),
            'new_assigned_user' => $users->get($h->new_responsible_id)?->only('id', 'name'),
            'action' => $h->action?->value ?? $h->action,
        ]))->toArray();

        $flowExecutionData = $flowExecution?->toArray();
        if ($flowExecutionData && $flowExecution->current_responsible_id) {
            $flowExecutionData['assigned_user'] = User::find($flowExecution->current_responsible_id)?->only('id', 'name', 'email');
        }

        return Inertia::render('Workflow/Gondola/Show', [
            'gondola' => $gondola,
            'flowExecution' => $flowExecutionData ?? null,
            'history' => $historyArray,
        ]);
    }
}
