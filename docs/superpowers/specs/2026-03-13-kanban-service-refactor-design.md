# KanbanService Refactor — Design Spec

**Data:** 2026-03-13
**Pacote:** `packages/callcocam/laravel-raptor-flow/src/Services/KanbanService.php`
**Objetivo:** Tornar o `KanbanService` legível, extensível via `addColumn()` e `addAction()`, eliminando código duplicado do `KanbanBoard`.

---

## Contexto

O `KanbanService` atual (~280 linhas) replica lógica que já existe no `KanbanBoard` (`formatConfigStep`, `formatConfigItem`, `formatExecution`). Não expõe nenhum ponto de extensão de colunas ou actions. A subclasse do app (`App\Services\Workflow\KanbanService`) sobrescreve hooks que não existem no pai, tornando-os código morto.

O `KanbanBoard` (`Support/Kanban/KanbanBoard.php`) já implementa corretamente: steps, executions, grouping, columns (`ExecutionColumn`), filtros genéricos, userRoles, e `additionalQuery`. Toda essa lógica deve permanecer lá.

---

## Escopo

**In scope:**
- Refatorar `KanbanService` do pacote para thin wrapper fluente sobre `KanbanBoard`
- Adicionar `addColumn()` e `addAction()` ao `KanbanService`
- Adicionar `getDetailModalConfig()` para montar `DetailModalConfig` a partir das actions registradas
- Atualizar `KanbanServiceTest.php` para cobrir a nova API
- Atualizar `KanbanController` para usar `App\Services\Workflow\KanbanService` corretamente

**Out of scope:**
- Refatorar `App\Services\Workflow\KanbanService` (subclasse do app) — pode acontecer depois
- Alterar `KanbanBoard.php`
- Alterar componentes Vue

---

## Arquitetura

### `KanbanService` — novo design

Thin orchestrator com setters fluentes. Delega `getBoardData()` 100% ao `KanbanBoard`. Acumula columns e actions para expô-los via métodos dedicados.

```php
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

    /** Seções do modal (DetailModalSectionConfig[]) */
    protected array $modalSections = [];

    /** Links do modal (DetailModalLinkConfig[]) */
    protected array $modalLinks = [];

    // ── Fluent setters ──────────────────────────────────────────────
    public function setFlow(Flow $flow): static
    public function setFilters(array $filters): static
    public function setWorkableType(string $class): static
    public function setWorkableIds(Closure|array $ids): static
    public function setGroupConfigs(Closure $resolver): static
    public function setUserRoles(Closure $resolver): static
    public function setAdditionalQuery(Closure $callback): static
    public function withDetailModal(bool $enable = true): static

    public function addColumn(ExecutionColumn $column): static
    public function addAction(FlowAction $action): static
    public function addModalSection(array $section): static
    public function addModalLink(array $link): static

    // ── Output ──────────────────────────────────────────────────────
    public function getBoardData(): array          // delega ao KanbanBoard
    public function getDetailModalConfig(): array  // monta DetailModalConfig
    public function getFilterOptionsData(): array  // stub extensível

    // ── Helpers internos ────────────────────────────────────────────
    protected function buildBoard(): KanbanBoard
    protected function hasFilter(string $key): bool
    protected function getFilter(string $key, mixed $default = null): mixed
}
```

### `getBoardData()` — implementação

`buildBoard()` usa `KanbanBoard::make()` diretamente (não é injetado). Para garantir testabilidade sem banco de dados, os novos testes de `KanbanService` usam a estratégia de **integration test com banco em memória (SQLite)** ou testam apenas `getDetailModalConfig()` e os setters, deixando o comportamento de board coberto pelos testes do `KanbanBoard` (que já existem ou serão criados separadamente).

```php
public function getBoardData(): array
{
    return $this->buildBoard()->getBoardData();
}

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
```

### `getDetailModalConfig()` — implementação

Monta o array `DetailModalConfig` esperado pelo frontend. Actions filtradas por `visibleStatuses` são responsabilidade do frontend; o PHP expõe todas.

> **Restrição de URL:** `getDetailModalConfig()` é chamado sem contexto de execução. Portanto, `FlowAction::url` deve ser uma string estática ou conter placeholders `{param}` que o frontend resolve via `resolveActionUrl()`. Closures de URL **não são suportados** neste contexto — um `FlowAction` com `->url(fn($exec) => ...)` producirá URL vazia.

