# Plano de implementação — Conclusão em Execução Loja + Revisão Periódica automática

> **Para o executor (outro chat):** este documento é autossuficiente. Leia inteiro
> antes de começar. Siga as decisões travadas e as regras do projeto. Implemente por
> fases, rodando os testes de cada fase antes de seguir. NÃO faça `migrate:fresh`.

## Contexto e regra de negócio

Hoje o fluxo de workflow é **linear e por gôndola**: cada `WorkflowGondolaExecution`
percorre 7 etapas (`WorkflowTemplate` ordenados por `suggested_order`) e só pode ser
"concluída" na **última etapa não pulada** (`WorkflowExecutionPolicy::isAtLastWorkflowStep`,
hoje = Revisão Periódica, ordem 7). Não existe conceito de "planograma concluído".

**Nova regra:**
1. A conclusão do fluxo passa a ser na etapa **Execução Loja** (ordem 6).
2. **Revisão Periódica** (ordem 7) deixa de encerrar o fluxo — vira etapa **pós-conclusão automática**.
3. Quando vence o período de análise, o planograma é promovido automaticamente a Revisão Periódica.

## Decisões travadas (confirmadas com o cliente)

| # | Decisão |
|---|---|
| 1 | **Granularidade: por planograma.** Planograma vira `completed` quando **todas as gôndolas não puladas** concluem a etapa final de fluxo. O gatilho reabre **todas** as gôndolas na etapa Revisão Periódica. |
| 2 | **Vencimento:** `periodic_review_due_at = completed_at + (end_date − start_date)`. |
| 3 | **Retroativo: NÃO.** Zero alteração em planogramas/execuções já finalizados. Vale só para conclusões a partir do deploy. |
| 4 | **Sem `start_date`/`end_date` válidos (ou `end < start`):** fica `completed`, **não agenda** revisão, loga. |
| — | **Conclusão = etapa marcada como `flow` final** (não hardcodar ordem 6), via novo `stage_type`. |
| — | **Idempotência:** gatilho guardado por `lifecycle_status` + `periodic_review_started_at`; `withoutOverlapping()`. |
| — | **Auditoria:** cada transição automática grava `WorkflowHistory` (ação nova `PeriodicReviewTriggered`, actor = sistema) e notifica os responsáveis. |

## Regras do projeto que o executor DEVE seguir

- **Tudo via Docker.** Nunca PHP/artisan direto:
  - `docker compose exec php php artisan <cmd>`
  - Migração tenant: `docker compose exec php php artisan tenants:artisan "migrate --database=tenant"`
  - **NUNCA** `migrate:fresh` (destrói dados reais).
  - Pint ao final: `docker compose exec php vendor/bin/pint --dirty --format agent`
  - Build front: `VITE_ENABLE_WAYFINDER=false npm run build` (host). NÃO rodar `npm run build` sem o env; NÃO rodar `wayfinder:generate` (escrever actions manualmente se preciso).
  - Testes: `docker compose exec php php artisan test --compact --filter=<Nome>`
- **Models tenant**: traits `BelongsToTenant, UsesTenantConnection, HasUlids, SoftDeletes`. **Nunca** passar `tenant_id` manualmente.
- **Migração tenant**: `protected $connection = 'tenant';` + `Schema::table(...)`. Colunas `string`/`timestamp` simples **não** precisam de guard pgsql (funcionam no SQLite dos testes). Backfill de dados existentes dentro do `up()`.
- **Sem texto hardcoded** no front/PHP: usar `useT()` no Vue e chaves em `lang/pt_BR/...`.
- **HTTP no front**: `router`/`useHttp` do Inertia — nunca axios/fetch.
- **Enums**: chaves TitleCase, string-backed.
- **Tenant de teste**: slug **`alberti`** (o CLAUDE.md diz "albert", está errado), tenant_id `01jym02qk8n1cwdq2hd5drpgsz`. Em tinker use `-e HOME=/tmp`.
- **Caveat de testes**: alguns testes tenant (`WorkflowPlanogramSettingsTest`) falham no ambiente local de dev por não migrarem o schema do tenant `:memory:` (problema pré-existente, não regressão). `WorkflowKanbanControllerTest` migra (`migrateTenantSchema()`) e é confiável. Ao asserir persistência em conexão tenant, prefira reconsultar via model/JSON em vez de `assertDatabaseHas` (que usa a conexão default).

