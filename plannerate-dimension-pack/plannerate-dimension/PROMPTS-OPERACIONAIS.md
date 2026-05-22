# Prompts Operacionais — Pesquisa de Dimensões (Plannerate)

> Cole estes prompts no Claude Code / Cursor / Copilot Chat dentro do
> projeto Plannerate. As convenções do projeto estão em CLAUDE.md +
> CLAUDE-APPEND.md (este pacote).

---

## PROMPT 1 — Scaffolding inicial (cola uma vez)

```
Implemente o pipeline de pesquisa de dimensões de produto seguindo
ESTRITAMENTE as convenções já estabelecidas neste projeto:

- Multi-tenancy Spatie v4 (NÃO usar single-database scope)
- Docker compose personalizado (NUNCA Sail, NUNCA comando direto sem docker compose exec)
- Wayfinder pra rotas TypeScript (NUNCA hardcodar URL no Vue)
- Pest 4 pra testes, com tenant albert (01jym02qk8n1cwdq2hd5drpgsz)
- Pint --format agent obrigatório ao final de edits PHP
- category_id como FK pra binding (sem grouping_normalized)
- Inertia v3 + Vue 3 Composition API + TypeScript
- Echo Vue pra real-time
- Horizon pra filas

Antes de qualquer coisa:

1. Ativar skills relevantes:
   - laravel-best-practices (sempre)
   - pest-testing (ao escrever testes)
   - inertia-vue-development (ao escrever Vue)
   - wayfinder-development (ao gerar rotas)
   - tailwindcss-development (ao estilizar)
   - echo-vue-development (ao implementar real-time)
   - configuring-horizon (ao mexer em config/horizon.php)

2. Usar Boost tools:
   - database-schema pra inspecionar tabela products antes de migration
   - search-docs com queries topic-based antes de cada mudança
   - database-query pra validar dados de teste

3. Verificar arquivos siblings antes de criar novos (convenções existentes)

ETAPAS — pare entre cada uma esperando meu OK:

═══ ETAPA 1 — Inspeção e plano ═══
- Rodar database-schema na tabela products do tenant albert
- Listar campos existentes que já cubram dimensões (se houver)
- Confirmar que pgvector está habilitado no banco
- Verificar config/services.php pra credenciais Cosmos existentes
- Verificar config/ai.php (se existe)
- Listar arquivos que vão ser criados/modificados
- Apontar dependências composer/npm que faltam
- Confirmar se devo criar tabela cache CENTRAL (não-tenant) ou tenant-scoped
- ESPERAR OK

═══ ETAPA 2 — Schema ═══
- Migration tenant: add_dimension_research_fields_to_products_table
  (campos no CLAUDE-APPEND.md, inclusive vector(768) com driver check)
- Migration central: create_dimension_research_cache_table
- App\Enums\DimensionStatus (com label() e color() em PT-BR)
- Atualizar App\Models\Product:
  - casts dos novos campos
  - relação similarTo() belongsTo self
  - scopes: awaitingApproval, withApprovedDimensions, needingResearch
- App\Models\DimensionResearchCache (model central, sem tenant scope)
- App\Observers\ProductDescriptionEmbeddingObserver
  (gera embedding via Str::of()->toEmbeddings() ao criar/atualizar description)
- Registrar observer no AppServiceProvider
- Rodar:
  - docker compose exec php php artisan migrate (central)
  - docker compose exec php php artisan tenants:artisan "migrate --database=tenant"
- Rodar Pint
- Escrever test que verifica enum, casts, scopes
- ESPERAR OK

═══ ETAPA 3 — Tools ═══
- App\Ai\Tools\SearchLocalProductDimensions
  - Input schema: description, category_id, measurement_unit, net_content, packaging_type
  - Pipeline interno:
    a) Query Eloquent com filtros rígidos (category_id exato, unit exato,
       net_content ±5%, packaging_type exato, dimension_status=approved)
    b) Sobre o resultado, refina com whereVectorSimilarTo na description_embedding
       usando o texto consultado (minSimilarity: 0.75)
  - Retorna até 5 candidatos com ean, description, brand, dimensions,
    similarity_score, match_reasons[]
  - Se vazio: {"found": false}
  - Respeita global scope multi-tenant

- App\Ai\Tools\FetchCosmosBluesoft
  - Input schema: ean (string)
  - Antes de chamar API: consulta DimensionResearchCache (cache central)
  - Se cache miss: HTTP call com Http::withToken(config('services.cosmos.token'))
  - Endpoint: https://api.cosmos.bluesoft.com.br/gtins/{ean}
  - Persiste no cache central (TTL 90 dias)
  - Trata 404 retornando {"found": false}
  - Trata 429 com retry backoff exponencial

- Testes Pest pra ambos os tools
- Pint
- ESPERAR OK

═══ ETAPA 4 — Agent ═══
- App\Ai\Agents\ProductDimensionResearcher
  - implements Agent, HasStructuredOutput, HasTools
  - #[Provider(Lab::Gemini)] #[Model('gemini-2.5-flash')]
  - Construtor recebe Product
  - instructions(): file_get_contents do arquivo
    resources/ai/dimension-researcher-instructions.txt
    (criar esse arquivo com conteúdo de prompts/agent-instructions.txt deste pacote)
  - tools():
    [
      new SearchLocalProductDimensions,
      new FetchCosmosBluesoft,
      (new WebSearch)->max(5)->allow([
          'paodeacucar.com', 'carrefour.com.br', 'atacadao.com.br',
          'cosmos.bluesoft.com.br', 'mercadolivre.com.br',
      ]),
    ]
  - schema(): conforme spec do CLAUDE-APPEND.md
  
- Test usando ProductDimensionResearcher::fake() do AI SDK
- Pint
- ESPERAR OK

═══ ETAPA 5 — Job, Service, Event ═══
- App\Events\ProductDimensionResearched
  - implements ShouldBroadcast
  - broadcastOn: new PrivateChannel("tenant.{$tenant_id}.dimensions")
  - broadcastWith: dados do produto + status

- App\Jobs\ResearchProductDimensionsJob
  - ShouldQueue, queue('ai-research'), tries=3, backoff=[60, 300, 900]
  - Verifica Cache::lock('gemini-rate-limit') antes
  - Monta prompt do template prompts/user-prompt-template.txt
  - Aplica sanity checks ANTES de persistir
  - Sempre seta dimension_status=awaiting_approval se found=true
  - Dispara ProductDimensionResearched ao final
  - Tag('ai-research', 'dimensions', "tenant:{$tenant_id}")

- App\Services\ProductDimensionService
  - research(Product $product): void
  - approve(Product $product, User $user): void
  - reject(Product $product, User $user, string $reason): void
  - dispatchPendingBatch(int $limit = 50): int

- Atualizar config/horizon.php: supervisor ai-research
  (maxProcesses: 3, balance: simple, queue: [ai-research])

- Testes feature pro Service e Job
- Pint
- ESPERAR OK

═══ ETAPA 6 — Controller, rotas, Wayfinder ═══
- App\Http\Controllers\Products\DimensionApprovalController
  - index() — Inertia::render com produtos awaiting_approval paginados,
    filtros: category_id, dimension_source, dimension_confidence
  - approve(Product $product)
  - reject(Request $request, Product $product)  // valida motivo
  - research(Product $product) — dispara job manual
  - Policy: ProductDimensionPolicy (autorização)

- routes/web.php — grupo middleware ['auth', 'tenant']
  prefix /products/dimensions name products.dimensions.*

- Rodar:
  docker compose exec -u root php php artisan wayfinder:generate --with-form

- Testes feature pro controller (incluindo policy)
- Pint
- ESPERAR OK

═══ ETAPA 7 — Vue page ═══
- resources/js/pages/Products/PendingDimensionsApproval.vue
  - <script setup lang="ts">
  - Composition API
  - Importa rotas via @/actions/products/dimensions
  - useEcho('private', `tenant.${tenantId}.dimensions`, 'ProductDimensionResearched',
    callback que atualiza lista)
  - Cards por produto:
    * EAN, descrição, marca, categoria
    * Dimensões propostas
    * Badge da fonte (color dinâmico do enum)
    * Confidence badge (verde/amarelo/vermelho)
    * Reasoning expansível (<details>)
    * Se source=local_similarity: link pro produto referência
    * Lista de warnings com ícone
  - Botões por linha:
    * Aprovar (POST via Form do Inertia)
    * Rejeitar (Modal pedindo motivo)
    * Repesquisar
  - Filtros (URL persistente via router.get)
  - Ação em lote: "Aprovar todos com confidence=high"
  - Paginação 20/página
  - TailwindCSS conforme padrão do projeto (verificar sibling files)

- Rodar build completo:
  docker compose exec -u root php php artisan wayfinder:generate --with-form && VITE_ENABLE_WAYFINDER=false npm run build

- Pest browser test pra smoke test da página
- ESPERAR OK

═══ ETAPA 8 — Artisan command + docs ═══
- App\Console\Commands\Products\ResearchDimensionsCommand
  signature: products:research-dimensions {--limit=50} {--status=pending}
  - Dispara job pros produtos no status indicado
  - Mostra progress bar (laravel/prompts)
  - Respeita rate limit

- Atualizar README do módulo se houver convenção no projeto

NÃO PULE NENHUMA ETAPA. NÃO COMECE A CODAR ANTES DA ETAPA 1 ESTAR APROVADA.
```

