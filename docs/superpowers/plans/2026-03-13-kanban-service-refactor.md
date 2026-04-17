# KanbanService Refactor — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite `KanbanService` do pacote como thin fluent wrapper sobre `KanbanBoard`, adicionando `addColumn()` e `addAction()`, e conectar o `KanbanController` ao serviço correto.

**Architecture:** O `KanbanService` do pacote remove ~200 linhas de lógica de formatação duplicada e delega 100% do build de board ao `KanbanBoard` existente. A subclasse do app (`App\Services\Workflow\KanbanService`) adiciona `getBoardData()` que carrega dados de domínio e wira os setters fluentes antes de chamar `parent::getBoardData()`. O `KanbanController` é atualizado para usar o serviço do app com filtros.

**Tech Stack:** PHP 8.5, Laravel 12, Pest v4, pacote `laravel-raptor-flow`

---

## Chunk 1: Reescrever o KanbanService do pacote

### Task 1: Reescrever `KanbanService` do pacote

**Files:**
- Rewrite: `packages/callcocam/laravel-raptor-flow/src/Services/KanbanService.php`

**Contexto:** O arquivo atual tem ~285 linhas com `getBoardData()`, `formatConfigStep()`, `formatConfigItem()`, `formatConfigItemForStep()`, `formatExecution()`, `resolveExecutionForStep()` e `getFilterOptionsData()`. Toda essa lógica de formatação já existe no `KanbanBoard` (`Support/Kanban/KanbanBoard.php`). O novo `KanbanService` será um thin wrapper fluente com ~90 linhas.

Imports necessários:
- `Callcocam\LaravelRaptorFlow\Models\Flow`
- `Callcocam\LaravelRaptorFlow\Support\Actions\FlowAction`
- `Callcocam\LaravelRaptorFlow\Support\Kanban\KanbanBoard`
- `Callcocam\LaravelRaptorFlow\Support\Kanban\Columns\ExecutionColumn`
- `Closure`

- [ ] **Step 1.1: Substituir o conteúdo do `KanbanService.php`**

Reescrever o arquivo inteiro com o seguinte conteúdo:

