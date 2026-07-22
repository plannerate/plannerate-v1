# Orientação: extrair o motor de integrações para pacote próprio

> **Isto não é o plano.** É o briefing de reconhecimento para a sessão que vai *escrever* o
> plano. Contém o inventário já levantado (não refaça), o mapa de acoplamento e as perguntas
> que o plano precisa responder antes de propor qualquer passo.

## Objetivo e restrição

Tirar toda a área de integrações de ERP — **backend e frontend** — deste repositório e
publicá-la como pacote Composer consumido via **repositório VCS**:

```jsonc
"repositories": [{ "type": "vcs", "url": "git@github.com:<org>/<pacote>.git" }]
```

Explicitamente **não** é `"type": "path"` nem morar em `packages/`. O pacote
`callcocam/laravel-raptor-plannerate` que existe hoje usa `path` apontando para
`./packages/...` — serve de referência de estrutura, mas **não** de modelo de distribuição.

## Inventário do que entra no pacote

Levantado em 22/07/2026. Confira antes de usar; o código mudou muito nos últimos dias.

**Backend — motor**
| Diretório | Arquivos |
|---|---|
| `app/Services/Integrations/` (+ `Discovery/`, `Lookup/`, `Support/`) | 26 |
| `app/Jobs/Integrations/` (+ `Maintenance/`) | 7 |
| `app/Console/Commands/Integrations/` | 11 |

**Backend — modelos, HTTP, config**
- `app/Models/`: `IntegrationApi`, `TenantIntegration`, `IntegrationImportRun`, `IntegrationSyncDay` *(verificar se este último ainda é usado — cheira a legado)*
- `app/Http/Controllers/Landlord/`: `IntegrationApiController`, `TenantIntegrationController`
- `app/Http/Requests/Landlord/`: `StoreIntegrationApiRequest`, `UpdateIntegrationApiRequest`, `ImportIntegrationApiConfigRequest`, `UpdateTenantIntegrationRequest`, `Concerns/NormalizesIntegrationApiRequests`
- `config/integrations.php`
- `routes/landlord.php`: 7 rotas (`integration-apis` resource + `tenants/{tenant}/integration/*`, incluindo `run-import` e `run-post-import`)
- `routes/console.php`: agendamentos `imports:prune`, `integration:run`, `sync:post-import`, `integration:health`
- `config/horizon.php`: supervisores `imports-fetch` e `imports-process` + entradas em `waits`
- `lang/pt_BR/app/landlord/`: `integration_apis.php`, `tenant_integrations.php`

**Migrations** — 8 em `database/migrations/landlord/` (`tenant_integrations`, `integration_apis`, `integration_import_runs`, `integration_sync_days` + blueprints seedados por migration). **Cuidado:** as de `database/migrations/` (`product_store`, `add_store_scoped_metrics_to_product_store_table`) são do *app*, não do pacote — decida de que lado fica a pivot.

**Frontend**
- `resources/js/pages/landlord/tenants/Integration.vue`
- `resources/js/pages/landlord/tenants/integration/`: `ConnectionForm.vue`, `AuthSection.vue`, `TestPanel.vue`, `types.ts`
- `resources/js/pages/landlord/integration-apis/`: `Index.vue`, `Form.vue` + `components/` (5 repeaters + `types.ts`)

**Testes** — 36 arquivos em `tests/Feature/Integrations/` + `tests/Unit/Integrations/`.

## O frontend: casca no app, código no pacote

**Restrição de base:** nenhum pacote deste repositório entrega páginas Inertia. Confirmado —
`find vendor/callcocam -type d -name pages` volta vazio. O resolvedor é o plugin
`@inertiajs/vite` (v3), por convenção sobre `resources/js/pages`; não há
`resolvePageComponent` em `resources/js/app.ts` para interceptar.

**Abordagem definida** (fazer assim, não re-debater):

1. **A página continua no app**, como casca fina. O controller do pacote segue chamando
   `Inertia::render('landlord/tenants/Integration')` — sem namespace novo, sem mexer no
   resolvedor.
