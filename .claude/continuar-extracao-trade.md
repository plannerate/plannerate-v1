# Continuar a extração do domínio Trade

Passagem de bastão entre sessões. **Fases 0 a 3 prontas e verificadas.** Cada fase seguinte é
executada numa sessão nova.

> **O pacote saiu do monorepo (23/07/2026).** Deixou de ser `packages/callcocam/laravel-raptor-trade`
> (path repository + symlink) e virou o repositório privado
> **`github.com/callcocam/laravel-raptor-trade`**, consumido por `repositories` VCS igual ao
> `laravel-integrations`. O código agora se escreve em `~/projects/laravel-raptor-trade` e chega
> ao app por `composer update`.

Plano completo (10 fases, decisões arquiteturais, riscos): `docs/PLANO.md` **no pacote**.
Regras de código, ciclo de trabalho e armadilhas do pacote: `CLAUDE.md` **no pacote**
(`~/projects/laravel-raptor-trade/CLAUDE.md`) — leia junto com este arquivo antes de abrir uma fase.

## Onde as coisas estão

| O quê | Onde |
|---|---|
| Pacote (repo) | `github.com/callcocam/laravel-raptor-trade` — **privado**, branch `main` |
| Clone local do pacote | `/home/caltj/projects/laravel-raptor-trade` ← **é aqui que se edita** |
| Cópia instalada no app | `vendor/callcocam/laravel-raptor-trade` (dist do Composer; **não editar**) |
| App host | `/home/caltj/projects/plannerate-v1` — cascas, i18n, menu, migrations sincronizadas |
| Projeto de origem | `/home/caltj/projects/space-trade` — Laravel 12 + Inertia + **React 19** |
| Docs de negócio da origem | `space-trade/docs/contrato.md`, `docs/contracts.md`, `docs/workflow/*` |
| Padrão de backend | `packages/callcocam/laravel-raptor-plannerate` (esse **continua** no monorepo) |
| Padrão de pacote-fora-do-app | `/home/caltj/projects/laravel-integrations` |
| Rotas de origem (fonte da paridade) | `space-trade/routes/{web,maps,supplier,api}.php` — 131 + 67 + 25 rotas |

## Como abrir a sessão de uma fase

Rode o Claude **do app host** (`/home/caltj/projects/plannerate-v1`) e peça, por exemplo:

> Execute a Fase 4 do plano em `.claude/continuar-extracao-trade.md`. O código do pacote está
> em `~/projects/laravel-raptor-trade`; o código-fonte de origem está em
> `~/projects/space-trade` (React) e vai ser reescrito em Vue.

Rodar do app host (e não do pacote) é o certo: metade de cada fase é trabalho **no host** —
cascas publicadas, i18n, item de menu, `wayfinder:generate`, build, migrations por tenant — e
só do host dá para verificar o resultado de verdade.

A leitura de `~/projects/space-trade/**` e `~/projects/laravel-raptor-trade/**` já está liberada
em `.claude/settings.local.json` — não precisa de `--add-dir`.

## O que fica em cada lado

| No pacote (`~/projects/laravel-raptor-trade`) | No app host (`plannerate-v1`) |
|---|---|
| migrations (`database/migrations/clients/`) | cópia sincronizada em `database/migrations/clients/` |
| models, enums, services, policies, requests, controllers | — |
| rotas (`routes/*.php`) + registro no provider | — |
| páginas/componentes Vue reais (`resources/js/`) | cascas de 5 linhas em `resources/js/pages/tenant/trade/` |
| stubs das cascas (`resources/stubs/pages/`) | — |
| permissions (`TradePermission`) | — |
| — | traduções `lang/pt_BR/app/tenant/trade/*.php` |
| — | item de menu (`SidebarNavigationService`) + ícone (`NavMenuEntry.vue`) |
| — | alias `@trade` (`vite.config.ts`, `tsconfig.json`) |

Regra prática: **texto visível e navegação são do host; regra de negócio e tela são do pacote.**

## Rodar as coisas

Não há PHP nem Composer no host — tudo via container do app:

```bash
docker compose exec php php artisan route:list --path=trade
docker compose exec php php artisan trade:migrations:sync
docker compose exec php php artisan trade:permissions:sync
docker compose exec php php artisan trade:publish-pages          # --force para sobrescrever
docker compose exec php php artisan tenants:artisan "migrate --database=tenant --path=database/migrations/clients"
docker compose exec php ./vendor/bin/pint vendor/callcocam/laravel-raptor-trade --format agent
```

Frontend, **no host** (nunca `-u root` no wayfinder — ver CLAUDE.md):

```bash
docker compose exec php php artisan wayfinder:generate --with-form
VITE_ENABLE_WAYFINDER=false npm run build
npm run types:check && npx eslint resources/js vendor/callcocam/laravel-raptor-trade/resources/js
```

Composer **exige** o token do GitHub — `laravel-integrations` e `laravel-raptor-trade` são
repos VCS privados, e sem credencial o Composer aborta antes de resolver qualquer pacote:

```bash
TOKEN=$(gh auth token)
docker compose exec -e COMPOSER_HOME=/tmp/composer \
  -e COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"$TOKEN\"}}" \
  php composer update callcocam/laravel-raptor-trade
```

## Ciclo de trabalho de uma fase

1. **Escrever no pacote**: `~/projects/laravel-raptor-trade` (migrations → models/enums →
   services → policies/requests → controllers → rotas → páginas Vue → stubs das cascas).
2. **Espelhar no `vendor/` do host** para testar sem push:
   ```bash
   rsync -a --delete --exclude .git ~/projects/laravel-raptor-trade/ \
     ~/projects/plannerate-v1/vendor/callcocam/laravel-raptor-trade/
   ```
   Serve para os dois lados: o PHP roda no container (que monta a raiz do app) e o Vite roda no
   host — ambos leem `vendor/`. **Nunca editar direto no `vendor/`**: o próximo `composer update`
   apaga tudo. Editar no clone, espelhar, e ao terminar commitar no clone.
