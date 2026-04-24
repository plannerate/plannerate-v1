<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Workflow;

use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Services\KanbanService as PackageKanbanService;
use Callcocam\LaravelRaptorFlow\Support\Actions\AbandonAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\CustomAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\PauseAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\ResumeAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\StartAction;
use Callcocam\LaravelRaptorFlow\Support\Builders\ConfigureKanbanCard;
use Callcocam\LaravelRaptorFlow\Support\Builders\ConfigureKanbanModal;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayCardItem;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayColumn;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayField;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayRow;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplaySection;
use Callcocam\LaravelRaptorFlow\Support\Display\NotesBlock;
use Callcocam\LaravelRaptorFlow\Support\Kanban\Columns\ExecutionColumn;
use Callcocam\LaravelRaptorFlow\Support\Kanban\Columns\Types\PermissionsColumn;
use Callcocam\LaravelRaptorFlow\Support\Kanban\Columns\Types\WorkableColumn;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\GondolaWorkflow;
use Callcocam\LaravelRaptorPlannerate\Models\Workflow\PlanogramWorkflow;
use Callcocam\LaravelRaptorPlannerate\Services\Workflow\Kanban\KanbanExecutionPermissionsResolver;
use Callcocam\LaravelRaptorPlannerate\Services\Workflow\Kanban\KanbanFilterOptionsProvider;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Closure;
use Illuminate\Support\Collection;

/**
 * KanbanService do domínio Plannerate (gôndolas/planogramas).
 *
 * Segue a cadeia flow-first:
 *   Flow → stepTemplates → FlowConfigStep (configurable = PlanogramWorkflow)
 *       → planogram_ids → gondolas → FlowExecution
 *
 * Apenas planogramas com FlowConfigStep configurados para o fluxo atual aparecem no board.
 * Sobrescreve apenas os hooks do template method do pacote.
 */
class KanbanService extends PackageKanbanService
{
    public function __construct(
        protected ?KanbanExecutionPermissionsResolver $executionPermissionsResolver = null,
        protected ?KanbanFilterOptionsProvider $filterOptionsProvider = null,
    ) {}

    protected ?string $planogramId = null;

    protected ?Collection $gondolasCache = null;

    protected ?Collection $planogramsCache = null;

    protected ?Collection $allPlanogramsCache = null;

    /** FlowConfigStep carregados em loadEntityData(); reutilizados em getGroupConfigs(). */
    protected ?Collection $flowConfigStepsCache = null;

    protected ?array $planogramsWithConfigsCache = null;

    protected ?string $resolvedWorkableType = null;

    /**
     * @var array<string, array{templatePreviousStep: array{id: string, name: string}|null, templateNextStep: array{id: string, name: string}|null}>
     */
    protected array $stepNeighborsByTemplateId = [];

