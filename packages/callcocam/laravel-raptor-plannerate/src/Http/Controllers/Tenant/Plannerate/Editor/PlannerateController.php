<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor;

use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Support\Actions\CustomAction;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Concerns\HasWorkflowToggle;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Store;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\User;
use Callcocam\LaravelRaptorPlannerate\Policies\FlowExecutionPolicy as AppFlowExecutionPolicy;
use Callcocam\LaravelRaptorPlannerate\Services\Workflow\KanbanService;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlannerateController extends Controller
{
    use HasWorkflowToggle;

    public function show(Request $request, $record): Response
    {
        $record = Planogram::findOrFail($record);

        // Carrega apenas a estrutura das gôndolas sem produtos (otimizado)
        $record->load(['gondolas.sections.shelves']);

        // Converte para array e adiciona store (cross-database)
        $recordArray = $record->toArray();

        // Carrega dados do mapa da store se existir (está no banco landlord)
        if ($record->store_id) {
            $store = Store::find($record->store_id);
            if ($store) {
                $recordArray['store'] = [
                    'id' => $store->id,
                    'name' => $store->name,
                    'map_image_path' => $store->map_image_path,
                    'map_regions' => $store->map_regions,
                ];
            }
        }

        // Monta filtros com planogram_id fixo
        $filters = $this->buildFilters($request, $record->id);

        // Se não existe configuração de workflow, mostra apenas a view de gôndolas
        if (! $this->isWorkflowEnabled()) {
            return Inertia::render('tenant/plannerates/index', [
                'filters' => $filters,
                'record' => $recordArray,
                'users' => User::select('id', 'name')->get(),
                'breadcrumbs' => $this->buildBreadcrumbs($record),
            ]);
        }

        $hasWorkflowConfig = FlowConfigStep::query()
            ->whereIn('configurable_type', WorkflowMorphMap::planogramWorkflowTypes())
            ->where('configurable_id', $record->id)
            ->exists();

        // Monta filtros com planogram_id fixo
        $filters = $this->buildFilters($request, $record->id);
        $flow = $this->resolveFlowForPlanogram($record);
        if (! $hasWorkflowConfig || ! $flow) {
            return Inertia::render('tenant/plannerates/index', [
                'filters' => $filters,
                'record' => $recordArray,
                'users' => User::select('id', 'name')->get(),
                'breadcrumbs' => $this->buildBreadcrumbs($record),
            ]);
        }

        // Carrega dados do Kanban filtrados pelo planograma atual (com dados para o modal de detalhes)
        $service = app(KanbanService::class)
            ->setFlow($flow)
            ->forPlanogram($record->id)
            ->setFilters($filters)
            ->withDetailModal(true)
            ->modal(app(KanbanService::class)->buildDefaultModalBuilder([
                'start' => $this->gondolaActionUrl('flow.execution.start', 'execution', 'id'),
                'pause' => $this->gondolaActionUrl('flow.execution.pause', 'execution', 'id'),
                'resume' => $this->gondolaActionUrl('flow.execution.resume', 'execution', 'id'),
                'abandon' => $this->gondolaActionUrl('flow.execution.abandon', 'execution', 'id'),
                'notes' => $this->gondolaActionUrl('flow.execution.notes', 'execution', 'id'),
            ]))
            ->card(app(KanbanService::class)->buildDefaultCardBuilder(
                fn (?FlowExecution $execution) => $this->resolveExecutionWorkableUrl($execution),
                fn (?FlowExecution $execution) => $this->canOpenExecutionWorkable($execution),
            )->addAction(
                CustomAction::make('view_pdf')
                    ->label('Ver')
                    ->method('get')
                    ->icon('Eye')
                    ->target('_blank')
                    ->component('flow-action-link')
                    ->url(fn (?FlowExecution $execution) => $this->resolveGondolaPdfUrl($execution))
                    ->visible(fn (?FlowExecution $execution) => in_array($execution?->status, [FlowStatus::InProgress, FlowStatus::Completed]))
            ));

        $kanbanData = $service
            ->getBoardData();

        return Inertia::render('tenant/plannerates/kanban', [
            'message' => 'Visualização Kanban do Planograma',
            'resourceName' => $record->name,
            'resourcePluralName' => 'kanbans',
            'resourceLabel' => $record->name,
            'resourcePluralLabel' => $record->name,
            'maxWidth' => 'full',
            'record' => $recordArray,
            'planogramIdForCreate' => $record->id, // Permite criar góndola neste planograma
            'breadcrumbs' => $this->buildBreadcrumbs($record),
            'detailModalConfig' => $service->getDetailModalConfig(),
            ...$kanbanData,
            // filters deve vir APÓS o spread para sobrescrever $kanbanData['filters'] (shape diferente)
            'filters' => [
                'values' => $filters,
                'data' => Inertia::defer(fn () => $this->withoutPlanogramFilter($service->getFilterOptionsData())),
            ],
        ]);
    }

    /**
     * Constrói os filtros a partir da requisição
     *
     * Filtros disponíveis:
     * - planogram_id: Fixo (planograma atual) - não pode ser mudado
     * - loja_id: Filtra execuções por loja (NÃO afeta options de planogramas)
     * - user_id: Filtra execuções pelo responsável atual
     * - assigned_to: Filtra execuções por atribuição (role/user)
     * - status: Filtra execuções por status (not_started, in_progress, completed, etc)
     * - only_overdue: Mostra apenas execuções atrasadas
     * - show_completed: Inclui execuções completadas
     */
    protected function buildFilters(Request $request, string $planogramId): array
    {
        return [
            'planogram_id' => $planogramId, // Fixo no planograma atual
            'loja_id' => $request->input('loja_id'),
            'user_id' => $request->input('user_id'),
            'assigned_to' => $request->input('assigned_to'),
            'status' => $request->input('status'),
            'only_overdue' => $request->boolean('only_overdue'),
            'show_completed' => $request->boolean('show_completed'),
        ];
    }

    /**
     * Constrói o breadcrumb para navegação
     */
    protected function buildBreadcrumbs(Planogram $record): array
    {
        return [
            [
                'label' => 'Painel de controle',
                'url' => route('dashboard', [], false),
            ],
            [
                'label' => 'Planogramas',
                'url' => route('tenant.planograms.index', [], false),
            ],
            [
                'label' => $record->name,
                'url' => null,
            ],
        ];
    }

    protected function resolveFlowForPlanogram(Planogram $record): ?Flow
    {
        $flowId = FlowConfigStep::query()
            ->whereIn('configurable_type', WorkflowMorphMap::planogramWorkflowTypes())
            ->where('configurable_id', $record->id)
            ->with('stepTemplate:id,flow_id')
            ->orderBy('order')
            ->get()
            ->pluck('stepTemplate.flow_id')
            ->filter()
            ->first();

        if (! $flowId) {
            return null;
        }

        return Flow::find($flowId);
    }

    protected function withoutPlanogramFilter(array $filters): array
    {
        return collect($filters)
            ->reject(fn (mixed $filter): bool => is_array($filter) && ($filter['name'] ?? null) === 'planogram_id')
            ->values()
            ->all();
    }

    protected function resourcePath(): string
    {
        return 'tenant';
    }

    protected function gondolaActionUrl(
        string $routeName,
        string $routeParam = 'gondola',
        string $placeholder = 'workable.id'
    ): string {
        $marker = '__PLACEHOLDER__';
        $generated = route($routeName, [$routeParam => $marker], false);

        return str_replace($marker, "{{$placeholder}}", $generated);
    }

    protected function canOpenExecutionWorkable(?FlowExecution $execution): bool
    {
        $user = auth()->user();

        if (! $user || ! $execution) {
            return false;
        }

        if ($execution->current_responsible_id !== $user->id) {
            return false;
        }

        if (! AppFlowExecutionPolicy::passesRoleGate($user, $execution)) {
            return false;
        }

        $gondola = $this->resolveExecutionWorkable($execution);

        if (! $gondola) {
            return false;
        }

        $planogram = Planogram::withoutGlobalScopes()->find($gondola->planogram_id);

        return $planogram ? $user->can('update', $planogram) : false;
    }

    protected function resolveExecutionWorkableUrl(?FlowExecution $execution): ?string
    {
        return $this->resolveExecutionWorkable($execution)?->route_gondolas;
    }

    protected function resolveGondolaPdfUrl(?FlowExecution $execution): ?string
    {
        $gondola = $this->resolveExecutionWorkable($execution);

        if (! $gondola) {
            return null;
        }

        return route('export.gondola.view', ['gondola' => $gondola->id], false);
    }

    protected function resolveExecutionWorkable(?FlowExecution $execution): ?Model
    {
        if (! $execution) {
            return null;
        }

        $workableClass = WorkflowMorphMap::resolveWorkableModelClass($execution->workable_type);

        if (! $workableClass) {
            return null;
        }

        return $workableClass::find($execution->workable_id);
    }
}