3. **Verificar no host**: `route:list` → `trade:migrations:sync` + migrate por tenant →
   `trade:permissions:sync` → `trade:publish-pages` → `wayfinder:generate --with-form` → build,
   `types:check`, eslint, pint.
4. **Fechar**: commit + push no pacote, `composer update callcocam/laravel-raptor-trade` no host
   (isso substitui o espelho pelo dist real e move o `composer.lock`), e commit no host com as
   cascas, i18n, menu e `composer.lock`.

**Armadilha da ordem**: o Wayfinder só gera os helpers TS depois que as rotas existem, e as
páginas Vue importam esses helpers. Sempre backend/rotas → `wayfinder:generate` → páginas.

### Testar o pacote isoladamente

O pacote ainda **não tem suíte de testes** (dívida conhecida — falta `pest` + `orchestra/testbench`).
Enquanto isso, a verificação é a do passo 3 mais exercícios de tinker no app numa transação com
rollback — foi assim nas Fases 1, 2 e 3. O ambiente de teste local do app está quebrado (sqlite
sem schema landlord), então `php artisan test` falha por motivo alheio à mudança.

Para rodar ferramenta PHP dentro do clone (não há PHP no host):

```bash
cd ~/projects/laravel-raptor-trade
docker run --rm -v "$PWD":/app -w /app -u 1000:1000 php:8.4-cli php -l src/<arquivo>.php
```

---

## Estado das fases

| Fase | Escopo | Status |
|---|---|---|
| 0 | Fundação: composer, provider, config, comandos, alias `@trade`, página de teste | **pronta** |
| 1 | Espaços + `trade_store_profiles` + aditiva no `Provider` do host | **pronta** |
| 2 | Mapas / planta da loja (konva + vue-konva) — **maior risco** | **pronta** |
| 3 | Reservas + intenções de compra | **pronta** |
| 4 | Contratos (+ anexos, aprovações internas, rounds de negociação) | **pronta** |
| 5 | Atividades, workflow/kanban, proofs, execução pública por token | **pronta** |
| 6 | Portal do Fornecedor | **pronta** |
| 7 | Dashboards | **pronta** |
| 8 | PWA `/campo` + web push | **pronta** |
| 9 | API interna Gomark (feature flag) | **adiada** — fora de escopo por ora (23/07/2026) |
| 10 | Paridade rota-a-rota e endurecimento | a fazer |

## O que a Fase 8 entregou

- **PWA `/campo`** (`FieldController` + `campo/Index.vue`): rampa de lançamento
  móvel do executor. Lista as atividades abertas do usuário (próprias, de etapa
  ou do fornecedor — resolvidas por `TradeUserContext`) e **entrega a execução às
  telas de my-activities** (mesma `ActivityPolicy`, sem duplicar regra). O
  `<Head>` liga manifest + service worker; `useFieldPwa`/`push.ts` registram o SW
  e assinam o push (fetch standalone, exceção permitida ao `router`).
- **Web push** via `laravel-notification-channels/webpush`:
  - `Model PushSubscription` estende o do webpush **preso à conexão de tenant**
    (tabela `trade_push_subscriptions`); o provider prende `webpush.model`/
    `table_name` no `packageRegistered`. VAPID vem do `.env` do host.
  - `PushSubscriptionController` (store/destroy, 204) grava via trait
    `HasPushSubscriptions` **adicionada ao `User` do host**.
  - `FieldPushNotifier` + `FieldPush` (canal WebPush) — guard de feature
    (`features.webpush` + canal instalado + VAPID) e try/catch: **falha de push
    nunca derruba a operação** que a disparou.
- **Push ligado nos ganchos que já existiam** — fecha a pendência arrastada das
  Fases 5/6: atribuição de atividade ao fornecedor (`ActivitySupplierNotifier`,
  agora e-mail **+** push) e resultado da comprovação
  (`MyActivityController@reviewProof` avisa quem enviou).
- **`PublishTradePwaCommand`** (`trade:publish-pwa`): copia sw + ícones e **gera**
  o `manifest-trade.webmanifest` a partir de `config('trade.pwa')` + prefixo de
  rota (start_url acompanha o prefixo sem editar arquivo).
- **Host**: casca `campo/Index.vue`, i18n `field.php`, item de menu "Campo"
  (ícone `smartphone`, gate `viewAny` de Activity) no `SidebarNavigationService`
  + `NavMenuEntry.vue`. `routes/field.php` só carrega com `features.pwa`.

**Decisões/desvios importantes desta fase:**
- **Reusa my-activities para executar** — o `/campo` é só a listagem móvel sem
  sidebar; a execução (checklist/fotos/proof) são as mesmas telas da Fase 5,
  porque a policy já reconhece executor e fornecedor.
- **Assinaturas na conexão de tenant** — as subscriptions pertencem a usuários do
  cliente, não ao landlord; por isso o model do webpush é reamarrado ao escopo.
- **Push é aditivo e silencioso** — sem a flag, sem VAPID ou em erro de
  transporte, o notifier não faz nada; o e-mail das Fases 5/6 continua o canal
  garantido.
- **Manifest gerado, não estático** — o `scope` fica na raiz (`/`) para a tela de
  execução, fora do prefixo `/campo`, seguir dentro do app instalado.

**Verificado:** migration `trade_push_subscriptions` nos tenants (schema
correto), `route:list` (3 rotas campo/push), `npm run build` (casca do `/campo`
no manifest), `types:check` **limpo nos arquivos da Fase 8** (`push.ts` corrigido:
`Uint8Array<ArrayBuffer>` para satisfazer `BufferSource`), eslint `--no-ignore`
limpo nos arquivos do pacote, pint passed. Pacote em `48b80b8`, `composer.lock`
do host movido para esse commit.

**Pendências conhecidas:** o PWA **não foi aberto num browser** (registrar SW,
assinar push, receber notificação de verdade) — é a parte de maior risco e o
harness de tinker não cobre; sem testes automatizados (padrão das fases); os
erros de `types:check` em páginas das Fases 5–7 e no pacote plannerate seguem
como **dívida pré-existente** (o build os tolera); a mudança **não relacionada**
`config/filesystems.php` (+ `PublicDiskUrlIsRootRelativeTest`) continua fora dos
commits das fases. Falta a **Fase 10** (paridade rota-a-rota + endurecimento); a
Fase 9 (Gomark) está **adiada**.

