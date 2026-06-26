# PLAN-145 — Tela "Execução em Loja" — Brief de Implementação

> **Para o chat que vai implementar:** este documento é o prompt de execução. A
> fonte das regras de negócio é [plan-execucao-loja.md](plan-execucao-loja.md)
> (export do JIRA). **Porém esse export contém um erro de modelagem corrigido
> abaixo (seção "Correções obrigatórias"). Em qualquer divergência entre o export
> e este brief, este brief prevalece.**

---

## 0. Antes de começar (OBRIGATÓRIO)

1. Trabalhar em **nova branch** a partir de `main`:
   ```bash
   git checkout main && git checkout -b feat/execucao-loja-tela
   ```
   Não commitar direto em `main`/`dev`.
2. Ler os arquivos de início de sessão do CLAUDE.md (Resumo + changelog).
3. Todos os comandos via Docker (`docker compose exec php php artisan ...`).
   Nunca `npm run build` direto, nunca `migrate:fresh`.
4. Há um plano paralelo em
   [plano-revisao-periodica-automatica.md](../plano-revisao-periodica-automatica.md)
   tratando a **Revisão Periódica automática**. Esta tela NÃO implementa a revisão
   periódica — apenas grava os dados que ela consome (`completed_at`,
   `periodic_review_due_at`). Não duplicar nem conflitar com aquele plano.

---

## 1. Correções obrigatórias ao export do JIRA

O export descreve "Concluídos" como uma **etapa/coluna do Kanban**
(seções 2, 3, 20, 23, 32 do export). **Isso está errado para este código.**
Modelo real:

| Conceito no export | Realidade no código | Onde |
| --- | --- | --- |
| Etapa "Concluídos" no Kanban | **Status** `lifecycle_status = completed` no planograma | [PlanogramLifecycleStatus.php](../../app/Enums/PlanogramLifecycleStatus.php) |
| "Mover card para Concluídos" | **Mudar o status** para `completed` + gravar `completed_at`. O card permanece logicamente na etapa **Execução em Loja** (`stage_type: flow`, 6º step) | [WorkflowKanbanService.php](../../app/Services/WorkflowKanbanService.php) |
| Lista de etapas do fluxo | Criação → Revisão de imagens → Revisão de dimensões → Aprovação comercial → Aprovação GC → **Execução em Loja**. **Não existe coluna "Concluídos".** Revisão Periódica é `stage_type: periodic_review` (pós-conclusão) | [WorkflowTemplate.php](../../app/Models/WorkflowTemplate.php) |

### Regra corrigida 1 — Concluído é status, não etapa
Ao concluir a execução, **não** mover o card para nenhuma coluna nova. Em vez disso:
- `planograms.lifecycle_status` → `completed`;
- gravar `planograms.completed_at`;
- a execução da gôndola (`WorkflowGondolaExecution.status`) → `completed`;
- calcular e gravar `periodic_review_due_at` (consumido pelo plano de revisão periódica).

Reaproveitar a lógica de conclusão que já existe em
`WorkflowKanbanService` (o método que ao concluir a etapa final marca o
planograma como `completed`). **Não criar uma etapa "Concluídos".**

### Regra corrigida 2 — Concluídos não são listados por padrão
**Comportamento novo a implementar.** Hoje o Kanban/listagem mostra tudo.
Passar a, **por padrão, ocultar** planogramas com `lifecycle_status = completed`:
- No board do Kanban ([WorkflowKanbanService::buildBoardForTenant](../../app/Services/WorkflowKanbanService.php)) e na listagem: aplicar filtro padrão que exclui `completed` quando o usuário **não** pediu explicitamente para vê-los.
- Adicionar um **toggle/filtro opcional** ("Exibir concluídos") em [KanbanFilters.vue](../../resources/js/components/kanban/KanbanFilters.vue) — usar o filtro `lifecycle_status` que já existe no controller ([WorkflowKanbanController.php](../../app/Http/Controllers/Tenant/WorkflowKanbanController.php)).
- Importante: `periodic_review` segue suas próprias regras — não confundir com `completed`. Ocultar por padrão **apenas** `completed`, não `periodic_review`.

> Substitua mentalmente, em todo o export: **"etapa Concluídos" → "status `completed` (oculto da listagem por padrão)"** e **"mover para Concluídos" → "marcar como concluído"**.

---

## 2. Escopo desta tela

Tela de execução em loja, acessível quando o card está na etapa **Execução em
Loja**. Regras de acesso por visualização (do export, seção topo):
- **Lista** e **Kanban**: abrir apenas quando estiver na etapa Execução em Loja.
- **Mapa**: abrir somente depois de concluído (`lifecycle_status = completed`).

A loja **não edita** o planograma (sem trocar produto, frente, posição,
dimensão, módulo ou layout). Só registra execução. Ver permissões na seção 28 do
export e em [WorkflowExecutionPolicy.php](../../app/Policies/WorkflowExecutionPolicy.php).

### 2.1 Layout da tela (export §7–§12)
- Cabeçalho com dados do planograma (código, status, loja/corredor, categoria, nº módulos, data publicação, responsável, fluxo, versão).
- Bloco resumido de execução (status, responsável, início, SLA, evidências X/Y, divergências) — só resumo + ações; detalhe nos modais.
- Fluxo da gôndola acima da prateleira (somente leitura): início, sentido, fim.
- Botões de execução (3, à direita do bloco): **Adicionar evidência**, **Apontar divergência**, **Concluir execução** — cada um abre um modal.
- Botões de visualização já existentes (zoom, performance, colunas, baixar PDF) — preservar, separados visualmente.
- **Início automático**: ao abrir o planograma pela 1ª vez na etapa Execução em Loja, registrar `status = active`, usuário e data/hora de início (export §12, opção recomendada).