2. **O código real vira componente no pacote**, importado pela casca via alias de `vendor/` —
   exatamente o que o `laravel-raptor-plannerate` já faz com sucesso ([vite.config.ts](vite.config.ts) linhas 43-47).
3. **Um comando publica as cascas** (`integrations:publish-pages` ou similar), para instalar e
   reinstalar sem trabalho manual.

Forma esperada da casca — deve ser trivial a ponto de quase nunca mudar:

```vue
<script setup lang="ts">
import TenantIntegrationPage from '@integrations/pages/TenantIntegration.vue';
import type { TenantIntegrationProps } from '@integrations/types';

const props = defineProps<TenantIntegrationProps>();
</script>

<template>
    <TenantIntegrationPage v-bind="props" />
</template>
```

**Por que casca + componente em vez de publicar a UI inteira:** arquivo publicado diverge. Se o
comando copiasse as ~12 telas completas, toda correção no pacote viraria merge manual no app —
e o pacote deixaria de ser atualizável por `composer update`. Com a casca, o que é copiado tem
5 linhas e é estável; o que muda de verdade chega pelo `vendor/`.

**Se importar do pacote se mostrar ruim** (HMR quebrado, `optimizeDeps` brigando, tipos não
resolvendo), o mesmo comando serve de escape: passa a publicar as telas completas e assume-se
o custo do merge. Deixe o comando preparado para os dois modos, mas **entregue o modo casca
como padrão**.

### O que verificar antes de escrever o plano

- **Alias no `vite.config.ts`** — precisa de entrada nova (`@integrations` → `vendor/<org>/<pacote>/resources/js`)
  e de `optimizeDeps.include` para os `.vue`, como já existe para os dois pacotes atuais.
  Isso é edição no app: o comando de instalação deve avisar, ou o README documentar.
- **Wayfinder** — os controllers do pacote precisam gerar actions em `resources/js/actions/`.
  Hoje `wayfinder:generate` varre as rotas registradas; confirme que enxerga rotas vindas de
  service provider de pacote e para onde escreve. Se não enxergar, é bloqueador — o front
  inteiro usa `TenantIntegrationController.runImport.url()` e afins.
- **Tipos** — exporte de dentro do pacote os tipos das props (`IntegrationPayload`,
  `IntegrationTypeOption`, `RequestPathRow`…). A casca deve tipar-se a partir do pacote, senão
  o contrato de props se desalinha em silêncio quando o controller mudar.
- **Traduções** — as telas usam `useT()` com chaves `app.landlord.integration_apis.*` e
  `app.landlord.tenant_integrations.*`. Defina se o pacote publica os arquivos de `lang/` ou
  registra um namespace próprio (`integrations::...`) — o segundo evita colisão, mas obriga a
  reescrever todas as chaves nas telas.
- **Dependências de design system** — as telas importam `AppLayout`, `FormCard`,
  `DeleteButton`, `@/components/ui/*`. O componente do pacote vai depender do app para isso
  (alias `@`), ou o pacote traz os seus? Depender do `@` do app é mais barato e é o que o
  plannerate faz, mas amarra o pacote ao design system deste projeto.

## Mapa de acoplamento (o que impede um `git mv`)

Ocorrências de modelos do app dentro do motor:

```
15  App\Models\TenantIntegration      ← do pacote
11  App\Models\Tenant                 ← DO APP (Spatie multitenancy)
 7  App\Models\IntegrationImportRun   ← do pacote
 4  App\Models\IntegrationApi         ← do pacote
 3  App\Models\Store                  ← DO APP
 2  App\Models\Product                ← DO APP
 1  App\Models\User                   ← DO APP
 1  App\Models\EanReference           ← DO APP
```

Pontos concretos a resolver (todos verificáveis no código):

1. **`Tenant`** — o motor chama `$tenant->execute(fn () => ...)` para trocar de conexão, e
   `Tenant::current()`. É contrato do Spatie, mas o *model* é do app. Precisa virar interface,
   config (`integrations.tenant_model`) ou dependência declarada no pacote.
2. **`Store::published()`** em `DiscoverIntegrationPagesJob::loadStores()` e
   `TenantIntegrationController::firstStoreDocument()`. O motor decide *quais lojas buscar*
   lendo um model do app.