## O que a Fase 7 entregou

- **`TradeDashboardService`** (`Services/Dashboard/`): substitui o placeholder
  "hello" da Fase 0 por um dashboard de verdade. Visão geral cruzando os
  domínios (ocupação, ações ativas + valor, negociações abertas, atividades
  abertas/atrasadas, comprovações a revisar, vencendo em 7d) + três abas:
  - **Ações**: KPIs (ativas/valor/agendadas/vencendo/fornecedores) + rank por
    fornecedor/loja/tipo (contagem + valor) + linha de vencimentos (dias
    restantes).
  - **Atividades**: KPIs (abertas/atrasadas/concluídas 30d/taxa no prazo/tempo
    médio/reprovadas) + distribuição por situação/tipo/loja.
  - **Foto Check**: KPIs (fila/aprovadas/reprovadas/taxa/tempo de revisão/
    aguardando envio) + fila de revisão + reprovadas recentes.
- **`SpaceAnalyticsBuilder`** (`Services/Dashboard/`): aba **Performance** —
  receita por mês (6 meses), top clientes, desempenho por tipo de espaço,
  tendência de ocupação (mês atual × anterior), sobre reservas efetivas.
- **`TradeDashboardController`** guardado pela permission
  `tenant.trade-dashboard.view` (via `TradeAuthorization`).
- **Frontend**: `dashboard/Index.vue` com abas + componentes `StatCard` e
  `BarList` (barras horizontais sem lib de gráfico, alinhado ao DS do host).
  Item de menu "Dashboard" no topo do grupo Trade (permission), i18n `dashboard.php`.

**Decisões/desvios importantes desta fase:**
- **Sem lib de gráfico** (o host não tem chart.js/apexcharts): KPIs em cards +
  ranks em barras horizontais próprias (`BarList`). Cobre a intenção do plano
  sem nova dependência.
- **Tudo calculado no backend** (D11): a tela só apresenta; cada aba é uma seção
  do payload. Escopo pelo dono via global scope (sem `scopeTenant` manual).
- **`por_tipo` usa o slug do tipo do espaço** (`space.type`), não um label
  cadastrado — o dashboard não resolve o nome do tipo para não puxar mais joins;
  a tela mostra o slug.
- **Redirecionamento por perfil da origem descartado**: a origem redirecionava
  fornecedor/loja para outros painéis; aqui o menu já é gateado por permission/
  vínculo, então o controller só verifica a permission de dashboard.

**Verificado:** `route:list` (dashboard em `trade`), pint, eslint, `npm run build`
(casca do dashboard no manifest). Tinker: `build()` monta o payload das 4 abas +
performance sem erro (6 meses de receita, ocupação). **Fixtures numa transação
com rollback provaram os números batendo**: 1 ação confirmada ativa + 1 pending
agendada → ocupação 1/1=100%, ações ativas 1/valor 500, agendadas 1, vencendo_7d
1, `por_fornecedor` total 2/valor 800; 2 atividades abertas (1 atrasada); 1
comprovação na fila; 1 negociação aberta — tudo conferido contra os fixtures.

**Pendências conhecidas:** sem filtros de período/loja/fornecedor no dashboard
(a origem tinha; a versão atual mostra o consolidado — dá para adicionar depois);
sem export de relatório (a origem tinha `spaces/analytics/export`); render HTTP
real da tela não rodou pelo harness de tinker (payload exercitado direto).

## O que a Fase 6 entregou

- **Migrations** (pacote): `trade_provider_user` e `trade_store_user` — pivôs
  usuário↔fornecedor/loja (D7), substituem `parceiro_user`/`loja_user` da origem.
  PK ULID + escopo → models `Pivot` com `->using()` (armadilha 23502).
- **Models**: `ProviderUser`, `StoreUser`.
- **`TradeUserContext`** (D9, `Support/Tenancy/`): resolve os fornecedores/lojas
  do usuário pelos pivôs — com **fallback para `Provider.user_id`** que o host já
  usa como dono único —, `isSupplier`/`isStoreExecutor`/`primaryProvider`.
  Substitui toda leitura de `User.type` da origem.
- **Gate de acesso**: middleware `EnsureTradeSupplier` (portão do portal) + Gate
  `trade-supplier-portal` (menu do host, sem model — via `Gate::check`).
- **Controllers** (`Supplier/`): `SupplierPortalController` (panel consolidado:
  espaços/negociações/comprovações + contratos pendentes), `SupplierContractController`
  (index/accept/reject — **reaproveita `ContractWorkflow` da Fase 4**),
  `SupplierReservationController`, `SupplierActivityController` (index/show; a
  execução reusa os endpoints de my-activities), `SupplierIntentionController`
  (index/respond — **reaproveita `PurchaseIntentionFlow` da Fase 3**).
  `SupplierPayloadService` escopa tudo por `provider_ids`.
- **`ActivityPolicy` estendida**: reconhece o fornecedor pelo `supplier_id` (via
  `TradeUserContext`), não só `supplier_user_id` — faz o my-activities/execução
  funcionar para o portal sem duplicar lógica.
- **Lado do gestor**: `ProviderUserController` (tela de vínculo usuário↔fornecedor)
  + permission `tenant.trade-providers.link` (Gestor Trade/admin).
- **Notificação ao fornecedor** (fecha a pendência da Fase 5): `ActivitySupplierNotifier`
  + `ActivityAssignedSupplierMail` (markdown `trade::mail.activity-assigned`),
  disparado no `ActivityController@store` quando a atividade é para fornecedor.
  Resolve destinatários pelo pivô (+ dono único). **Push fica na Fase 8.**
- **Rotas**: `supplier.php` (8, com o gate) + `providers.php` (3, grupo do gestor).
- **Frontend**: supplier Panel/Contracts/Reservations/Activities/ActivityShow/
  Intentions + providers/Links; grupo de menu "Portal do fornecedor" (gate) e
  item "Acessos do fornecedor" (permission). i18n `supplier.php`/`provider_links.php`.