```php
<?php

namespace Callcocam\LaravelRaptorFlow\Services;

use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Support\Actions\FlowAction;
use Callcocam\LaravelRaptorFlow\Support\Kanban\KanbanBoard;
use Callcocam\LaravelRaptorFlow\Support\Kanban\Columns\ExecutionColumn;
use Closure;

/**
 * Thin wrapper fluente sobre KanbanBoard.
 *
 * Responsabilidades:
 *   - Acumular configuração via setters fluentes
 *   - Delegar a construção do board ao KanbanBoard
 *   - Serializar actions para getDetailModalConfig()
 *
 * Para extensão de domínio, sobrescreva getBoardData() na subclasse:
 *   1. Carregue dados de domínio
 *   2. Configure os setters fluentes (setWorkableType, setWorkableIds, addColumn, etc.)
 *   3. Chame parent::getBoardData()
 */
class KanbanService
{
    protected ?Flow $flow = null;

    protected string $workableType = '';

    protected array $filters = [];

    protected bool $withDetailModal = false;

    protected Closure|array|null $workableIdsResolver = null;

    protected ?Closure $groupConfigsResolver = null;

    protected ?Closure $userRolesResolver = null;

    protected ?Closure $additionalQueryCallback = null;

    /** @var ExecutionColumn[] */
    protected array $columns = [];

    /** @var FlowAction[] */
    protected array $actions = [];

    /** @var array<array{id: string, label?: string, fields: array<array{key: string, type: string, label?: string}>}> */
    protected array $modalSections = [];

    /** @var array<array{key: string, label: string, url: string, external?: bool}> */
    protected array $modalLinks = [];

    // ── Fluent setters ────────────────────────────────────────────────────────

    public function setFlow(Flow $flow): static
    {
        $this->flow = $flow;

        return $this;
    }

    public function setFilters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function setWorkableType(string $class): static
    {
        $this->workableType = $class;

        return $this;
    }

    /**
     * Define os IDs dos workables a serem exibidos no board.
     * Pode ser um array direto ou um Closure que retorna array.
     */
    public function setWorkableIds(Closure|array $ids): static
    {
        $this->workableIdsResolver = $ids;

        return $this;
    }

    public function setGroupConfigs(Closure $resolver): static
    {
        $this->groupConfigsResolver = $resolver;

        return $this;
    }

    public function setUserRoles(Closure $resolver): static
    {
        $this->userRolesResolver = $resolver;

        return $this;
    }

    public function setAdditionalQuery(Closure $callback): static
    {
        $this->additionalQueryCallback = $callback;

        return $this;
    }

    public function withDetailModal(bool $enable = true): static
    {
        $this->withDetailModal = $enable;

        return $this;
    }

    public function addColumn(ExecutionColumn $column): static
    {
        $this->columns[] = $column;

        return $this;
    }

    /**
     * Registra uma action para o modal de detalhes.
     *
     * A URL da action deve ser uma string estática ou conter placeholders {param}
     * que o frontend resolve via resolveActionUrl(). Closures de URL não são
     * suportados neste contexto (getDetailModalConfig é chamado sem execução).
     */
    public function addAction(FlowAction $action): static
    {
        $this->actions[] = $action;

        return $this;
    }

    /**
     * @param array{id: string, label?: string, fields: array<array{key: string, type: string, label?: string, placeholder?: string, readOnly?: bool}>} $section
     */
    public function addModalSection(array $section): static
    {
        $this->modalSections[] = $section;

        return $this;
    }

    /**
     * @param array{key: string, label: string, url: string, external?: bool} $link
     */
    public function addModalLink(array $link): static
    {
        $this->modalLinks[] = $link;

        return $this;
    }

    // ── Output ────────────────────────────────────────────────────────────────

    /**
     * Constrói e retorna os dados do board delegando ao KanbanBoard.
     */
    public function getBoardData(): array
    {
        return $this->buildBoard()->getBoardData();
    }

    /**
     * Monta o DetailModalConfig para o frontend.
     *
     * As actions são serializadas sem contexto de execução — use placeholders
     * {param} nas URLs em vez de Closures.
     *
     * @return array{sections: array<mixed>, actions: array<mixed>, links: array<mixed>}
     */
    public function getDetailModalConfig(): array
    {
        return [
            'sections' => $this->modalSections,
            'actions'  => array_map(fn(FlowAction $a) => $a->toArray(), $this->actions),
            'links'    => $this->modalLinks,
        ];
    }

    /**
     * Stub extensível para opções dos filtros.
     * Subclasses devem sobrescrever para retornar os dados reais.
     */
    public function getFilterOptionsData(): array
    {
        return [];
    }

    // ── Helpers para subclasses ───────────────────────────────────────────────

    /**
     * Verifica se um filtro está definido e não vazio.
     * Mantido para uso em subclasses (ex: App\Services\Workflow\KanbanService).
     */
    protected function hasFilter(string $key): bool
    {
        return isset($this->filters[$key])
            && $this->filters[$key] !== ''
            && $this->filters[$key] !== null;
    }

    /**
     * Retorna o valor de um filtro ou o default.
     * Mantido para uso em subclasses (ex: App\Services\Workflow\KanbanService).
     */
    protected function getFilter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    // ── Interno ───────────────────────────────────────────────────────────────

    protected function buildBoard(): KanbanBoard
    {
        $board = KanbanBoard::make()
            ->flow($this->flow)
            ->workableType($this->workableType)
            ->filters($this->filters)
            ->withDetailModal($this->withDetailModal)
            ->columns($this->columns);

        if ($this->workableIdsResolver !== null) {
            $board->workableIds($this->workableIdsResolver);
        }

        if ($this->groupConfigsResolver !== null) {
            $board->groupConfigs($this->groupConfigsResolver);
        }

        if ($this->userRolesResolver !== null) {
            $board->userRoles($this->userRolesResolver);
        }

        if ($this->additionalQueryCallback !== null) {
            $board->additionalQuery($this->additionalQueryCallback);
        }

        return $board;
    }
}
```

- [ ] **Step 1.2: Rodar Pint**

```bash
docker compose exec laravel.test vendor/bin/pint --dirty packages/callcocam/laravel-raptor-flow/src/Services/KanbanService.php
```

Esperado: `PASS` sem alterações (ou formatação corrigida sem erros).

---

### Task 2: Reescrever `KanbanServiceTest.php`

**Files:**
- Rewrite: `tests/Unit/KanbanServiceTest.php`

