# Workflow: Pacote laravel-raptor-flow (atual)

O workflow de planogramas e gôndolas usa o pacote **callcocam/laravel-raptor-flow**. Todas as operações de config, etapas e execuções usam as tabelas `flow_*` no **banco de cada cliente** (conexão tenant).

---

## 1. Modelos do pacote (flow)

| Modelo | Tabela | Uso |
|--------|--------|-----|
| `FlowStepTemplate` | `flow_step_templates` | Templates de etapas (nome, ordem, role, duração, cor, ícone). Por cliente. |
| `FlowConfig` | `flow_configs` | Configuração de workflow por planograma (`configurable_type` = PlanogramWorkflow, `configurable_id` = planogram_id). |
| `FlowConfigStep` | `flow_config_steps` | Etapas da config (uma por FlowStepTemplate, com ordem, default_role_id, estimated_duration_days). |
| `FlowExecution` | `flow_executions` | Execução por gôndola (`workable_type` = GondolaWorkflow, `workable_id` = gondola_id). Status, responsável, SLA, datas. |
| `FlowHistory` | `flow_histories` | Histórico de ações (move, pause, assign, etc.). |

**Models da aplicação (ponte):**

- `App\Models\Workflow\PlanogramWorkflow` — aponta para `planograms`; usado como `configurable` em FlowConfig.
- `App\Models\Workflow\GondolaWorkflow` — aponta para `gondolas`; usado como `workable` em FlowExecution.

---

## 2. Comandos

| Comando | Descrição |
|---------|-----------|
| `flow:seed` | Cria FlowStepTemplate (se vazio, a partir de WorkflowStepTemplate do landlord), FlowConfig + FlowConfigStep por planograma e FlowExecution por gôndola. Opções: `--client`, `--skip-configs`, `--skip-executions`, `--force`. |
| `workflow:verify` | Lista por cliente: planogramas, FlowConfigs, gôndolas, FlowExecutions, FlowStepTemplates. |

---

## 3. Kanban e controllers

- **KanbanService** (`App\Services\Workflow\KanbanService`): usa apenas FlowExecution, FlowStepTemplate, FlowConfig e FlowConfigStep. Monta o board (steps + executions agrupados por etapa) e filtros.
- **KanbanController** (`App\Http\Controllers\Plannerate\KanbanController`): chama o KanbanService e devolve dados ao frontend (Inertia).
- **GondolaWorkflowController** (`App\Http\Controllers\Workflow\GondolaWorkflowController`): ações por gôndola (start, move, pause, resume, assign, abandon, notes) delegadas ao `FlowManager` do pacote. Página Show usa `flowExecution` e `history` (FlowHistory).
- **PlanogramController** (config de workflow): usa `FlowManager::getConfigFor`, `createConfig`, `syncConfigSteps` e `FlowStepTemplate` para o repeater de etapas no formulário do planograma.

---

## 4. Observer e criação de execuções

- **GondolaObserver**: ao criar ou atualizar uma gôndola (com planogram_id), se existir FlowConfig para o planograma, chama `FlowManager::createPendingExecution(GondolaWorkflow, FlowConfig)`. Ao deletar/restaurar gôndola, remove/restaura as FlowExecutions correspondentes.

---

## 5. Permissões

- **FlowExecutionPolicy** (app estende a do pacote): define quem pode start, move, pause, resume, assign, abandon, notes. O Kanban envia `abilities` por execução para o frontend.

---

## 6. Referência rápida

- **Templates no flow**: vêm do landlord (`WorkflowStepTemplate`) na primeira vez que roda `flow:seed` (se não houver nenhum FlowStepTemplate no cliente).
- **Config por planograma**: FlowConfig + FlowConfigStep (criados pelo flow:seed ou pelo formulário do planograma).
- **Execução por gôndola**: FlowExecution (criada pelo flow:seed ou pelo GondolaObserver quando a gôndola é criada e já existe FlowConfig no planograma).

Documentação de setup: [COMANDOS_SETUP.md](COMANDOS_SETUP.md#4-seed-de-workflow-flow).