**Decisões/desvios importantes desta fase:**
- **`isSupplier` = tem vínculo** (pivô OU `Provider.user_id`), não uma coluna
  `type` — o host não tem `User.type`. O fallback ao dono único evita religar à
  mão os fornecedores que o host já criou.
- **Aceite reflete no tenant reaproveitando a Fase 4**: `recordSupplierResponse`
  + `refreshApprovalState` já levam o contrato a `approved` quando a aprovação
  interna também terminou — verificado no smoke (accept → `status=approved`).
- **Portal reusa serviços dos dois lados da mesa** (contrato/intenção/execução):
  nada de regra de negócio reimplementada no portal, só payloads escopados.
- **Execução do fornecedor pelos endpoints de my-activities**: a policy passou a
  reconhecer o fornecedor, então não há um segundo conjunto de rotas de execução.
- **E-mail só no `@store`** (fora de transação); a montagem gerada pelo observer
  não dispara e-mail para não emitir efeito dentro da transação do workflow — o
  fornecedor vê a montagem no portal.

**Verificado:** migrations nos tenants (2 pivôs), `route:list` (8 supplier + 3
provider-links), `trade:permissions:sync` (55 permissions), pint, eslint (fixes
de estilo + 1 unused-var real corrigido), `npm run build` (7 páginas do portal
no manifest), `TradeModelsUseTenantConnectionTest` (24 models). Tinker numa
transação com rollback: pivô ULID sem 23502 (owner/id preenchidos), `isSupplier`
false→true, `providerIds` resolvido, contrato enviado→pending→`can_respond`,
**aceite do fornecedor → `supplier_approval_status=accepted` e `status=approved`**,
`pending_contracts` esvazia, `panel`/`reservationRows` sem erro, notifier
resolvendo o destinatário pelo pivô.

**Pendências conhecidas:** sem testes automatizados de feature (padrão das fases
anteriores); **push web** ao fornecedor fica para a **Fase 8** (só e-mail agora);
`trade_store_user` criado (D7 completo) mas sem portal de executor de loja
próprio — o my-activities da Fase 5 já serve o executor por responsável/etapa;
`supplier_user_id` continua sem seletor nos formulários de gestão (agora
possível com o pivô, mas não priorizado); render HTTP real das telas não rodou
pelo harness de tinker. A mudança **não relacionada** `config/filesystems.php`
(+ teste) segue fora dos commits das fases.

## O que a Fase 5 entregou

- **Migrations** (pacote): `trade_activity_types`, `trade_workflow_step_templates`,
  `trade_activities` (consolida as ~16 incrementais da origem, com SoftDeletes),
  `trade_workflow_steps`, `trade_activity_audits`.
- **Enums**: `ActivityStatus` (pendente→em_andamento→concluida/cancelada, com
  canStart/canComplete/canCancel/isClosed), `ActivityPriority`,
  `ActivityTargetType` (loja/fornecedor), `ActivityApprovalStatus`, `ProofStatus`
  (submitted/approved/rejected, `canReview`), `WorkflowStepStatus`,
  `ActivityAuditEvent`.
- **Models**: `Activity` (**três eixos independentes**: `status` = andamento,
  `approval_status` = aprovação do planejamento, `proof_status` = comprovação por
  foto; `situacao` unificada; link público `share_token`+senha com validade
  início+7d; `scopeForUser`/`scopeForSupplier`; `userIsSupplierExecutor`;
  moveToStep/moveToNext/Previous), `ActivityType` (slug por dono; `is_audit`+
  `audit_config`), `WorkflowStep`, `WorkflowStepTemplate` (allowed_users,
  duplicate), `ActivityAudit`.
- **Serviços** (`Services/Activities/`): `ActivityWorkflow` (start/complete/cancel
  + submitProof/reviewProof — aprovar comprovação conclui a atividade),
  `WorkflowStepService` (addStep/applyTemplateCategory/reorder + **recálculo em
  cascata das datas**: cada etapa começa no dia seguinte ao fim da anterior),
  `SupplierActivityGenerator` (montagem a partir da reserva, idempotente),
  `ActivityPayloadService`, `ActivityMetricsService`, `ActivityMediaService`
  (fotos + foto do espaço com histórico próprio), `ActivityAuditLogger`.
- **Observer**: `ReservationObserver` registrado no provider — confirmar uma
  reserva gera a atividade de montagem do fornecedor (o gancho comercial→operacional).
- **Policies**: `ActivityPolicy` (gestão por permission; ver/editar liberam
  também por instância — responsável, executor do fornecedor, ou responsável de
  etapa; aprovar **nunca** por quem executa), `ActivityTypePolicy`,
  `WorkflowStepTemplatePolicy`. **+17 permissions** (71 no total); Comprador Trade
  planeja atividades e Executor Loja ganhou view/update/execute.
- **Controllers + rotas** (`activities.php`, 55 rotas + `public.php`, 9):
  `ActivityController` (index deferido/kanban/CRUD + approve/reject/resetApproval
  + start/complete/cancel), `WorkflowStepController` (store/applyTemplate/reorder/
  recalculate/move + transições de etapa), `MyActivityController` (execução:
  checklist/status/fotos/proof/reviewProof/spaceImage/share),
  `ActivityTypeController`, `WorkflowStepTemplateController` (+duplicate/toggle),
  `Public\PublicActivityController` (show/unlock + mutações guardadas por token).
- **Frontend**: activities Index/Kanban/Form/Show, my-activities Index/Show,
  activity-types Index/Form, workflow-templates Index/Form, public Lock/Execution;
  componentes `ActivityChecklist`, `WorkflowStepsCard`, `ActivityExecutionPanel`.
  i18n em 5 arquivos; menu com Atividades/Minhas atividades/Tipos/Templates.

**Decisões/desvios importantes desta fase:**
- **Execução pública sem middleware de token**: o host é subdomínio-por-tenant,
  então o `NeedsTenant` resolve o tenant pelo domínio do próprio link antes de o
  controller procurar o `share_token` — o D13 (`ResolveTenantFromShareToken`) foi
  desnecessário. `public.php` roda com `['web', NeedsTenant]`, sem `auth`.