**Contexto:** O teste atual testa `formatConfigItemForStep` que foi removido. O novo teste cobre a API pública do `KanbanService` refatorado: registro de columns, actions, e serialização de `getDetailModalConfig()`. Como `getBoardData()` delega ao `KanbanBoard` (que acessa o banco), testamos apenas os métodos que não precisam de banco.

- [ ] **Step 2.1: Substituir o conteúdo do `KanbanServiceTest.php`**

```php
<?php

use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Services\KanbanService;
use Callcocam\LaravelRaptorFlow\Support\Actions\StartAction;
use Callcocam\LaravelRaptorFlow\Support\Actions\PauseAction;
use Callcocam\LaravelRaptorFlow\Support\Kanban\Columns\ExecutionColumn;
use Callcocam\LaravelRaptorFlow\Support\Kanban\Columns\Types\WorkableColumn;

// ── addColumn ────────────────────────────────────────────────────────────────

it('accumulates columns via addColumn', function () {
    $service = new KanbanService;

    $col1 = WorkableColumn::make('workable');
    $col2 = WorkableColumn::make('permissions');

    $service->addColumn($col1)->addColumn($col2);

    $reflection = new ReflectionProperty(KanbanService::class, 'columns');
    $columns = $reflection->getValue($service);

    expect($columns)->toHaveCount(2)
        ->and($columns[0])->toBe($col1)
        ->and($columns[1])->toBe($col2);
});

it('returns the same instance from addColumn for fluent chaining', function () {
    $service = new KanbanService;
    $result = $service->addColumn(WorkableColumn::make('workable'));

    expect($result)->toBe($service);
});

// ── addAction + getDetailModalConfig ─────────────────────────────────────────

it('serializes registered actions in getDetailModalConfig', function () {
    $service = new KanbanService;

    $service
        ->addAction(new StartAction)
        ->addAction(new PauseAction);

    $config = $service->getDetailModalConfig();

    expect($config)->toHaveKeys(['sections', 'actions', 'links'])
        ->and($config['actions'])->toHaveCount(2)
        ->and($config['actions'][0])->toHaveKey('id')
        ->and($config['actions'][1])->toHaveKey('id');
});

it('returns empty sections and links by default in getDetailModalConfig', function () {
    $service = new KanbanService;

    $config = $service->getDetailModalConfig();

    expect($config['sections'])->toBe([])
        ->and($config['links'])->toBe([]);
});

it('includes modal sections added via addModalSection', function () {
    $service = new KanbanService;

    $service->addModalSection([
        'id'     => 'info',
        'label'  => 'Informações',
        'fields' => [
            ['key' => 'notes', 'type' => 'textarea', 'label' => 'Notas'],
        ],
    ]);

    $config = $service->getDetailModalConfig();

    expect($config['sections'])->toHaveCount(1)
        ->and($config['sections'][0]['id'])->toBe('info');
});

it('includes modal links added via addModalLink', function () {
    $service = new KanbanService;

    $service->addModalLink([
        'key'   => 'edit',
        'label' => 'Editar',
        'url'   => '/planograms/{workable.id}/edit',
    ]);

    $config = $service->getDetailModalConfig();

    expect($config['links'])->toHaveCount(1)
        ->and($config['links'][0]['key'])->toBe('edit');
});

// ── Fluent setters ────────────────────────────────────────────────────────────

it('returns the same instance from setFlow for fluent chaining', function () {
    $service = new KanbanService;
    $flow = new Flow;

    expect($service->setFlow($flow))->toBe($service);
});

it('returns the same instance from setFilters for fluent chaining', function () {
    $service = new KanbanService;

    expect($service->setFilters(['status' => 'pending']))->toBe($service);
});

it('returns the same instance from withDetailModal for fluent chaining', function () {
    $service = new KanbanService;

    expect($service->withDetailModal())->toBe($service);
});

// ── getFilterOptionsData stub ──────────────────────────────────────────────────

it('returns empty array from getFilterOptionsData by default', function () {
    $service = new KanbanService;

    expect($service->getFilterOptionsData())->toBe([]);
});
```

- [ ] **Step 2.2: Rodar os testes**

```bash
docker compose exec laravel.test php artisan test --compact tests/Unit/KanbanServiceTest.php
```

Esperado: todos os testes passando. `StartAction::make()` e `PauseAction::make()` precisam existir — se não existirem, o erro será `Class not found`. Verificar com:

```bash
ls packages/callcocam/laravel-raptor-flow/src/Support/Actions/
```

- [ ] **Step 2.3: Rodar Pint**

```bash
docker compose exec laravel.test vendor/bin/pint --dirty tests/Unit/KanbanServiceTest.php
```

- [ ] **Step 2.4: Commit do Chunk 1**

```bash
git add packages/callcocam/laravel-raptor-flow/src/Services/KanbanService.php \
        tests/Unit/KanbanServiceTest.php
git commit -m "$(cat <<'EOF'
refactor: rewrite KanbanService as thin fluent wrapper over KanbanBoard

Remove ~200 lines of duplicated formatting logic (formatConfigStep,
formatConfigItem, formatExecution, resolveExecutionForStep). KanbanService
now delegates getBoardData() entirely to KanbanBoard and exposes
addColumn() and addAction() as extension points. getDetailModalConfig()
serializes registered FlowActions for the frontend DetailModal.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Chunk 2: Conectar o App ao novo KanbanService

> **Pré-requisito:** Chunk 1 (Tasks 1-2) deve ter sido executado com sucesso.
> Após o Chunk 1, `packages/callcocam/laravel-raptor-flow/src/Services/KanbanService.php` é o **thin wrapper fluente** com os setters `setWorkableType()`, `setWorkableIds()`, `setGroupConfigs()`, `addColumn()`, `setAdditionalQuery()`, `setUserRoles()`. Seu `getBoardData()` chama `$this->buildBoard()->getBoardData()` e retorna o shape `{ board: {...}, groupConfigs, userRoles, filters }` do `KanbanBoard`.

### Task 3: Atualizar `App\Services\Workflow\KanbanService`

**Files:**
- Modify: `app/Services/Workflow/KanbanService.php`

**Contexto:** A subclasse do app já tem toda a lógica de domínio implementada em métodos protegidos (`loadEntityData`, `buildColumns`, `buildAdditionalQuery`, `buildUserRoles`, `getGroupConfigs`). O único problema é que nenhum deles é chamado — falta um `getBoardData()` que os wire via os setters fluentes do pai (disponíveis após Chunk 1).

A subclasse não precisa mudar nenhuma lógica existente. Apenas adiciona `getBoardData()` que:
1. Chama `loadEntityData()` para popular os caches de gôndolas e planogramas
2. Configura os setters fluentes do pai com os dados de domínio
3. Chama `parent::getBoardData()`

- [ ] **Step 3.1: Adicionar `getBoardData()` na subclasse**

No arquivo `app/Services/Workflow/KanbanService.php`, adicionar o método após `forPlanogram()` (linha ~51), antes da seção de template-method hooks.

> **Nota sobre `getGroupConfigs()`:** O método usa `$this->allPlanogramsCache` que é uma `Collection` de `PlanogramWorkflow`. O closure de `map()` tem a assinatura `fn(Planogram $planogram)` — isso é uma anotação de tipo apenas; em PHP, closures não validam tipos em runtime, e `PlanogramWorkflow` tem as propriedades `id` e `name` necessárias. Comportamento pré-existente, não introduzido aqui.

> **Nota sobre `getFilterOptionsData()` no defer:** A subclasse já trata o caso de cache nula em `getFilterOptionsData()` (linha 288). Em requests Inertia deferred, o método `index()` do controller re-executa completamente, chamando `setFlow()` e `setFilters()` antes de qualquer closure ser avaliada — portanto `$this->flow` e `$this->filters` estão corretos no momento de avaliação do defer.

```php
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
    $this->setGroupConfigs(fn() => $this->getGroupConfigs());

    foreach ($this->buildColumns() as $column) {
        $this->addColumn($column);
    }

    if ($query = $this->buildAdditionalQuery()) {
        $this->setAdditionalQuery($query);
    }

    if ($roles = $this->buildUserRoles()) {
        $this->setUserRoles($roles);
    }

    return parent::getBoardData();
}
```

- [ ] **Step 3.2: Verificar que não há erro de sintaxe**

```bash
docker compose exec laravel.test php -l app/Services/Workflow/KanbanService.php
```

Esperado: `No syntax errors detected`

- [ ] **Step 3.3: Rodar Pint**

```bash
docker compose exec laravel.test vendor/bin/pint --dirty app/Services/Workflow/KanbanService.php
```

---

### Task 4: Atualizar `KanbanController`

**Files:**
- Modify: `app/Http/Controllers/Plannerate/KanbanController.php`

**Contexto:** O controller atualmente injeta `Callcocam\LaravelRaptorFlow\Services\KanbanService` (pacote), ignorando toda a lógica de domínio da subclasse do app. Precisa:
1. Trocar o import para `App\Services\Workflow\KanbanService`
2. Passar `setFilters()` com os filtros da request (atualmente ausente)

- [ ] **Step 4.1: Atualizar o import e o `index()`**

Trocar o arquivo `app/Http/Controllers/Plannerate/KanbanController.php`:

**Import a remover:**
```php
use Callcocam\LaravelRaptorFlow\Services\KanbanService;
```

**Import a adicionar:**
```php
use App\Services\Workflow\KanbanService;
```

**Método `index()` atualizado** — adicionar `setFilters()` antes de `getBoardData()` e destruturar o resultado:

> **Atenção:** `KanbanBoard::getBoardData()` retorna `{ board: {steps, executions}, groupConfigs, userRoles, filters }`.
> O controller deve destruturar esse resultado em props separadas para o frontend.
> A página Vue `resources/js/pages/admin/tenant/plannerates/kanbans/index.vue` já espera `board`, `groupConfigs`, `userRoles` como props **separadas** via `KanbanIndexProps` — o frontend está correto, é o controller que está errado atualmente.

```php
public function index(Request $request, Flow $flow): Response
{
    $filters = $this->buildFilters($request);

    $service = $this->kanbanService
        ->setFlow($flow)
        ->setFilters($filters);

    $boardData = $service->getBoardData();

    return Inertia::render('admin/tenant/plannerates/kanbans/index', [
        'message'              => 'Visualização Kanban dos Planogramas',
        'resourceName'         => 'kanban',
        'resourcePluralName'   => 'kanbans',
        'resourceLabel'        => 'Kanban',
        'resourcePluralLabel'  => 'Kanban',
        'maxWidth'             => 'full',
        'planogramIdForCreate' => null,
        'breadcrumbs'          => $this->buildBreadcrumbs($request),
        'board'                => $boardData['board'] ?? [],
        'groupConfigs'         => $boardData['groupConfigs'] ?? [],
        'userRoles'            => $boardData['userRoles'] ?? [],
        'filters'              => [
            'values' => $filters,
            'data'   => Inertia::defer(fn() => $service->getFilterOptionsData()),
        ],
    ]);
}
```

- [ ] **Step 4.2: Verificar sintaxe**

```bash
docker compose exec laravel.test php -l app/Http/Controllers/Plannerate/KanbanController.php
```

Esperado: `No syntax errors detected`

- [ ] **Step 4.3: Rodar Pint**

```bash
docker compose exec laravel.test vendor/bin/pint --dirty \
    app/Services/Workflow/KanbanService.php \
    app/Http/Controllers/Plannerate/KanbanController.php