## Padrões existentes para espelhar

- **Campo enum por etapa com fallback template→step**: o recém-criado `access_mode`. Replicar exatamente para `stage_type`:
  - Migração: [database/migrations/2026_06_25_120000_add_access_mode_to_workflow_tables.php](database/migrations/2026_06_25_120000_add_access_mode_to_workflow_tables.php)
  - Enum: [app/Enums/WorkflowAccessMode.php](app/Enums/WorkflowAccessMode.php)
  - Cast no template + fillable: [app/Models/WorkflowTemplate.php](app/Models/WorkflowTemplate.php)
  - Accessor com fallback no step: [app/Models/WorkflowPlanogramStep.php](app/Models/WorkflowPlanogramStep.php) (`getAccessModeAttribute`)
  - Seed: `WorkflowTemplate::getDefaultTemplates()` + `WorkflowTemplateController::seedDefaultTemplates`
- **Command tenant-aware** (loop de tenants + `$tenant->execute()`): [app/Console/Commands/LinkSalesProductsCommand.php](app/Console/Commands/LinkSalesProductsCommand.php).
- **Scheduler**: [routes/console.php](routes/console.php) (usar `Schedule::command(...)->dailyAt(...)->withoutOverlapping()->name(...)`).
- **Notificação**: `WorkflowKanbanService::requestAbandonment` usa `AppNotification` — espelhar.
- **Histórico**: `WorkflowKanbanService::recordHistory`.

## Modelos/relações relevantes (já existentes)

- `App\Models\Planogram` (extends pacote `EditorPlanogram`): tem `gondolas()` (herdado do pacote), `workflowSteps(): HasMany<WorkflowPlanogramStep>`, `store()`, campos `start_date`/`end_date` (date, nullable), `status` (draft|published — **publicação, NÃO mexer**).
- `App\Models\WorkflowPlanogramStep`: pertence a um `Planogram` e a um `WorkflowTemplate`; tem `executions(): HasMany`, accessor `suggested_order` (do template), `is_skipped`.
- `App\Models\WorkflowGondolaExecution`: `status` (enum), `completed_at`, `started_at`, `current_responsible_id`, `execution_started_by`; relações `gondola()`, `step()`.

---

# FASE 1 — Dados (migração, enums, seed)

### 1.1 Enums novos
- `app/Enums/WorkflowStageType.php` (string-backed): `Flow = 'flow'`, `PeriodicReview = 'periodic_review'`. Método `isPeriodicReview(): bool`. Docblock PT-BR.
- `app/Enums/PlanogramLifecycleStatus.php`: `InProgress = 'in_progress'`, `Completed = 'completed'`, `PeriodicReview = 'periodic_review'`. Docblock PT-BR.

### 1.2 Novo valor no histórico
- `app/Enums/WorkflowHistoryAction.php`: adicionar `case PeriodicReviewTriggered = 'periodic_review_triggered';`.

### 1.3 Migração tenant (uma só), espelhando a do `access_mode`
Arquivo `database/migrations/2026_06_26_000000_add_periodic_review_to_workflow_and_planograms.php`, `protected $connection = 'tenant'`:
- `workflow_templates`: `stage_type` string `default('flow')` após `access_mode`.
- `workflow_planogram_steps`: `stage_type` string **nullable** após `access_mode` (override por etapa).
- `planograms`:
  - `lifecycle_status` string `default('in_progress')` (após `status`).
  - `completed_at` timestamp nullable.
  - `periodic_review_due_at` timestamp nullable.
  - `periodic_review_started_at` timestamp nullable.
  - índice composto `(lifecycle_status, periodic_review_due_at)` para o job.
- **Backfill no `up()`** (DECISÃO 3 = não-retroativo, então backfill apenas estrutural, sem reabrir nada):
  - `workflow_templates`: `where('suggested_order', '>=', 7)->update(['stage_type' => 'periodic_review'])` (etapa 7+ = revisão periódica; demais ficam `flow` pelo default).
  - `planograms`: deixar todos `in_progress` (default). **NÃO** tentar inferir `completed_at` de planogramas antigos (decisão: histórico intacto). Os já "finalizados" na etapa 7 permanecem como estão; não serão promovidos nem alterados.