- **Backend calcula/guarda o estado da comprovação**: aprovar a comprovação
  conclui a atividade; reprovar exige motivo e devolve para novo envio; reenvio
  limpa a decisão anterior. A comprovação é analisada por quem **não** enviou.
- **Foto do espaço ≠ evidência**: a foto do espaço tem histórico próprio em
  `trade_space_images` e é o estado atual do espaço; não entra em `activities.fotos`.
- **Ações de estado devolvem `back()` + toast** (não JSON como a origem).
- **Kanban dependency-free**: colunas por status com ações inline
  (start/complete/cancel), sem `vue-draggable-plus` (não instalado no host).
- **`ActivityChecklistTemplates::mergeActions`**: o cliente só decide `completed`;
  a `action` (metadado do servidor, ex.: troca da foto do espaço) é preservada.

**Verificado:** migrations em todos os tenants (5 novas), `route:list`
(55 activities/workflow + 9 públicas), `trade:permissions:sync` (54 permissions,
já com as de atividades), pint, eslint (21 correções de estilo aplicadas),
`npm run build` (Kanban + Form chunks no manifest), `TradeModelsUseTenantConnectionTest`
(22 models na conexão de tenant). Tinker numa transação com rollback cobrindo:
tipo/template com slug por dono, checklist semeado, `applyTemplateCategory` +
datas em cascata, transições de etapa, mover etapa atual, andamento + ciclo da
comprovação (submitted→rejected→reenvio→approved→concluída), link público com
senha (validade/checagem), e o **observer reserva-confirmada→montagem** gerando 1
atividade idempotente (alvo fornecedor, checklist de 6 itens, requires_photo_proof);
`ActivityPayloadService::{show,metrics,options}` exercitados contra o schema do tenant.

**Pendências conhecidas:** sem testes automatizados de feature (ambiente local
quebrado — padrão das fases anteriores); render HTTP real das telas não rodou pelo
harness de tinker, mas os builders de payload foram exercitados direto;
**notificação ao fornecedor** (e-mail do `ActivitySupplierNotifier` + push
`FieldPush` da origem) fica para a **Fase 6** (portal do fornecedor, depende do
pivô `trade_provider_user`, D7); **PWA `/campo`** para a **Fase 8**;
`supplier_user_id` tem coluna mas ainda sem seletor no formulário (mesma D7);
wiring de deploy das migrations de tenant continua pendente (ver memory
`trade-tenant-migrations-path`). Há uma mudança **não relacionada** pendente no
host (`config/filesystems.php` + `PublicDiskUrlIsRootRelativeTest`, URL
root-relative do disco público para CORS do Konva) deixada fora do commit da Fase 5.

## O que a Fase 4 entregou

- **Migrations** (pacote): `trade_contracts` (consolida as 10 migrations
  incrementais da origem), `trade_contract_reservation` (pivô, `unique` em
  reservation_id — uma ação só num contrato), `trade_contract_attachments`,
  `trade_contract_internal_approvals`.
- **Enums**: `ContractStatus` (draft→pending→approved→active→completed +
  cancelled/suspended, com `canEdit/canActivate/canComplete/canSuspend/canCancel/
  canDelete`), `AgreementType` (from_actions/fixed_monthly/one_off, com
  `usesFixedAmount/usesStoreScope/requiresReservations`), `ContractScope`,
  `ContractScopeApply`, `BillingType`, `AdjustmentIndex`, `AdjustmentFrequency`,
  `SupplierApprovalStatus`, `ContractApprovalStep` (mapeia etapa→permission),
  `ContractApprovalStatus`, `ContractDocumentType`.
- **Models**: `Contract` (2 trilhas: `status` = vigência, `supplier_approval_status`
  = fornecedor; `hasAllInternalApprovals`, `appendToChangeLog/NegotiationHistory`,
  scopes live/forSupplier/inPeriod), `ContractReservation` (pivô ULID),
  `ContractAttachment`, `ContractInternalApproval`. `Reservation` ganhou
  `contracts()`.
- **Serviços** (`Services/Contracts/`): `ContractNumberGenerator` (sequencial
  por cliente/mês, `CONT-AAAAMM0001` — sem o sorteio-que-colide da origem),
  `ContractPricing` (backend calcula total/final; das ações ou do valor fixo),
  `ContractApprovalFlow` (4 estágios em ordem, `ensureSteps` idempotente,
  `reopenAfterRejection`), `ContractWorkflow` (sendToSupplier/recordSupplierResponse/
  registerRenegotiation/refreshApprovalState/activate/complete/suspend/cancel/
  cancelDefinitively), `ContractAttachmentService`, `ContractMetricsService`,
  `ContractPayloadService` (row/show/formData/termSheet).
- **Policies**: `ContractPolicy` (view/create/update/delete + `manage` para
  vigência + `approveStep` resolvendo permission por etapa). 11 permissions
  novas (48 no total); 4 roles de aprovação (analista/gerente-comercial/
  financeiro/diretoria); Comprador Trade monta o contrato mas não aprova.
- **Controllers + `routes/contracts.php`** (19 rotas): `ContractController`
  (index deferido/create/store/show/edit/update/destroy/restore +
  sendToSupplier/recordSupplierResponse/activate/complete/suspend/cancel/
  cancelDefinitively), `ContractAttachmentController` (store/destroy),
  `ContractApprovalController` (approve/reject).
- **Frontend**: páginas contracts Index/Form/Show; componentes
  `ContractApprovalsCard`, `ContractAttachmentsCard` (upload multipart via
  router.post), `ContractNegotiationCard`; term sheet na Show. i18n
  `contracts.php`; item de menu "Contratos" (ícone `file-text`).

**Decisões/desvios importantes desta fase:**
- **Backend calcula os valores** (ContractPricing) — a origem deixava o frontend
  mandar total/final. A tela mostra prévia com a mesma regra em TS.
- **Número sequencial por cliente/mês**, lido com `withTrashed` para um contrato
  apagado não devolver o número ao bolo — a origem sorteava 4 dígitos contra
  uma coluna única (colisão só na hora de salvar).