```

- [ ] **Step 4.4: Rodar todos os testes afetados**

```bash
docker compose exec laravel.test php artisan test --compact tests/Unit/KanbanServiceTest.php
```

Esperado: todos passando.

- [ ] **Step 4.5: Commit do Chunk 2**

```bash
git add app/Services/Workflow/KanbanService.php \
        app/Http/Controllers/Plannerate/KanbanController.php
git commit -m "$(cat <<'EOF'
fix: wire KanbanController to app KanbanService with domain logic

KanbanController now injects App\Services\Workflow\KanbanService instead
of the package base class, activating the domain hooks (loadEntityData,
buildColumns, getGroupConfigs, etc.) that were previously dead code.

App KanbanService gains getBoardData() that loads entity data and
configures the fluent setters before delegating to parent::getBoardData().
Also passes filters from request to setFilters() so domain filtering
(planogram_id, loja_id, etc.) works correctly.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Verificação Final

- [ ] **Rodar suite completa de testes**

```bash
docker compose exec laravel.test php artisan test --compact
```

Se houver falhas relacionadas a `KanbanService` ou `KanbanController`, investigar e corrigir antes de considerar concluído.

- [ ] **Verificar Pint em todos os arquivos modificados**

```bash
docker compose exec laravel.test vendor/bin/pint --dirty
```

Esperado: `PASS` sem arquivos modificados.