- `down()`: dropar as colunas adicionadas.

> Rodar: `docker compose exec php php artisan tenants:artisan "migrate --database=tenant"`.

### 1.4 Models
- `WorkflowTemplate`: `stage_type` no `$fillable` + cast `WorkflowStageType::class`.
- `WorkflowPlanogramStep`: `stage_type` no `$fillable` + accessor `getStageTypeAttribute(?string $value): WorkflowStageType` com fallback template→`Flow` (igual ao `getAccessModeAttribute`).
- `Planogram`: `lifecycle_status`, `completed_at`, `periodic_review_due_at`, `periodic_review_started_at` no `$fillable`; casts (`lifecycle_status` => enum; os três timestamps => `datetime`).

### 1.5 Seed
- `WorkflowTemplate::getDefaultTemplates()`: adicionar `'stage_type'` a cada item — `flow` nas ordens 1–6, `periodic_review` na ordem 7.
- `WorkflowTemplateController::seedDefaultTemplates`: persistir `stage_type` ao criar (igual ao `access_mode`).
- `WorkflowPlanogramStepService::syncForPlanogram` e `loadDefaultSettingsForPlanogram`: ao criar steps a partir do template, **não** precisa setar `stage_type` (fica null → herda do template via accessor). Confirmar que `settingsForPlanogram` carrega `template:...,stage_type` no `with()` (adicionar `stage_type` ao select do template, como foi feito com `access_mode`).

### Testes Fase 1
- Unit: `WorkflowStageType`/`PlanogramLifecycleStatus` básicos; `WorkflowPlanogramStep::stage_type` herda do template e respeita override.
- Migração roda sem erro; colunas existem.

---

# FASE 2 — Conclusão na etapa Execução Loja (entrega central)

### 2.1 Policy — concluir na última etapa `flow`
[app/Policies/WorkflowExecutionPolicy.php](app/Policies/WorkflowExecutionPolicy.php) `isAtLastWorkflowStep`: ao buscar `$lastStep`, filtrar **apenas etapas `stage_type = flow`** além de `is_skipped = false`:
```php
$lastStep = $currentStep->planogram
    ?->workflowSteps()
    ->with('template')
    ->where('is_skipped', false)
    ->get()
    ->filter(fn (WorkflowPlanogramStep $s) => ! $s->stage_type->isPeriodicReview())
    ->sortBy('suggested_order')
    ->last();
```
Renomear o método para `isAtFinalFlowStep` (opcional, mas mais claro). Resultado: "Concluir" passa a aparecer em Execução Loja; Revisão Periódica deixa de ser concluível manualmente.

> **Fallback**: se o planograma não tiver nenhuma etapa `flow` (config atípica), manter o comportamento atual (última não pulada). Implementar: se o filtro `flow` retornar vazio, usar a última não pulada.

### 2.2 Marcar planograma como concluído
Em [app/Services/WorkflowKanbanService.php](app/Services/WorkflowKanbanService.php), no `complete()`, após atualizar a execução, chamar um novo método privado `maybeMarkPlanogramCompleted(Planogram $planogram)`:

Regra de "planograma concluído":
- Carregar os steps `flow` não pulados do planograma e as execuções das suas gôndolas.
- O planograma está concluído quando **toda gôndola do planograma que possui execução** tem a execução da **etapa final `flow`** com `status = completed`, e **não há** execução em etapa `flow` com status `active`/`pending`/`paused`.
- Quando verdadeiro e `lifecycle_status != completed`:
  - `completed_at = now()`
  - `lifecycle_status = PlanogramLifecycleStatus::Completed`
  - `periodic_review_due_at = computePeriodicReviewDueAt($planogram)` (ver 2.3)
  - Idempotente: se já `completed`, não reprocessa.

> Implementar `maybeMarkPlanogramCompleted` no service (não no controller). Cobrir com transação. Carregar `$execution->step->planogram` (eager) para evitar N+1.