- **Uma permission por estágio** de aprovação, em vez dos slugs de role fixos
  na base landlord da origem: o pacote autoriza sem saber como o host nomeia
  papéis (`ContractApprovalStep::permission()` + `ContractPolicy::approveStep`).
- **Duas trilhas separadas**: aprovação interna (4 etapas) × aceite do fornecedor.
  `refreshApprovalState` só marca `approved` quando as duas terminam; `activate`
  reexige as duas (a tela pode estar velha).
- **Registrar resposta do fornecedor pelo gestor** (aceite/recusa por telefone/
  e-mail) — o portal da Fase 6 chama o mesmo `recordSupplierResponse`.
- **Reabre as etapas internas** ao reeditar um contrato recusado
  (`reopenAfterRejection`); a origem travava para sempre nesse caso.
- **`unique(reservation_id)`** no pivô garante 1 ação = 1 contrato no banco; o
  form request traduz o 23505 em erro de formulário (validateReservationsAreFree,
  ignorando o próprio contrato em edição).
- **Ações de estado devolvem `back()` + toast**, não JSON como na origem.

**Verificado:** migrations em todos os tenants (4 novas), `route:list`
(19 rotas contracts), `trade:permissions:sync` (48 permissions, 4 roles novas),
pint, eslint (cascas), `npm run build` (3 páginas + ContractController chunk no
manifest), `TradeModelsUseTenantConnectionTest` (17 models na conexão de tenant,
falha comprovada sem a correção). Tinker numa transação com rollback cobrindo:
tenant_id preenchido pelo BelongsToTradeOwner, número sequencial + incremento,
pricing (fixo e das ações), 4 etapas idempotentes, aprovar fora de ordem
recusado, ciclo completo interno→envio→ativar-sem-aceite-recusado→aceite→approved
→vigência→encerramento, pivô ULID com escopo não-nulo, availableReservations
excluindo ação já vinculada (mas mantendo a do próprio contrato) e policy
negando usuário sem role; `ContractPayloadService::{formData,show}` e
`ContractMetricsService::build` exercitados contra o schema do tenant.

**Pendências conhecidas:** sem testes automatizados de feature (ambiente local
quebrado — sqlite sem schema landlord; verificação por tinker + build, padrão
das Fases 1–3); render HTTP real das 3 páginas GET não rodou (o harness de
tinker esbarra em auth-guard na conexão de tenant), mas os builders de payload
que as telas consomem foram exercitados direto; **notificação ao fornecedor**
(sininho Gomark + e-mail do `ContractSupplierNotifier` da origem) fica para a
Fase 6 (portal do fornecedor), junto com `supplier_user_id` no formulário
(depende do pivô provider↔user, D7); wiring de deploy das migrations de tenant
continua pendente (ver memory `trade-tenant-migrations-path`).

## O que a Fase 3 entregou

- **Migrations** (pacote): `trade_reservations`, `trade_reservation_store_map` (pivô multi-loja)
  e `trade_purchase_intentions`.
- **Enums**: `ReservationStatus` (com `canApprove/canStart/canComplete/canCancel/canEdit` e
  `blockingValues`), `PaymentStatus`, `PaymentMethod`, `IntentionStatus` (+`openValues`),
  `IntentionTiming`. `PricePeriod` ganhou `days()`.
- **Models**: `Reservation` (scopes `blocking`/`forSpace`/`overlapping`, `appendToChangeLog`),
  `PurchaseIntention` (scopes `open`/`forSupplier`), `ReservationStoreMap` (pivô como model —
  ver armadilha abaixo). `Space` ganhou `reservations()`, `activeReservations()` e
  `purchaseIntentions()`.
- **Serviços** (`Services/Reservations/`): `ReservationPricing` (duração + total/final),
  `ReservationConflictChecker` (espaço × período × plantas), `ReservationWorkflow`
  (approve/start/complete/cancel + ocupação + change_log), `ReservationMetricsService`,
  `ReservationPayloadService`, `PurchaseIntentionFlow` (gestor + fornecedor + conversão).
- **`SpaceStatusResolver` refinado**: status derivado das reservas (`em_negociacao` /
  `reserved` / `occupied`); bloqueio/manutenção/inativo manuais vencem a derivação.
  `SpaceOccupationRecorder` ganhou `openReservation()`/`closeReservation()`.
- **Policies** `ReservationPolicy` (+`approve`) e `PurchaseIntentionPolicy` (+`respond`,
  `convert`); 11 permissions novas (27 no total); `Comprador Trade` recebeu criar/editar
  ações e responder intenções.
- **Controllers + `routes/reservations.php`** (15 rotas): `ReservationController`
  (index deferido/create/store/show/edit/update/destroy/restore + approve/start/complete/
  cancel) e `PurchaseIntentionController` (index/store/respond/convert).
- **Frontend**: páginas reservations Index/Form/Show e purchase-intentions Index; componentes
  `StatusPill`, `IntentionRespondModal`, `IntentionConvertModal`, `IntentionCreateModal`;
  helpers em `@trade/support/format`. i18n em `reservations.php`/`purchase_intentions.php`;
  itens de menu "Ações" e "Intenções de compra" (ícones `calendar-check`/`handshake`).

**Decisões/desvios importantes desta fase:**
- **Backend calcula os valores**: na origem o frontend mandava `total_amount`/`final_amount` e o
  backend só gravava — qualquer request podia salvar valor inconsistente. Aqui quem calcula é o
  `ReservationPricing`; a tela mostra só uma prévia (o mesmo cálculo, em TS).
- **`unit_price` + `price_period`** em vez de `weekly_price` (a origem chamava de semanal mesmo
  quando o período era diário ou mensal) e **`map_id`** em vez de `store_map_id`.
- **Transições no `ReservationWorkflow`**, não no controller: cada uma tem efeito colateral
  (ocupação do espaço, histórico) e a Fase 5 pendura a geração de atividades nos mesmos pontos.
- **Ações de estado devolvem `back()` + toast**, não JSON como na origem (padrão do host).
- **Conflito só por reserva confirmada/em andamento** (`blockingValues`) — pendente não trava a
  agenda de ninguém.
