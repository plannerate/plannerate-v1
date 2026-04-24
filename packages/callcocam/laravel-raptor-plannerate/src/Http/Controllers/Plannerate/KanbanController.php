<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Plannerate;

use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Support\Actions\CustomAction;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Policies\FlowExecutionPolicy as AppFlowExecutionPolicy;
use Callcocam\LaravelRaptorPlannerate\Services\Workflow\KanbanService;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller responsável pela visualização Kanban dos planogramas.
 *
 * Monta o board Kanban a partir de um Flow (fluxo de trabalho),
 * aplicando filtros, permissões por responsável e role, e ações
 * contextuais nos cards (start, pause, resume, abandon, notes, PDF).
 */
class KanbanController extends Controller
{
    public function __construct(
        protected KanbanService $kanbanService
    ) {}

    /**
     * Exibe o board Kanban para o fluxo informado.
     *
     * Configura o KanbanService com:
     * - Filtros extraídos da query string (planograma, loja, usuário, status, etc.)
     * - Modal de detalhe com URLs de ação (start/pause/resume/abandon/notes)
     * - Card builder com link para edição da gôndola e ação "Ver PDF"
     * - Abas de navegação entre Lista, Kanban e Maps
     *
     * Dados de filtro são carregados via Inertia::defer para não bloquear o render inicial.
     */
    public function index(Request $request, Flow $flow): Response
    {
        $filters = $this->buildFilters($request);

        $service = $this->kanbanService
            ->setFlow($flow)
            ->forPlanogram($filters['planogram_id'] ?? null)
            ->withDetailModal()
            ->modal($this->kanbanService->buildDefaultModalBuilder([
                'start' => $this->gondolaActionUrl('flow.execution.start', 'execution', 'id'),
                'pause' => $this->gondolaActionUrl('flow.execution.pause', 'execution', 'id'),
                'resume' => $this->gondolaActionUrl('flow.execution.resume', 'execution', 'id'),
                'abandon' => $this->gondolaActionUrl('flow.execution.abandon', 'execution', 'id'),
                'finish' => $this->gondolaActionUrl('flow.execution.finish', 'execution', 'id'),
                'notes' => $this->gondolaActionUrl('flow.execution.notes', 'execution', 'id'),
            ]))
            ->card($this->kanbanService->buildDefaultCardBuilder(
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
            ))
            ->setFilters($filters);

        $boardData = $service->getBoardData();

        return Inertia::render('admin/tenant/plannerates/kanbans/index', [
            'message' => 'Visualização Kanban dos Planogramas',
            'resourceName' => 'kanban',
            'resourcePluralName' => 'kanbans',
            'resourceLabel' => 'Kanban',
            'resourcePluralLabel' => 'Kanban',
            'maxWidth' => 'full',
            'planogramIdForCreate' => null,
            'breadcrumbs' => $this->buildBreadcrumbs($request),
            'board' => $boardData['board'],
            'groupConfigs' => $boardData['groupConfigs'] ?? [],
            'userRoles' => $boardData['userRoles'] ?? [],
            'cardConfig' => $boardData['cardConfig'] ?? null,
            'detailModalConfig' => $service->getDetailModalConfig(),
            'filters' => [
                'values' => $filters,
                'data' => Inertia::defer(fn () => $service->getFilterOptionsData()),
            ],
            'tabs' => [
                ['key' => 'lista', 'label' => 'Lista', 'href' => '/planograms', 'icon' => 'LayoutListIcon', 'active' => false],
                ['key' => 'kanban', 'label' => 'Kanban', 'href' => '/kanbans/planogramas', 'icon' => 'KanbanIcon', 'active' => true],
                ['key' => 'maps', 'label' => 'Maps', 'href' => '/maps', 'icon' => 'MapIcon', 'active' => false],
            ],
        ]);
    }

    /**
     * Extrai os filtros da query string do request.
     *
     * @return array{
     *     planogram_id: string|null,
     *     loja_id: string|null,
     *     user_id: string|null,
     *     assigned_to: string|null,
     *     status: string|null,
     *     only_overdue: bool,
     *     show_completed: bool,
     * }
     */
    protected function buildFilters(Request $request): array
    {
        return [
            'planogram_id' => $request->input('planogram_id'),
            'loja_id' => $request->input('loja_id'),
            'user_id' => $request->input('user_id'),
            'assigned_to' => $request->input('assigned_to'),
            'status' => $request->input('status'),
            'only_overdue' => $request->boolean('only_overdue'),
            'show_completed' => $request->boolean('show_completed'),
        ];
    }