---

## PROMPT 2 — Bootstrap rápido só do schema (se quiser começar pequeno)

```
Quero apenas o schema do pipeline de dimensões — sem agent, sem UI ainda.

1. database-schema na tabela products do tenant albert
2. Criar:
   - Migration tenant pros campos de dimension_status, source, etc.
   - Migration central pra dimension_research_cache
   - App\Enums\DimensionStatus
   - Atualizar Product model (casts, scopes, relação similarTo)
3. Pint
4. Pest test do enum e dos casts

NÃO criar tools, agent, job ou UI ainda. Só fundação.
Respeitar driver check pra pgvector (não rodar em SQLite).
```

---

## PROMPTS DE MANUTENÇÃO

### Pesquisar dimensões de um produto agora (manual)

```
Use database-query pra pegar 1 produto do tenant albert que tenha
dimension_status='pending'. Dispare ResearchProductDimensionsJob síncrono
pra ele (com ::dispatchSync) e mostre o resultado.
```

### Adicionar nova fonte web

```
Adicione [DOMÍNIO] à allowlist do WebSearch em ProductDimensionResearcher.
Atualize as instructions do agent (resources/ai/dimension-researcher-instructions.txt)
mencionando como fonte preferencial pra categoria [X].
Rodar Pint.
```

### Ajustar tolerância