- **`PurchaseIntentionFlow` já tem os dois lados da mesa**: `respondAsManager()` e
  `respondAsSupplier()`. O portal da Fase 6 chama o mesmo serviço, sem reimplementar regra.
- **`PurchaseIntentionController@store`** (registrar intenção pelo gestor) **não existe na
  origem** — foi adicionado para o pedido que chega por telefone/e-mail entrar na mesma fila, e
  para a fase ser exercitável pela UI antes do portal do fornecedor.
- **`supplier_user_id`** existe na tabela mas não tem seletor no formulário: depende do pivô
  `trade_provider_user` (D7), que é da Fase 6.

**Armadilha achada (vale para as próximas fases):** pivô com PK ULID + `tenant_id` **não pode**
ser o pivô cru do `belongsToMany` — `attach()`/`sync()` fazem insert direto e o `id` sai `null`
(23502). A solução é um model `Pivot` com `HasUlids + BelongsToTenant` e `->using(...)` na
relação (ver `ReservationStoreMap`). O `trade_space_map` da Fase 2 escapa disso porque só é
escrito pelo `placements()->updateOrCreate`.

**Verificado:** migrations em todos os tenants, `route:list` (52 rotas trade, 15 novas),
`trade:permissions:sync` (27 permissions), pint, eslint, `vue-tsc --noEmit`, `npm run build`
(4 páginas novas no manifest); 29 asserções em tinker numa transação com rollback cobrindo
precificação, pivô multi-loja, status derivado do espaço nos 4 estágios, bloqueio manual
vencendo a derivação, conflito de agenda (e não-conflito de pendente), transição inválida
recusada, ciclo completo contraproposta→aceite→conversão, dupla conversão recusada e policy
negando usuário sem role; render HTTP real (200 + props) das 3 páginas GET e as regras dos
FormRequests validadas contra o schema do tenant.

**Pendências conhecidas:** sem testes automatizados — o ambiente de teste local está quebrado
(sqlite sem schema landlord, ver memory `local-test-env-sqlite-vacuum`), então a verificação
seguiu o padrão das Fases 1/2 (tinker + build); calendário de ações (`spaces/calendar` da
origem) não foi portado — fica para a Fase 7 (dashboards); `attachments` da reserva tem coluna
mas não tem upload na UI; wiring de deploy das migrations de tenant continua pendente (ver
memory `trade-tenant-migrations-path`).

## O que a Fase 2 entregou

- **Migrations** (pacote): `trade_maps` (store_id, image_path/width/height, `layout_data` JSON,
  status draft/published) e `trade_space_map` (colocação de espaço na planta).
- **Colocação = percentual (0–100)** da imagem de fundo — independente de resolução.
  `trade_space_map`: position_x/y, width, height (decimal %), rotation (graus), status.
  Modelada como **entidade de 1ª classe** `SpaceMapPlacement` (não pivô puro).
- **Models**: `Map` (store/user/placements/spaces), `SpaceMapPlacement`; `Space` ganhou
  `maps()`/`placements()`. Enum `MapStatus`. Policy `MapPolicy` + permissions `trade-maps.*`.
- **Serviços**: `MapImageService` (upload + `getimagesize`), `MapPayloadService` (payload do
  editor: mapa + colocações com cor de status + espaços disponíveis da loja).
