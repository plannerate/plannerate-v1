<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;

use App\Enums\WorkflowExecutionStatus;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Services\WorkflowExecutionLayerService;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Services\Export\GondolaPrintService;
use Inertia\Inertia;
use Inertia\Response;

class GondolaPdfPreviewController extends Controller
{
    public function __construct(
        protected GondolaPrintService $printService
    ) {}

    /**
     * Exibe preview da gôndola usando componentes Vue.
     *
     * Sobre a tela read-only acopla a camada de Execução em Loja apenas para
     * quem tem a responsabilidade (`canExecute`). O payload pesado de execução
     * (evidências, divergências, SLA) só é consultado sob demanda, via
     * Inertia::optional, sem onerar quem apenas visualiza.
     */
    public function show(string $gondolaId): Response
    {
        $data = $this->printService->prepareGondolaData($gondolaId);

        // Carregar análises mais recentes
        $abcAnalysis = GondolaAnalysis::getLatestAbcAnalysis($gondolaId);
        $stockAnalysis = GondolaAnalysis::getLatestStockAnalysis($gondolaId);

        $user = auth()->user();
        $execution = $this->resolveExecutableExecution($gondolaId);
        $canExecute = $user instanceof User
            && $execution !== null
            && $user->can('execute', $execution);

        return Inertia::render('tenant/editor/pdfPrintview', [
            'gondola' => $data['gondola'],
            'sections' => $data['sections'],
            'analysis' => [
                'abc' => $abcAnalysis?->toAbcFormattedArray(),
                'stock' => $stockAnalysis?->toStockFormattedArray(),
            ],
            'responsavel' => $user?->name,
            'canExecute' => $canExecute,
            'execution' => $canExecute
                ? Inertia::optional(fn () => $this->buildExecutionPayload($execution, $user))
                : null,
        ]);
    }

    /**
     * Localiza a execução operável da gôndola (etapa Execução em Loja), com as
     * relações que o gate `execute` e o payload consultam — evitando N+1.
     */
    private function resolveExecutableExecution(string $gondolaId): ?WorkflowGondolaExecution
    {
        return WorkflowGondolaExecution::query()
            ->where('gondola_id', $gondolaId)
            ->whereIn('status', [
                WorkflowExecutionStatus::Pending,
                WorkflowExecutionStatus::Active,
                WorkflowExecutionStatus::Paused,
            ])
            ->with([
                'gondola:id,planogram_id',
                'gondola.planogram:id,category_id',
                'step.template:id,suggested_order,stage_type,access_mode',
                'step.availableUsers:id',
                'step.planogram:id',
                'step.planogram.workflowSteps:id,planogram_id,workflow_template_id,is_skipped,stage_type',
                'step.planogram.workflowSteps.template:id,suggested_order,stage_type',
            ])
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Inicia automaticamente a execução pendente (export §12) e devolve o
     * payload completo da camada de execução.
     *
     * @return array<string, mixed>
     */
    private function buildExecutionPayload(WorkflowGondolaExecution $execution, User $user): array
    {
        $service = app(WorkflowExecutionLayerService::class);
        $execution = $service->autoStartIfPending($execution, $user);

        return $service->buildPayload($execution, $user);
    }
}