```
Em SearchLocalProductDimensions, mude net_content tolerance de ±5% para ±[X]%
e minSimilarity de 0.75 para [Y]. Atualizar testes Pest correspondentes.
Rodar: docker compose exec php php artisan test --compact --filter=SearchLocalProductDimensions
```

### Re-rodar produtos rejeitados em lote

```
Use o command products:research-dimensions com --status=rejected --limit=100.
Antes, mostre via database-query quantos produtos serão afetados no tenant atual.
```

### Adicionar fila/supervisor extra no Horizon

```
Ativar skill configuring-horizon.
Adicione um segundo supervisor 'ai-research-priority' em config/horizon.php
pra produtos de categorias prioritárias (parametrizar via env).
Atualizar Job pra usar onQueue() condicional.
```

### Dashboard de status das pesquisas

```
Criar Vue page Products/DimensionResearchDashboard.vue mostrando:
- Total por dimension_status (gráfico de pizza)
- Tendência últimos 30 dias (linha)
- Top 10 categorias com mais produtos sem dimensão
- Performance por fonte (% de aprovação local_similarity vs cosmos vs web_search)

Usar @vueuse/core e Chart.js (verificar se já existe no package.json).
Rota: /products/dimensions/dashboard
Wayfinder + build.
```

### Webhook quando aprovação manual

```
Adicionar event ProductDimensionApproved disparado quando user aprova.
Listener que envia webhook configurável (config/services.php → dimension_webhook_url).
Job com queue 'webhooks'. Tests pro listener.
```