- **Controller `MapController`** + `routes/maps.php` (9 rotas): index/create/store/edit/update/
  destroy/restore/**configure** (editor)/**saveLayout**. `saveLayout` reconcilia a lista inteira
  (upsert das enviadas, delete das ausentes) + `layout_data`, numa transação.
- **Frontend**: `konva` (imperativo) — **sem vue-konva**. Páginas maps Index/Form/**Editor**;
  componente `MapCanvas.vue` (Konva Stage: imagem de fundo + 1 Group/Rect/Text por espaço,
  Transformer p/ resize/rotate, drag com clamp, zoom/pan Ctrl+scroll). Paleta lateral
  (disponíveis ↔ colocados), botão **Salvar** único (batch), legenda por cor de status.
  Cascas publicadas; item de menu "Mapas" no sidebar (ícone `map`).

**Decisões/desvios importantes desta fase:**
- **konva imperativo em vez de vue-konva**: o bootstrap do app (createInertiaApp via
  `@inertiajs/vite`) não expõe `setup()` para `app.use(VueKonva)`, então registrar o plugin
  global seria frágil. `MapCanvas.vue` usa a API Konva direto (Stage/Layer/Transformer) —
  auto-contido, sem registro global. Satisfaz a intenção do plano (canvas konva).
- **Nova dependência**: `konva@^10.3.0` (adicionada a package.json/lock). vue-konva NÃO foi
  instalada.
- **Geometria por colocação no pivô** (position/width/height percentuais), em vez de guardar
  width/height no espaço como a origem — evita o choque com o `width/height` em cm da Fase 1 e
  deixa o editor auto-contido. Um espaço pode ter tamanhos diferentes em mapas diferentes.
- **Salvar explícito (batch)** em vez do auto-save por-espaço da origem — mais simples e alinhado
  ao padrão Inertia router/back() do host.
- **Membership no pai, geometria no canvas**: `MapCanvas` é dono da geometria durante a edição
  (fluxo one-way via `@update`); o Editor é dono de quais espaços estão no mapa. Evita loops.

**Verificado:** migrations em todos os tenants, `route:list` (9), pint, eslint,
`npm run build` (3 páginas maps no manifest, chunk Editor com konva ~192KB), `trade:permissions:sync`
(16 permissions), e tinker: `Map` na conexão tenant, policy **nega** sem role, e o caminho
completo do editor (Map+placement → `MapPayloadService` → reconcile delete) exercido numa
transação com rollback (cor de status correta, zero efeitos colaterais).

**Pendências conhecidas:** o editor Konva não foi clicado num browser headless (o loop de persist
percent↔backend foi verificado no backend); drag-drop da paleta pro canvas virou "clicar +
para adicionar" (mais robusto); wiring de deploy das migrations de tenant continua pendente
(ver memory `trade-tenant-migrations-path`).

## O que a Fase 1 entregou

- **Migrations** (no pacote, `database/migrations/clients/`): `trade_space_types`,
  `trade_space_prefixes`, `trade_space_categories`, `trade_space_type_prefixes`,
  `trade_spaces`, `trade_space_images`, `trade_space_type_library_items` (global, sem
  tenant_id), `trade_space_occupations`, `trade_store_profiles`, e a aditiva
  `add_trade_fields_to_providers_table` (razao_social/slug/status, nullable, idempotente).
- **Models** (ULID + SoftDeletes + BelongsToTenant + UsesTradeTenantConnection): Space,
  SpaceType, SpacePrefix, SpaceCategory, SpaceImage, SpaceTypeLibraryItem (global),
  SpaceOccupation, StoreProfile. Enums: SpaceStatus, PricePeriod, BillingMode.
- **Policies** (Space/SpaceType/SpacePrefix/SpaceCategory) via `TradePermission`; permissions
  novas `trade-space-types.*`; `trade:permissions:sync` atualizado. Registro no provider.
- **Services**: SpaceStatusResolver, SpaceOccupationRecorder, SpaceMetricsService.
- **Controllers + rotas `trade.php`**: SpaceController (index deferido/create/store/edit/
  update/destroy/restore/block/unblock), SpaceTypeController (+toggle/importFromLibrary),
  SpacePrefixController e SpaceCategoryController (endpoints dos modais). 27 rotas.
- **Frontend** (páginas reais no pacote `@trade/pages/`, cascas publicadas no host):
  spaces Index+Form, space-types Index+Form; modais SpaceCategoryModal, SpacePrefixModal,
  ImportLibraryModal. Padrão do host: `<Form>` + Wayfinder `.form()`, ListPage,
  useCrudPageMeta, useDeferredPaginator, ColumnActions. Sem fetch — o form embute
  prefixos/preço por tipo nas props; modais usam `router.post` com `preserveState`.
- **i18n** em `lang/pt_BR/app/tenant/trade/*.php`; item de menu "Trade Marketing" no
  `SidebarNavigationService` (ícones `shopping-bag`/`tag` em `NavMenuEntry.vue`).

**Decisões/desvios da origem nesta fase:**
- `trade_spaces.store_id` é **singular** (foreignUlid, nullable) — a origem vinculava espaço
  a várias lojas via mapas; o vínculo multi-loja/mapa é da **Fase 2**.
- `trade_space_categories` virou **ULID** (origem era bigint), para consistência.
- Prefixos/categorias não têm páginas próprias — geridos por modais (igual à origem).

**Verificado:** migrations rodaram em todos os tenants (via `--path`, ver
`.claude/` memory `trade-tenant-migrations-path`), `trade:permissions:sync` ok, `route:list`
(27 rotas), pint, eslint, `npm run build` (4 páginas no manifest), e tinker: models na
conexão `tenant`, aditiva do Provider aplicada, policy **nega** usuário sem role.

**Pendências conhecidas (não bloqueiam a fase):** wiring de deploy das migrations de tenant
do pacote (ver memory `trade-tenant-migrations-path`); factories/seeders e um seed do catálogo
`trade_space_type_library_items`; upload de imagem no `show` (não há página show nesta fase).

## O que a Fase 0 entregou

- `composer.json` (padrão plannerate), `LaravelRaptorTradeServiceProvider`, `config/trade.php`
  (models do host, prefixo `trade_`, flags `gomark_api`/`pwa`/`webpush`), `UsesTradeTenantConnection`
- Comandos `trade:migrations:sync`, `trade:publish-pages`, `trade:permissions:sync` + `TradePermission`
- Rota `tenant.trade.dashboard` → página Vue do pacote via casca publicada em
  `resources/js/pages/tenant/trade/dashboard/Index.vue`
- Alias `@trade` no `vite.config.ts` e `tsconfig.json`; build do host compila a página do pacote

## Decisões que valem para todas as fases

- **Models do host, não do pacote**: `User`, `Tenant`, `Provider` (= Parceiro/fornecedor),
  `Store` (= Loja), referenciados por `config('trade.models.*')`.
- **Tabelas com prefixo `trade_`** — o host já tem `WorkflowTemplate`/`WorkflowStep` do planogram.
- **Tenancy**: `HasUlids + SoftDeletes + BelongsToTenant` (trait do host) + `UsesTradeTenantConnection`.
  Nunca setar `tenant_id` na mão; o `scopeTenant()` manual e o global scope do `Contract` da
  origem são descartados.
- **ACL**: a origem usa `User.type` + role `gestor-trade` no landlord; aqui vira
  spatie/permission (`TradePermission`) + serviço `TradeUserContext`. **Ao portar qualquer
  controller, faça grep por `->type`** — a coluna não existe no host.
- **Migrations** ficam em `database/migrations/clients/` do pacote, não são auto-executadas:
  `trade:migrations:sync` e depois
  `tenants:artisan "migrate --database=tenant --path=database/migrations/clients"`
  (o `--path` é obrigatório — ver memory `trade-tenant-migrations-path`).
- **Pivô com PK ULID + `tenant_id` precisa de model `Pivot` + `->using()`** — `attach()`/`sync()`
  fazem insert cru e o `id` sai `null` (23502). Ver `ReservationStoreMap` e a memory
  `trade-ulid-pivot-needs-model`.
- **Sem API Resources**: payloads Inertia montados por Services.
- **Ordem dentro da fase**: backend/rotas → build (Wayfinder gera os helpers) → páginas Vue.

## Migração React → Vue

`@dnd-kit` → `vue-draggable-plus` · `react-konva` → `vue-konva` · `react-rnd` → `Transformer`
do Konva · Radix/shadcn-react → `@/components/ui` do host (reka-ui) · `cmdk` → shadcn-vue
**Command** e `react-day-picker` → **Calendar** (ambos ainda **não existem** no host, adicionar
na Fase 1) · `lucide-react` → `lucide-vue-next` · `@inertiajs/react` → `@inertiajs/vue3`.