### 2.3 Cálculo do vencimento (helper de domínio)
Criar `app/Support/Workflow/PeriodicReviewSchedule.php` (ou método estático) `computeDueAt(Planogram $p): ?CarbonImmutable`:
- Se `start_date` ou `end_date` nulos → `null` (DECISÃO 4: não agenda).
- Se `end_date < start_date` → `null` + log warning (datas invertidas).
- Senão: `periodLength = start_date->diff(end_date)`; `due = completed_at->add(periodLength)`. Retornar `due`.
- Usar `completed_at` (não `now()` direto) para a base.

### Testes Fase 2
- Feature (no estilo `WorkflowKanbanControllerTest`, que migra tenant): concluir a última gôndola em Execução Loja seta `planograms.lifecycle_status = completed`, `completed_at`, `periodic_review_due_at` corretos.
- Concluir só uma de duas gôndolas **não** conclui o planograma.
- Unit: `PeriodicReviewSchedule::computeDueAt` — período normal; sem datas → null; invertidas → null.
- Regressão: etapas 1–5 e o board seguem iguais; `complete` na etapa 6 autorizado, na 7 não.

---

# FASE 3 — Automação (command + scheduler + history + notificação)

### 3.1 Command tenant-aware
`app/Console/Commands/TriggerPeriodicReviewCommand.php`, signature `planograms:trigger-periodic-review {--tenant=} {--dry-run}`. Espelhar o padrão de [LinkSalesProductsCommand](app/Console/Commands/LinkSalesProductsCommand.php):
- Resolver tenants ativos (`Tenant::query()->where('status','active')`), ou um só via `--tenant`.
- Para cada tenant: `$tenant->execute(function () { ... })`.
- Dentro do contexto: buscar planogramas elegíveis:
  ```php
  Planogram::query()
      ->where('lifecycle_status', PlanogramLifecycleStatus::Completed)
      ->whereNotNull('periodic_review_due_at')
      ->whereNull('periodic_review_started_at')
      ->where('periodic_review_due_at', '<=', now())
      ->get();
  ```
- Para cada planograma elegível, em transação, chamar um service novo `PeriodicReviewService::promote(Planogram $p)`:
  - Garantir steps sincronizados (`stepService->syncForPlanogram($p)`).
  - Achar o step `stage_type = periodic_review` não pulado do planograma.
  - Para cada gôndola do planograma: criar (ou reaproveitar) `WorkflowGondolaExecution` nesse step com `status = pending` (sem `started_by`/`current_responsible` — entra como pendente, igual ao início de um fluxo).
  - `gravar WorkflowHistory` por execução com ação `PeriodicReviewTriggered`, actor = `null`/sistema, descrição "Promovido automaticamente para Revisão Periódica (vencimento {due})".
  - `$p->update(['lifecycle_status' => PeriodicReview, 'periodic_review_started_at' => now()])`.
  - Notificar responsáveis (ver 3.3).
  - **Idempotência**: o guard `whereNull('periodic_review_started_at')` + transação garante que reprocessar não duplica.
- `--dry-run`: apenas listar o que seria promovido, sem escrever.
- Logar resumo por tenant (quantos promovidos).

> `recordHistory` hoje é privado no `WorkflowKanbanService` e exige `User $actor`. Para actor=sistema, criar no `PeriodicReviewService` um registro de `WorkflowHistory` próprio (ou ajustar `recordHistory` para aceitar `?User`). Preferir um método dedicado no novo service para não inflar o KanbanService.

### 3.2 Scheduler
Em [routes/console.php](routes/console.php) adicionar:
```php
Schedule::command('planograms:trigger-periodic-review')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->name('planograms-trigger-periodic-review');
```

### 3.3 Notificação
Espelhar `WorkflowKanbanService::requestAbandonment` (usa `AppNotification`). Notificar os `availableUsers` do step de revisão periódica (ou `current_responsible` das gôndolas, se houver). Mensagem: "Planograma {nome} entrou em Revisão Periódica." com `actionUrl` para o kanban filtrado pelo planograma. `tenantId` corretamente preenchido.