### 2.2 Modal "Adicionar evidência" (export §13–§15)
- Tipos: Foto geral, Módulo, Produto, Outro (regras de obrigatoriedade por tipo no §13).
- Upload múltiplo (drag-and-drop + seleção), miniaturas, remover antes de salvar, observação opcional, indicador de progresso X/Y.
- Sugestão técnica: JPG/PNG/HEIC, 10 MB/arquivo, 10 fotos/envio.
- Evidências obrigatórias **configuráveis** (§14): padrão = 1 foto geral + 1 por módulo.

### 2.3 Modal "Apontar divergência" (export §16–§19)
- Tipos: ruptura, divergente, falta de espaço, embalagem diferente, não localizado, sem cadastro, quantidade insuficiente, outro.
- Campos: tipo, módulo, prateleira, posição/facing, produto, observação, fotos opcionais (obrigatoriedade por tipo no §17).
- Status da divergência (§18): estruturar banco para `Aberta/Justificada/Em análise/Resolvida/Rejeitada`; na 1ª versão usar ao menos `Aberta` e `Resolvida`.
- Lista de divergências já registradas dentro do modal (§19).

### 2.4 Modal "Concluir execução" (export §20–§24)
- Resumo: evidências, divergências, SLA, responsável, validações.
- **Validações antes de concluir** (§21–§22):
  - evidências obrigatórias presentes (senão bloqueia, com atalho p/ adicionar);
  - divergências abertas sem justificativa bloqueiam (regra recomendada: concluir só se justificadas);
  - registrar responsável, data/hora, SLA dentro/fora do prazo.
- **Ação de concluir** (§23) — aplicar a **Regra corrigida 1** desta página
  (status `completed`, `completed_at`, calcular `periodic_review_due_at`), e a
  **Regra corrigida 2** (some da listagem por padrão). **Sem coluna "Concluídos".**
- Texto do modal (§24) deve deixar claro: encerra a execução e a revisão
  periódica será gerada conforme o prazo cadastrado — sem prometer coluna nova.

### 2.5 Histórico / auditoria / SLA / notificações (export §29–§31)
- Registrar eventos em [WorkflowHistory](../../app/Models/WorkflowHistory.php) (entrou em execução, iniciada, evidência +/-, divergência registrada/atualizada/resolvida, execução concluída).
- **Não** registrar evento "card movido para Concluídos" como mudança de coluna — registrar como mudança de status para concluído.
- SLA conta a partir da entrada na etapa Execução em Loja (§30).

---

## 3. Modelo de dados — verificar antes de criar

Já existem (confirmar antes de migrar):
- `planograms.lifecycle_status`, `completed_at`, `periodic_review_due_at`, `periodic_review_started_at` ([Planogram.php](../../app/Models/Planogram.php)).
- Enums [PlanogramLifecycleStatus](../../app/Enums/PlanogramLifecycleStatus.php) e [WorkflowExecutionStatus](../../app/Enums/WorkflowExecutionStatus.php).
- [WorkflowGondolaExecution](../../app/Models/WorkflowGondolaExecution.php), [WorkflowHistory](../../app/Models/WorkflowHistory.php), [WorkflowPlanogramStep](../../app/Models/WorkflowPlanogramStep.php).

Provavelmente **novas** tabelas tenant (`tenants:artisan "migrate --database=tenant"`):
- Evidências de execução (planograma/gôndola, tipo, módulo, produto, arquivos, observação, usuário, timestamps).
- Divergências de execução (tipo, módulo, prateleira, posição, produto, observação, status, fotos, usuário, timestamps).
- Config de evidências obrigatórias por cliente/categoria/tipo (pode começar simples).

Modelos tenant: `BelongsToTenant`, `UsesTenantConnection`, `HasUlids`, `SoftDeletes`. **Nunca** passar `tenant_id` manualmente.

---

## 4. Frontend
- Vue 3 + Inertia v3 + TS, `useT()` para todo texto (sem hardcode; chaves em `lang/pt_BR/...`).
- Mutações via `router` do Inertia (não axios/fetch). Controllers de mutação retornam `back()`.
- Wayfinder: **não** rodar `wayfinder:generate` automaticamente — escrever actions manualmente se necessário (ver memória do projeto).

## 5. Testes (obrigatório)
- Feature tests Pest para: início automático, salvar evidência, salvar divergência, bloqueio de conclusão por evidência/divergência pendente, conclusão feliz (status `completed` + `completed_at` + `periodic_review_due_at`), e **concluído sumir da listagem por padrão / aparecer com o filtro**.
- Tenant de teste: slug real **`alberti`** (não `albert`).
- Rodar: `docker compose exec php php artisan test --compact --filter=...`.
- Pint ao final: `docker compose exec php vendor/bin/pint --dirty --format agent`.

## 6. Entrega
- Branch `feat/execucao-loja-tela`, sem commit em main/dev.
- Resumo do que foi feito + lista de migrations novas + como validar no browser.
- Avisar explicitamente se algo do export ficou fora do escopo.