3. **`TenantNaturalKeyReconciler::NATURAL_KEYS`** — constante que **fixa em código** as chaves
   naturais de `products` (`['ean']`) e `sales`. É conhecimento de domínio do app dentro do
   motor genérico. Provavelmente a peça mais indigesta.
4. **`config/integrations.php` → `field_map_tables`** — whitelist de colunas de `products`,
   `sales` e `stores`. Idem: domínio do app, mas o motor depende dela para validar `target`.
5. **`import_clear_tables`** na mesma config, também com nomes de tabela do app.
6. **Autorização** — os controllers fazem `$this->authorize('update', $tenant)`, contra a
   policy de Tenant do app.
7. **`Product` / `EanReference`** — usados pelos services de pós-importação
   (`SyncProductsFromEanReferencesService`, `SyncSalesProductReferencesService`,
   `SyncLayerProductsByEanService`). **Pergunte-se se esses serviços são do motor ou do app** —
   eles falam de planograma e catálogo, não de integração.
8. **Frontend** — as páginas importam `AppLayout`, `useT`, `tenantWayfinderPath`,
   `@/components/ui/*`, `@/components/FormCard.vue`, `DeleteButton.vue`. O pacote passa a
   depender do design system do app, ou traz o seu.

## Escopo: fechado — vai tudo

**Decidido:** tudo que é relacionado à importação via API vai para o pacote — comandos, jobs,
services, models, migrations, controllers, requests, rotas, config, traduções, telas e testes.
Isso **inclui o pipeline pós-importação** (`sync:post-import` e a cadeia
`sync:link-sales` → `sync:cleanup` → `sync:products-from-ean-references` →
`monthly-sales:recalculate`), que é a segunda metade do ciclo de import, não algo à parte.

Não perca tempo debatendo fronteira. A pergunta que sobra é outra e mais concreta:

> **De que superfície do app o pacote precisa depender — e como declará-la?**

Porque levar tudo não elimina o acoplamento; converte "o que fica" em "o que vira contrato".

### Como o acoplamento se distribui (medido por arquivo)

A boa notícia: **a maior parte acessa tabela por nome via query builder, não por model Eloquent.**
Nome de tabela vira config trivialmente; classe de model exige contrato.

| Peça | O que alcança | Tipo |
|---|---|---|
| `SyncSalesProductReferencesService` | `sales` | tabela |
| `SyncLayerProductsByEanService` | `layers`, `products` | tabela |
| `LayerOrphanProductsReportService` | `products` | tabela |
| `RecalculateMonthlySalesSummariesService` | `sales`, `monthly_sales_summaries` + `Tenant` | tabela + model |
| `SyncProductsFromEanReferencesService` | `products`, `categories` + `EanReference` | tabela + **model** |
| `DiscoverIntegrationPagesJob`, `TenantIntegrationController` | `Store::published()` | **model** |
| `TenantNaturalKeyReconciler` | `NATURAL_KEYS` de `products`/`sales` | **constante em código** |
| `config/integrations.php` | `field_map_tables`, `import_clear_tables` | já é config |
| Comandos em geral | `Tenant`, `TenantIntegration` | model (Tenant é contrato Spatie) |

Puros, sem nenhum acoplamento — vão direto: `DeterministicIdGenerator`, `ImportDiscardMetrics`,
`ImportQueueMonitor`, `IntegrationPaginationMode`, `IntegrationResponseGuard`,
`IntegrationUrlBuilder`.

### O que o plano precisa desenhar a partir disso

1. **Models do app que o pacote precisa resolver**: `Tenant`, `Store`, `EanReference`
   (`Product` aparece pouco e quase sempre via tabela). Config apontando a classe
   (`integrations.models.tenant`), interface, ou binding no container — escolha e justifique.
2. **`Store::published()`** é o caso mais delicado: não é só a classe, é um *scope* com regra
   de negócio do app. O pacote precisa de uma forma de perguntar "quais lojas importar" sem
   conhecer `Store`.