### Testes Fase 3
- Feature: planograma `completed` com `periodic_review_due_at <= now()` → command promove: `lifecycle_status = periodic_review`, `periodic_review_started_at` setado, execuções criadas no step de revisão (`pending`), `WorkflowHistory` com ação `periodic_review_triggered`.
- **Idempotência**: rodar o command duas vezes não duplica execuções nem histórico.
- Planograma com `due_at` no futuro **não** é promovido. Planograma sem `due_at` (sem datas) **não** é promovido.
- Notificação enviada aos responsáveis (`Notification::fake()` + `assertSentTo`).

---

# FASE 4 — Observer (recálculo) + reabertura + bordas

### 4.1 Observer de período
`app/Observers/PlanogramObserver.php`, registrado no `AppServiceProvider::boot` (`Planogram::observe(...)`):
- No `updated`: se mudou `start_date` ou `end_date` **e** `lifecycle_status = completed` **e** `periodic_review_started_at` é null → recalcular `periodic_review_due_at` via `PeriodicReviewSchedule::computeDueAt`. Se já entrou em revisão (`periodic_review_started_at` != null), **ignorar**.
- Cuidado para não recursão infinita (usar `saveQuietly`/`updateQuietly` ao gravar o recálculo, ou checar `isDirty`).

### 4.2 Reabertura de planograma concluído
- Definir endpoint/ação de reabertura (ou método de service) que: limpa `completed_at`, `periodic_review_due_at`, `periodic_review_started_at`, volta `lifecycle_status = in_progress`, e (decisão do produto) reabre as gôndolas no fluxo. **Confirmar com o time se reabertura é necessária agora** — se não for, deixar documentado como follow-up e não implementar.

### Testes Fase 4
- Mudar `end_date` de planograma `completed` (ainda não em revisão) recalcula `periodic_review_due_at`.
- Mudar período de planograma já em `periodic_review` **não** altera nada.
- (Se reabertura implementada) reabrir limpa os campos e volta a `in_progress`.

---

# FASE 5 — Frontend, filtros, relatórios, traduções

### 5.1 Kanban
- O botão "Concluir" passa a aparecer em Execução Loja automaticamente (decorre da policy `can_complete`) — **sem mudança de código** no card. Validar no browser.
- A coluna Revisão Periódica só recebe cards após o gatilho.
- Badge no planograma `completed`: "Aguardando revisão periódica em {data}" (usar `periodic_review_due_at`). Onde exibir: cabeçalho do planograma selecionado em [Kanban.vue](resources/js/pages/tenant/planograms/Kanban.vue) (já recebe `selected_planogram`) — incluir `lifecycle_status` e `periodic_review_due_at` no payload de `selected_planogram` em [WorkflowKanbanController](app/Http/Controllers/Tenant/WorkflowKanbanController.php).

### 5.2 Filtro de lifecycle do planograma
- Novo filtro (in_progress / completed / periodic_review) na listagem de planogramas e/ou nos filtros do kanban. Backend: aceitar e filtrar por `lifecycle_status`. Frontend: `KanbanFilters.vue` / página de planogramas.

### 5.3 Relatórios/KPIs
- Onde houver contagem de "planogramas concluídos" baseada na etapa 7, **reapontar** para `Planogram.lifecycle_status` (`completed` + `periodic_review` contam como "passaram pela conclusão"). Procurar usos antes de mexer. KPI novo: "aguardando/em revisão periódica" = `lifecycle_status = periodic_review` (ou `completed` com `due_at` passado).

### 5.4 Traduções
- Todas as strings novas em `lang/pt_BR/...` (badges, filtros, labels de status). Sem hardcode. Frontend usa `useT()`.

### Build/validação
- `VITE_ENABLE_WAYFINDER=false npm run build`.
- Validar manualmente no browser (tenant `alberti`).

---

# Critérios de aceite (Given/When/Then)

1. **Conclusão em Execução Loja**
   - *Given* planograma com gôndolas todas em Execução Loja ativas, *When* a última é concluída, *Then* `lifecycle_status = completed`, `completed_at` setado, `periodic_review_due_at = completed_at + (end-start)`, e a etapa 7 não foi tocada.
2. **Sem datas**
   - *Given* planograma sem `end_date`, *When* concluído, *Then* `completed`, `periodic_review_due_at = null`, nunca promovido.