    public function forPlanogram(?string $planogramId): static
    {
        $this->planogramId = $planogramId;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Board data — wires domain hooks into fluent parent API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Carrega dados de domínio e delega a construção do board ao KanbanBoard via pai.
     */
    public function getBoardData(): array
    {
        $this->loadEntityData();

        $this->setWorkableType($this->resolveWorkableType());
        $this->setWorkableIds($this->resolveWorkableIds());
        $this->setGroupConfigs(fn () => $this->getGroupConfigs());

        $this->columns = [];
        foreach ($this->buildColumns() as $column) {
            $this->addColumn($column);
        }

        $this->setAdditionalQuery($this->buildAdditionalQuery());

        if ($roles = $this->buildUserRoles()) {
            $this->setUserRoles($roles);
        }

        return parent::getBoardData();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Template-method hooks
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Percorre a cadeia flow-first para carregar apenas os dados relevantes:
     *
     *   1. flow → stepTemplates (ativos, ordenados) → templateIds
     *   2. templateIds → FlowConfigStep[] onde configurable = PlanogramWorkflow
     *   3. FlowConfigStep.configurable_id → planogram IDs com workflow configurado
     *   4. planogram IDs → Planogram (allPlanogramsCache)
     *   5. planogram IDs → Gondola (gondolasCache), respeitando filtros de domínio
     */
    protected function loadEntityData(): void
    {
        $this->planogramsWithConfigsCache = null;
        $this->flowConfigStepsCache = collect();
        $this->stepNeighborsByTemplateId = [];
        $this->resolvedWorkableType = null;

        if ($this->flow === null) {
            $this->gondolasCache = collect();
            $this->planogramsCache = collect();
            $this->allPlanogramsCache = collect();

            return;
        }

        // 1. flow → templateIds
        $stepTemplates = $this->flow->stepTemplates()
            ->where('is_active', true)
            ->orderBy('suggested_order')
            ->get(['id', 'name', 'suggested_order']);

        $templateIds = $stepTemplates->pluck('id')->toArray();
        $this->stepNeighborsByTemplateId = $this->buildStepNeighborsByTemplateId($stepTemplates);

        if (empty($templateIds)) {
            $this->gondolasCache = collect();
            $this->planogramsCache = collect();
            $this->allPlanogramsCache = collect();

            return;
        }

        // 2. templateIds → FlowConfigStep[] (configurable = PlanogramWorkflow)
        $this->flowConfigStepsCache = FlowConfigStep::query()
            ->whereIn('configurable_type', WorkflowMorphMap::planogramWorkflowTypes())
            ->whereIn('flow_step_template_id', $templateIds)
            ->orderBy('order')
            ->get();

        // 3. FlowConfigStep → planogram IDs que possuem configuração neste fluxo
        $planogramIds = $this->flowConfigStepsCache
            ->pluck('configurable_id')
            ->unique()
            ->filter()
            ->toArray();

        if (empty($planogramIds)) {
            $this->gondolasCache = collect();
            $this->planogramsCache = collect();
            $this->allPlanogramsCache = collect();

            return;
        }

        // 4. Planogramas com workflow configurado (não todos do tenant)
        $this->allPlanogramsCache = PlanogramWorkflow::query()
            ->select('id', 'name')
            ->whereIn('id', $planogramIds)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        // 5. Gondolas dos planogramas com workflow, respeitando filtros de domínio
        $gondolasQuery = GondolaWorkflow::query()
            ->select('id', 'name', 'slug', 'planogram_id')
            ->whereIn('planogram_id', $planogramIds);

        if ($this->planogramId) {
            $gondolasQuery->where('planogram_id', $this->planogramId);
        }

        if ($this->hasFilter('loja_id')) {
            $idsForStore = Planogram::withoutGlobalScopes()
                ->whereIn('id', $planogramIds)
                ->where('store_id', $this->getFilter('loja_id'))
                ->pluck('id');
            $gondolasQuery->whereIn('planogram_id', $idsForStore);
        }

        $this->gondolasCache = $gondolasQuery->get()->keyBy('id');
        $this->resolvedWorkableType = $this->resolveDetectedWorkableType($this->gondolasCache->keys()->toArray());

        $visiblePlanogramIds = $this->gondolasCache->pluck('planogram_id')->unique()->filter();
        $this->planogramsCache = $visiblePlanogramIds->isNotEmpty()
            ? $this->allPlanogramsCache->whereIn('id', $visiblePlanogramIds->toArray())
            : collect();
    }

    protected function resolveWorkableType(): string
    {
        return $this->resolvedWorkableType ?? GondolaWorkflow::class;
    }

    /**
     * Resolve o workable_type realmente usado nas execucoes para as gondolas carregadas.
     * Prioriza o namespace atual do pacote, com fallback para namespace legado.
     *
     * @param  array<int, string>  $gondolaIds
     */
    protected function resolveDetectedWorkableType(array $gondolaIds): string
    {
        if (empty($gondolaIds)) {
            return GondolaWorkflow::class;
        }

        $selectedType = GondolaWorkflow::class;
        $highestCount = -1;

        foreach (WorkflowMorphMap::gondolaWorkflowTypes() as $type) {
            $count = FlowExecution::query()
                ->where('workable_type', $type)
                ->whereIn('workable_id', $gondolaIds)
                ->count();

            if ($count > $highestCount) {
                $highestCount = $count;
                $selectedType = $type;
            }
        }

        return $selectedType;
    }

    protected function resolveWorkableIds(): Closure|array
    {
        return fn () => $this->gondolasCache?->keys()->toArray() ?? [];
    }

    /**
     * @return ExecutionColumn[]
     */
    protected function buildColumns(): array
    {
        $user = auth()->user();
        $planogramEditPermissions = $this->resolvePlanogramEditPermissions($user);

        return [
            WorkableColumn::make('workable')
                ->resolveUsing(function (FlowExecution $execution) use ($planogramEditPermissions) {
                    $gondola = $this->gondolasCache?->get($execution->workable_id);

                    if (! $gondola) {
                        return null;
                    }

                    $planogram = $this->planogramsCache?->get($gondola->planogram_id);
                    $planogramData = $planogram?->only(['id', 'name']);

                    if ($planogram) {
                        $planogramData['edit_url'] = route('tenant.planograms.edit', ['record' => $planogram->id]);
                        $planogramData['can_edit'] = $planogramEditPermissions[$planogram->id] ?? false;
                    }

                    return [
                        'id' => $gondola->id,
                        'name' => $gondola->name,
                        'group_id' => $gondola->planogram_id,
                        'group_label' => $planogram?->name,
                        'slug' => $gondola->slug,
                        'edit_url' => $gondola->route_gondolas,
                        'planogram' => $planogramData,
                    ];
                }),

            PermissionsColumn::make('permissions')
                ->resolveUsing(function (FlowExecution $execution) use ($user, $planogramEditPermissions) {
                    $gondola = $this->gondolasCache?->get($execution->workable_id);
                    $gondolaPlanogramId = $gondola?->planogram_id;
                    $permissionsData = $this->executionPermissionsResolver()->resolve(
                        $user,
                        $execution,
                        $gondolaPlanogramId,
                        $planogramEditPermissions,
                        $this->isLastExecutionStep($execution),
                    );

                    return [
                        ...$permissionsData,
                        ...$this->resolveStepNeighbors($execution->flow_step_template_id),
                    ];
                }),
        ];
    }

    protected function resolveStepNeighbors(?string $templateId): array
    {
        if (! $templateId) {
            return [
                'templatePreviousStep' => null,
                'templateNextStep' => null,
            ];
        }

        return $this->stepNeighborsByTemplateId[$templateId] ?? [
            'templatePreviousStep' => null,
            'templateNextStep' => null,
        ];
    }

    /**
     * @param  Collection<int, object>  $stepTemplates
     * @return array<string, array{templatePreviousStep: array{id: string, name: string}|null, templateNextStep: array{id: string, name: string}|null}>
     */
    protected function buildStepNeighborsByTemplateId(Collection $stepTemplates): array
    {
        $templates = $stepTemplates->values();
        $neighbors = [];

        foreach ($templates as $index => $template) {
            $previous = $index > 0 ? $templates->get($index - 1) : null;
            $next = $templates->get($index + 1);

            $neighbors[(string) $template->id] = [
                'templatePreviousStep' => $previous
                    ? ['id' => (string) $previous->id, 'name' => (string) $previous->name]
                    : null,
                'templateNextStep' => $next
                    ? ['id' => (string) $next->id, 'name' => (string) $next->name]
                    : null,
            ];
        }

        return $neighbors;
    }

    protected function buildAdditionalQuery(): Closure
    {
        $filters = $this->filters;

        return function ($query) use ($filters): void {
            if (isset($filters['assigned_to']) && $filters['assigned_to']) {
                $roleId = $filters['assigned_to'];

                // Filtra execuções que estão na etapa cuja role padrão é a selecionada,
                // não por quem tem essa role como responsável atual.
                $configStepIds = FlowConfigStep::query()
                    ->where('default_role_id', $roleId)
                    ->pluck('id')
                    ->toArray();

                $query->whereIn('flow_config_step_id', $configStepIds);
            }
        };
    }

    protected function buildUserRoles(): ?Closure
    {
        return fn () => auth()->user()?->roles->pluck('slug')->toArray() ?? [];
    }

    /**
     * groupConfigs derivados dos FlowConfigStep já carregados em loadEntityData():
     * cada planograma com config no fluxo → { id, name, stepIds[] }.
     *
     * Reutiliza flowConfigStepsCache (sem nova query), agrupando por configurable_id.
     *
     * @return array<int, array{id: string, name: string, stepIds: array<string>}>
     */
    protected function getGroupConfigs(): array
    {
        if ($this->planogramsWithConfigsCache !== null) {
            return $this->planogramsWithConfigsCache;
        }

        if (! $this->allPlanogramsCache || $this->flowConfigStepsCache === null) {
            return [];
        }

        $stepsByPlanogram = $this->flowConfigStepsCache->groupBy('configurable_id');

        $this->planogramsWithConfigsCache = $this->allPlanogramsCache
            ->map(function (PlanogramWorkflow $planogram) use ($stepsByPlanogram) {
                $steps = $stepsByPlanogram->get($planogram->id) ?? collect();

                return [
                    'id' => $planogram->id,
                    'name' => $planogram->name,
                    'stepIds' => $steps
                        ->sortBy('order')
                        ->pluck('flow_step_template_id')
                        ->filter()
                        ->values()
                        ->toArray(),
                ];
            })
            ->values()
            ->toArray();

        return $this->planogramsWithConfigsCache;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Opções de filtro (pode ser chamado via Inertia defer numa nova request)
    // ─────────────────────────────────────────────────────────────────────────

    public function getFilterOptionsData(): array
    {
        if ($this->allPlanogramsCache === null) {
            $this->loadEntityData();
        }

        return $this->getOptionsFilters();
    }

    protected function getOptionsFilters(): array
    {
        // Extrai role IDs na ordem das etapas do fluxo para que o filtro "Função"
        // respeite a sequência do kanban ao invés de ordem alfabética.
        $orderedRoleIds = ($this->flowConfigStepsCache ?? collect())
            ->sortBy('order')
            ->pluck('default_role_id')
            ->filter()
            ->values()
            ->all();

        return $this->filterOptionsProvider()->getOptionsFilters($this->getGroupConfigs(), $orderedRoleIds);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers de domínio
    // ─────────────────────────────────────────────────────────────────────────

    protected function resolvePlanogramEditPermissions(?object $user): array
    {
        $permissions = [];

        if (! $user || ! $this->planogramsCache || $this->planogramsCache->isEmpty()) {
            return $permissions;
        }

        $editorPlanograms = Planogram::withoutGlobalScopes()
            ->whereIn('id', $this->planogramsCache->keys()->toArray())
            ->get()
            ->keyBy('id');

        foreach ($this->planogramsCache as $planogram) {
            $editorPlanogram = $editorPlanograms->get($planogram->id);

            $permissions[$planogram->id] = $editorPlanogram
                ? $user->can('update', $editorPlanogram)
                : false;
        }

        return $permissions;
    }

    public function buildDefaultModalBuilder(array $urls): ConfigureKanbanModal
    {
        $modal = ConfigureKanbanModal::make();

        if (! empty($urls['start'])) {
            $modal->addAction(
                StartAction::make('start')
                    ->url($urls['start'])
                    ->confirm('Iniciar etapa?', 'A etapa será iniciada e ficará ativa.')
            );
        }

        if (! empty($urls['pause'])) {
            $modal->addAction(PauseAction::make('pause')->url($urls['pause']));
        }

        if (! empty($urls['resume'])) {
            $modal->addAction(ResumeAction::make('resume')->url($urls['resume']));
        }

        if (! empty($urls['abandon'])) {
            $modal->addAction(
                AbandonAction::make('abandon')
                    ->url($urls['abandon'])
                    ->confirm(
                        'Abandonar etapa?',
                        'Você vai liberar a responsabilidade desta etapa. Outro usuário poderá assumir.'
                    )
            );
        }

        if (! empty($urls['finish'])) {
            $modal->addAction(
                CustomAction::make('finish')
                    ->label('Finalizar')
                    ->icon('CircleCheck')
                    ->url($urls['finish'])
                    ->visible(fn (?FlowExecution $execution) => $execution !== null && $this->isLastExecutionStep($execution))
                    ->confirm(
                        'Finalizar etapa?',
                        'A execução será marcada como concluída.'
                    )
            );
        }

        $modal
            ->addSection(
                DisplaySection::make('responsible')
                    ->label('Responsabilidade')
                    ->columnSpan(7)
                    ->addRow(
                        DisplayRow::make()->addFields([
                            DisplayField::label('currentResponsible.name')->labelText('Responsável atual'),
                            DisplayField::label('startedBy.name')->labelText('Iniciado por'),
                        ]),
                    )
            )
            ->addSection(
                DisplaySection::make('summary')
                    ->label('Resumo')
                    ->columnSpan(5)
                    ->addField(
                        DisplayField::cards('summary', [
                            DisplayCardItem::make('status')->label('Status')->format('badge'),
                            DisplayCardItem::make('sla_date')->label('SLA')->format('date'),
                        ]),
                    )
            )
            ->addSection(
                DisplaySection::make('timeline')
                    ->label('Fluxo do Workflow')
                    ->columnSpan(12)
                    ->addField(DisplayField::timeline('workflow_step_template_id'))
            )
            ->addSection(
                DisplaySection::make('participants')
                    ->label('Possíveis executantes')
                    ->columnSpan(12)
                    ->addField(DisplayField::selectUsers('config.users'))
            )
            ->addSection(
                DisplaySection::make('notifications')
                    ->label('Notificações')
                    ->columnSpan(12)
                    ->addField(
                        DisplayField::cards('notifications_summary', [
                            DisplayCardItem::make('notifications_summary.unread_count')->label('Não lidas'),
                            DisplayCardItem::make('notifications_summary.count')->label('Total'),
                        ]),
                    )
                    ->addField(
                        DisplayField::make('notifications_summary.latest_titles', 'textarea')
                            ->labelText('Recentes')
                    )
            );

        if (! empty($urls['notes'])) {
            $modal->addNote(
                NotesBlock::make('workflow-notes')
                    ->url($urls['notes'])
                    ->placeholder('Adicionar notas sobre esta etapa...')
            );
        }

        return $modal;
    }

    public function buildDefaultCardBuilder(
        ?Closure $openUrlResolver = null,
        ?Closure $openVisibilityResolver = null,
    ): ConfigureKanbanCard {
        $card = ConfigureKanbanCard::make()
            ->addColumn(
                DisplayColumn::make('identity')
                    ->addFields([
                        DisplayField::label('workable.name'),
                        DisplayField::text('workable.group_label'),
                    ])
            )
            ->addColumn(
                DisplayColumn::make('meta')
                    ->addFields([
                        DisplayField::badge('status'),
                        DisplayField::date('sla_date')->labelText('SLA'),
                    ])
            )
            ->addColumn(
                DisplayColumn::make('kpi_metric')
                    ->label('KPI da última métrica')
                    ->showWhenEmpty(false)
                    ->addField(
                        DisplayField::cards('metrics_summary.latest', [
                            DisplayCardItem::make('metrics_summary.latest.effective_work_minutes')->label('Efetivo (min)'),
                            DisplayCardItem::make('metrics_summary.latest.deviation_minutes')->label('Desvio (min)'),
                            DisplayCardItem::make('metrics_summary.latest.estimated_duration_minutes')->label('Estimado (min)'),
                        ])
                    )
            )
            ->addColumn(
                DisplayColumn::make('owner')
                    ->addFields([
                        DisplayField::label('currentResponsible.name')->labelText('Responsável'),
                    ])
            )
            ->addColumn(
                DisplayColumn::make('flows')
                    ->addFields([
                        DisplayField::label('templatePreviousStep.name')->labelText('Etapa Anterior/Próxima etapa'),
                        DisplayField::label('templateNextStep.name'),
                    ])
            );

        if ($openUrlResolver) {
            $action = CustomAction::make('open')
                ->label('Abrir Diagramação')
                ->icon('Edit3')
                ->method('get')
                ->url($openUrlResolver);

            if ($openVisibilityResolver) {
                $action->visible($openVisibilityResolver);
            }

            $card->addAction($action);
        }

        return $card;
    }

    protected function isLastExecutionStep(FlowExecution $execution): bool
    {
        return ($this->resolveStepNeighbors($execution->flow_step_template_id)['templateNextStep'] ?? null) === null;
    }

    protected function executionPermissionsResolver(): KanbanExecutionPermissionsResolver
    {
        return $this->executionPermissionsResolver
            ??= app(KanbanExecutionPermissionsResolver::class);
    }

    protected function filterOptionsProvider(): KanbanFilterOptionsProvider
    {
        return $this->filterOptionsProvider
            ??= app(KanbanFilterOptionsProvider::class);
    }
}