```php
public function getDetailModalConfig(): array
{
    return [
        'sections' => $this->modalSections,
        'actions'  => array_map(fn(FlowAction $a) => $a->toArray(), $this->actions),
        'links'    => $this->modalLinks,
    ];
}
```

**`addModalSection` / `addModalLink` — shapes esperados (PHPDoc obrigatório na implementação):**

```php
/**
 * @param array{id: string, label?: string, fields: array<array{key: string, type: string, label?: string, placeholder?: string, readOnly?: bool}>} $section
 */
public function addModalSection(array $section): static

/**
 * @param array{key: string, label: string, url: string, external?: bool} $link
 */
public function addModalLink(array $link): static
```

### `hasFilter` / `getFilter` — nota

Esses helpers existem no `KanbanService` apenas para suporte a subclasses que precisam deles em `getFilterOptionsData()` (ex.: `App\Services\Workflow\KanbanService::loadEntityData()`). Não têm relação com as cópias idênticas em `KanbanBoard`.

### `getFilterOptionsData()` — stub

Retorna array vazio por padrão. Subclasses ou closures injetados podem sobrescrever.

---

## Uso típico no controller

```php
// KanbanController.php
$service = app(KanbanService::class)
    ->setFlow($flow)
    ->setFilters($request->only([...]))
    ->setWorkableType(GondolaWorkflow::class)
    ->setWorkableIds(fn() => $gondolasCache->keys()->toArray())
    ->setGroupConfigs(fn() => [...])
    ->addColumn(WorkableColumn::make('workable')->resolveUsing(...))
    ->addColumn(PermissionsColumn::make('permissions')->resolveUsing(...))
    ->addAction(StartAction::make())
    ->addAction(PauseAction::make())
    ->withDetailModal();

return Inertia::render('...', [
    'board'       => $service->getBoardData(),
    'detailModal' => $service->getDetailModalConfig(),
    'filters'     => [
        'values' => $request->only([...]),
        'data'   => Inertia::defer(fn() => $service->getFilterOptionsData()),
    ],
]);
```

---

## O que é removido do `KanbanService` atual

| Método removido | Motivo |
|----------------|--------|
| `formatConfigStep()` | Lógica duplicada — pertence ao `KanbanBoard` |
| `formatConfigItem()` | Idem |
| `formatConfigItemForStep()` | Idem |
| `formatExecution()` | Idem |
| `resolveExecutionForStep()` | Idem |
| `permissionsResolver` (Closure) | Substituído por `PermissionsColumn` |

---

## Compatibilidade com `App\Services\Workflow\KanbanService`

> **Atenção — mudança atômica:** o `KanbanController` atualmente importa o `KanbanService` do **pacote** diretamente (`use Callcocam\LaravelRaptorFlow\Services\KanbanService`). A subclasse do app (`App\Services\Workflow\KanbanService`) com seus hooks de domínio (`loadEntityData`, `buildColumns`, etc.) **já não está sendo usada**. O board retorna resultado do `KanbanService` legado do pacote.

Com o refactor, o `KanbanService` do pacote passa a delegar tudo ao `KanbanBoard` com `workableIdsResolver = null` por padrão — o que produziria um board vazio. Por isso, **a atualização do `KanbanController` é obrigatória no mesmo PR** do refactor do pacote. Não podem ser feitos em separado.

O `KanbanController` deve ser atualizado para injetar `App\Services\Workflow\KanbanService` e configurar `setWorkableIds()`, `setGroupConfigs()`, `addColumn()`, etc. via a nova API fluente. A subclasse do app pode ser mantida ou removida — a decisão é adiada para sprint seguinte.

---

## Testes

### `tests/Unit/KanbanServiceTest.php`
O teste atual testa `formatConfigItemForStep` (método que será removido). Precisa ser reescrito para testar:
- `getBoardData()` retorna estrutura correta quando `KanbanBoard` retorna dados mock
- `getDetailModalConfig()` retorna actions serializadas corretamente
- `addColumn()` passa columns ao `KanbanBoard`

Como os métodos removidos eram a única coisa testada, o arquivo de teste será reescrito do zero cobrindo a nova API pública.

---

## Arquivos afetados

| Arquivo | Mudança |
|---------|---------|
| `packages/.../Services/KanbanService.php` | Reescrito (~80 linhas) |
| `tests/Unit/KanbanServiceTest.php` | Reescrito para nova API |
| `app/Http/Controllers/Plannerate/KanbanController.php` | Atualizado para usar `App\Services\Workflow\KanbanService` |