3. **Gatilho automático**
   - *Given* planograma `completed` com `due_at <= hoje` e `periodic_review_started_at = null`, *When* o command roda, *Then* `lifecycle_status = periodic_review`, `started_at` setado, execuções `pending` criadas na etapa 7, history `periodic_review_triggered` gravado, responsáveis notificados.
4. **Idempotência**
   - *Given* o cenário acima já processado, *When* o command roda de novo, *Then* nada muda (sem execuções/históricos duplicados).
5. **Mudança de período pós-conclusão**
   - *Given* planograma `completed` (sem revisão iniciada), *When* `end_date` é alterado, *Then* `periodic_review_due_at` é recalculado.
6. **Histórico intacto**
   - *Given* planogramas finalizados antes do deploy, *When* qualquer rotina roda, *Then* eles permanecem inalterados (não promovidos, histórico preservado).

# Riscos e mitigação

- **Quebra de histórico** → decisão não-retroativa; backfill apenas estrutural (`stage_type`), nunca em execuções/planogramas finalizados.
- **Dupla execução do job** → guard de estado (`periodic_review_started_at`) + transação + `withoutOverlapping()`.
- **Template customizado por tenant** (sem etapa flow/ordem diferente) → `stage_type` resolve sem depender de ordem; fallback para última não pulada se não houver `flow`.
- **N+1 no command/conclusão** → eager-load steps/template/execuções; processar por planograma.
- **Multi-tenant no scheduler** → padrão `$tenant->execute()` já consolidado.

# Ordem de execução e verificação

1. Fase 1 → migração tenant + testes unit. Confirmar colunas no tenant `alberti` via tinker (`-e HOME=/tmp`).
2. Fase 2 → policy + service + helper; `docker compose exec php php artisan test --compact --filter=WorkflowKanbanControllerTest` (+ novos testes de conclusão).
3. Fase 3 → command + scheduler + service de promoção; testes de automação/idempotência; testar o command manualmente: `docker compose exec php php artisan planograms:trigger-periodic-review --dry-run`.
4. Fase 4 → observer + bordas.
5. Fase 5 → frontend + traduções + build + validação no browser.
6. `docker compose exec php vendor/bin/pint --dirty --format agent` ao final de cada fase com PHP alterado.

# Arquivos (criar/alterar)

**Criar:**
- `app/Enums/WorkflowStageType.php`, `app/Enums/PlanogramLifecycleStatus.php`
- `database/migrations/2026_06_26_000000_add_periodic_review_to_workflow_and_planograms.php`
- `app/Support/Workflow/PeriodicReviewSchedule.php`
- `app/Services/PeriodicReviewService.php`
- `app/Console/Commands/TriggerPeriodicReviewCommand.php`
- `app/Observers/PlanogramObserver.php`
- Testes: `tests/Feature/Tenant/PlanogramCompletionTest.php`, `tests/Feature/Tenant/PeriodicReviewTriggerTest.php`, `tests/Unit/PeriodicReviewScheduleTest.php`

**Alterar:**
- `app/Enums/WorkflowHistoryAction.php` (novo case)
- `app/Models/WorkflowTemplate.php`, `app/Models/WorkflowPlanogramStep.php`, `app/Models/Planogram.php`
- `app/Http/Controllers/Landlord/WorkflowTemplateController.php` (seed `stage_type`)
- `app/Services/WorkflowPlanogramStepService.php` (select `stage_type` no template)
- `app/Policies/WorkflowExecutionPolicy.php` (última etapa `flow`)
- `app/Services/WorkflowKanbanService.php` (`maybeMarkPlanogramCompleted` no `complete`)
- `app/Providers/AppServiceProvider.php` (registrar observer)
- `routes/console.php` (scheduler)
- `app/Http/Controllers/Tenant/WorkflowKanbanController.php` (lifecycle/due no `selected_planogram`)
- Frontend: `resources/js/pages/tenant/planograms/Kanban.vue`, `resources/js/components/kanban/KanbanFilters.vue`, tipos relevantes; `lang/pt_BR/...`

# Fora de escopo (follow-ups)
- Migração retroativa de planogramas antigos (decisão: não).
- UI para editar `stage_type` por etapa (fica seed-definido; espelhar `access_mode` se pedirem depois).
- Reabertura de planograma (confirmar necessidade antes de implementar — Fase 4.2).
