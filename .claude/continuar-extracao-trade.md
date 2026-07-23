# Continuar a extração do domínio Trade

Passagem de bastão entre sessões. **Fase 0 (fundação) está pronta e verificada.** Cada fase
seguinte é executada numa sessão nova, sempre a partir deste repositório.

Plano completo (10 fases, decisões arquiteturais, riscos), versionado com o código:
`packages/callcocam/laravel-raptor-trade/docs/PLANO.md`

## Como abrir a sessão de uma fase

Rode o Claude **daqui** (`/home/caltj/projects/plannerate-v1`) e peça, por exemplo:

> Execute a Fase 1 do plano em `.claude/continuar-extracao-trade.md`. O código-fonte de
> origem está em `/home/caltj/projects/space-trade` (React) e vai ser reescrito em Vue.

A leitura de `/home/caltj/projects/space-trade/**` já está liberada em
`.claude/settings.local.json` — não precisa de `--add-dir`.

---

## Onde as coisas estão

| O quê | Onde |
|---|---|
| Pacote | `packages/callcocam/laravel-raptor-trade` (path repository, symlink em `vendor/`) |
| Projeto de origem | `/home/caltj/projects/space-trade` — Laravel 12 + Inertia + **React 19** |
| Docs de negócio da origem | `space-trade/docs/contrato.md`, `docs/contracts.md`, `docs/workflow/*` |
| Padrão de backend | `packages/callcocam/laravel-raptor-plannerate` |
| Padrão de frontend (cascas + alias) | `/home/caltj/projects/laravel-integrations` |
| Rotas de origem (fonte da paridade) | `space-trade/routes/{web,maps,supplier,api}.php` — 131 + 67 + 25 rotas |

## Rodar as coisas

Não há PHP nem Composer no host — tudo via container `plannerate-v1-php-1`:

```bash
docker exec plannerate-v1-php-1 sh -lc 'cd /var/www && php artisan route:list --path=trade'
docker exec plannerate-v1-php-1 sh -lc 'cd /var/www && ./vendor/bin/pint packages/callcocam/laravel-raptor-trade'
docker exec plannerate-v1-php-1 sh -lc 'cd /var/www && php artisan test --compact <path>'
```

Composer **exige** o token do GitHub (o repositório VCS privado de `laravel-integrations`
bloqueia a resolução inteira sem credencial):

```bash
TOKEN=$(gh auth token)
docker exec -e COMPOSER_HOME=/tmp/composer \
  -e COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"$TOKEN\"}}" \
  plannerate-v1-php-1 sh -lc 'cd /var/www && composer update callcocam/laravel-raptor-trade'
```

Frontend, no host: `VITE_ENABLE_WAYFINDER=false npm run build`.

Comandos do pacote: `trade:publish-pages`, `trade:migrations:sync`, `trade:permissions:sync`.

---

## Estado das fases

| Fase | Escopo | Status |
|---|---|---|
| 0 | Fundação: composer, provider, config, comandos, alias `@trade`, página de teste | **pronta** |
| 1 | Espaços + `trade_store_profiles` + aditiva no `Provider` do host | **pronta** |
| 2 | Mapas / planta da loja (konva + vue-konva) — **maior risco** | **pronta** |
| 3 | Reservas + intenções de compra | **pronta** |
| 4 | Contratos (+ anexos, aprovações internas, rounds de negociação) | a fazer |
| 5 | Atividades, workflow/kanban, proofs, execução pública por token | a fazer |
| 6 | Portal do Fornecedor | a fazer |
| 7 | Dashboards | a fazer |
| 8 | PWA `/campo` + web push | a fazer |
| 9 | API interna Gomark (feature flag) | a fazer |
| 10 | Paridade rota-a-rota e endurecimento | a fazer |

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
  `trade:migrations:sync` e depois `tenants:artisan "migrate --database=tenant"`.
- **Sem API Resources**: payloads Inertia montados por Services.
- **Ordem dentro da fase**: backend/rotas → build (Wayfinder gera os helpers) → páginas Vue.

## Migração React → Vue

`@dnd-kit` → `vue-draggable-plus` · `react-konva` → `vue-konva` · `react-rnd` → `Transformer`
do Konva · Radix/shadcn-react → `@/components/ui` do host (reka-ui) · `cmdk` → shadcn-vue
**Command** e `react-day-picker` → **Calendar** (ambos ainda **não existem** no host, adicionar
na Fase 1) · `lucide-react` → `lucide-vue-next` · `@inertiajs/react` → `@inertiajs/vue3`.