    /**
     * Monta os breadcrumbs da página Kanban.
     *
     * Base fixa: Dashboard → Planogramas → Kanban.
     * Se um planogram_id foi informado no filtro, acrescenta o nome
     * do planograma com link direto para sua edição.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    protected function buildBreadcrumbs(Request $request): array
    {
        $breadcrumbs = [
            ['label' => 'Painel de controle', 'url' => route('dashboard', [], false)],
            ['label' => 'Planogramas',         'url' => route('tenant.planograms.index', [], false)],
            ['label' => 'Kanban',              'url' => null],
        ];

        if ($planogramId = $request->input('planogram_id')) {
            $planogram = Planogram::find($planogramId);
            if ($planogram) {
                $breadcrumbs[] = [
                    'label' => $planogram->name,
                    'url' => route('tenant.planograms.edit', ['record' => $planogram->id], false),
                ];
            }
        }

        return $breadcrumbs;
    }

    /**
     * Gera um template de URL com placeholder {executionField} para ações de gôndola.
     *
     * Usa um marcador URL-safe para evitar que route() encode as chaves { }.
     * Exemplo: gondolaActionUrl('gondola.workflow.start') → '/workflow/gondola/{workable.id}/start'
     *
     * @param  string  $routeName  Nome da rota Laravel
     * @param  string  $routeParam  Parâmetro da rota (ex: 'gondola', 'execution')
     * @param  string  $placeholder  Campo da execução no frontend (ex: 'workable.id', 'id')
     */
    protected function gondolaActionUrl(
        string $routeName,
        string $routeParam = 'gondola',
        string $placeholder = 'workable.id'
    ): string {
        $marker = '__PLACEHOLDER__';
        $generated = route($routeName, [$routeParam => $marker], false);

        return str_replace($marker, "{{$placeholder}}", $generated);
    }

    /**
     * Verifica se o usuário autenticado pode abrir (editar) a gôndola vinculada à execução.
     *
     * Cadeia de 5 verificações sequenciais — todas devem passar:
     * 1. Sessão ativa e execução existente
     * 2. Responsável atual é o usuário logado
     * 3. Role do usuário é compatível com o step atual do fluxo
     * 4. Workable (gôndola) existe no banco
     * 5. Usuário tem permissão de update no planograma pai
     */
    protected function canOpenExecutionWorkable(?FlowExecution $execution): bool
    {
        $user = auth()->user();

        // 1. Usuário autenticado e execução existem — sem sessão ou sem execução, nega
        if (! $user || ! $execution) {
            return false;
        }

        // 2. Responsável atual — impede que outro membro abra a gôndola de quem está trabalhando nela
        if ($execution->current_responsible_id !== $user->id) {
            return false;
        }

        // 3. Gate de role — verifica se o papel do usuário é compatível com o step atual do fluxo (ex.: "designer" no passo de design)
        if (! AppFlowExecutionPolicy::passesRoleGate($user, $execution)) {
            return false;
        }

        // 4. Gôndola resolvível — a execução deve ter um workable do tipo Gondola válido
        $gondola = $this->resolveExecutionWorkable($execution);

        if (! $gondola) {
            return false;
        }

        // 5. Permissão de update no planograma — via Policy; withoutGlobalScopes evita bloqueio por tenant/client scoping
        $planogram = Planogram::withoutGlobalScopes()->find($gondola->planogram_id);

        return $planogram ? $user->can('update', $planogram) : false;
    }

    /**
     * Retorna a URL de edição da gôndola vinculada à execução.
     *
     * Usa o accessor `route_gondolas` do model Gondola, que gera
     * o link direto para o editor visual (ex.: /planograms/{id}/gondolas/{id}/edit).
     */
    protected function resolveExecutionWorkableUrl(?FlowExecution $execution): ?string
    {
        return $this->resolveExecutionWorkable($execution)?->route_gondolas;
    }

    /**
     * Abre a visualização em uma nova aba (preview/PDF)
     * Rota: /export/gondola/{gondola}/view (Wayfinder)
     */
    protected function resolveGondolaPdfUrl(?FlowExecution $execution): ?string
    {
        $gondola = $this->resolveExecutionWorkable($execution);

        if (! $gondola) {
            return null;
        }

        return route('export.gondola.view', ['gondola' => $gondola->id], false);
    }

    /**
     * Resolve o model workable (polimórfico) vinculado à execução do fluxo.
     *
     * Verificações defensivas antes do find:
     * 1. Execução não nula
     * 2. workable_type preenchido — execuções órfãs não quebram
     * 3. Classe existe no classmap — evita erro fatal se o model foi removido/renomeado
     *
     * @return Model|null Geralmente retorna uma Gondola, ou null se qualquer verificação falhar
     */
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