3. **`TenantNaturalKeyReconciler::NATURAL_KEYS`** precisa sair de constante para configuração
   publicável — é a peça que hoje trava o motor em `products`/`sales`.
4. **Tabelas por nome** (`layers`, `categories`, `monthly_sales_summaries`, `product_store`…)
   viram config com defaults. Barato, mas precisa ser exaustivo: um nome esquecido só aparece
   em runtime, no tenant do cliente.
5. **A pivot `product_store`** e as colunas por loja (`current_stock`, `last_purchase_date`)
   foram criadas em `database/migrations/` — migration do *app*. Decida se a tabela é do app
   (e o pacote só escreve nela) ou se vai junto.

## Armadilhas específicas deste repositório

Estão documentadas em [.claude/nova-integracao.md](.claude/nova-integracao.md) — leia §7 inteira. As que mais afetam a extração:

- **§7.9/7.10** — chave nova do blueprint precisa aparecer na UI, senão salvar pela tela a
  apaga. Se a UI for para o pacote e o blueprint continuar sendo estendido pelo app, esse
  contrato fica atravessando a fronteira.
- **`HasSlug` renomeia em todo `save()`** — `IntegrationApi` usa `callcocam/tall-sluggable`.
  Se o pacote sair, essa dependência vai junto (e o comportamento é uma armadilha conhecida:
  migrations precisam gravar via `->toBase()->update()`).
- **Migrations landlord não rodam no `migrate` padrão** — exigem
  `--path=database/migrations/landlord --database=landlord`. Um pacote publicando migrations
  precisa resolver isso; hoje é convenção manual do app.
- **`queues_are_tenant_aware_by_default = true`** — todo job do pacote precisa declarar
  `NotTenantAware` (foi causa de bug real, ver `RunIntegrationPipelineJob`).
- **`migrate:fresh` é proibido** neste projeto, em qualquer conexão.
- **Snapshots em `storage/app/private/last_payload/{tenant_id}.json`** — gravados pelo
  `TenantIntegrationController::update()` e a única cópia de blueprint + credenciais fora do
  banco. Existe `integration:restore-snapshot` para restaurar. Decida de que lado do pacote
  fica esse mecanismo; ele já salvou a integração uma vez.

## O que o plano precisa entregar

1. **Superfície de contrato** — a lista fechada do que o pacote precisa do app (models,
   tabelas, scopes) e o mecanismo de cada um. O escopo do que *migra* já está decidido: tudo.
2. **Distribuição do frontend** — a abordagem já está definida (casca no app + componente no
   pacote + comando de publicação). O plano precisa provar que ela funciona de ponta a ponta:
   alias, `optimizeDeps`, Wayfinder das rotas do pacote, tipos e traduções.
3. **Pontos de extensão** — como o app registra chaves naturais, colunas mapeáveis, nomes de
   tabela e a fonte das lojas a importar.
4. **Estratégia de migração** — o motor está em produção com dados reais (RP Info: ~21 mil
   produtos, ~560 mil vendas). O plano precisa dizer como sair do estado atual sem parar
   importação, e como versionar os blueprints já seedados por migration.
5. **Testes** — os 36 arquivos vão para o pacote? Rodam com que banco? Hoje dependem de
   `migrate:fresh --database=landlord` no `beforeEach` e **um arquivo por invocação**.
6. **Verificação** — como provar que a extração não quebrou nada. Existe base real para
   comparar: tenant `RPInfo` (`01ky3c72cc412acxz8zt7tt0ds`), 3 lojas, blueprints `rpinfo`,
   `sysmo`, `gescooper`.

## Por onde começar a ler

1. [.claude/nova-integracao.md](.claude/nova-integracao.md) — como o motor funciona e por que cada decisão foi tomada
2. `app/Services/Integrations/TenantNaturalKeyReconciler.php` — o acoplamento mais profundo
3. `config/integrations.php` — a fronteira de domínio já materializada em config
4. [vite.config.ts](vite.config.ts) e `resources/js/app.ts` — como o frontend de pacote é (e não é) resolvido hoje
5. `packages/callcocam/laravel-raptor-plannerate/` — o precedente de pacote no projeto, com o que ele **não** resolve (páginas)
