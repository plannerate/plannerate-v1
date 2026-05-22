# Pipeline de Pesquisa de Dimensões de Produto

> Anexar este bloco ao CLAUDE.md existente do projeto (ou criar
> `.claude/dimension-research.md` referenciado a partir do principal).
> NÃO substitui o CLAUDE.md atual — complementa.

## Visão geral

Pipeline em cascata pra descobrir altura/largura/profundidade (cm) e peso (g)
da embalagem primária de cada produto. Resultado entra sempre como
`awaiting_approval` — operador valida antes de virar `approved`.

```
[1] Similaridade local (banco de produtos aprovados do tenant)
[2] API Cosmos/Bluesoft (EAN)
[3] Web search via Gemini Flash-Lite
[4] not_found
```

## Integração com convenções existentes do projeto

### Multi-tenancy (Spatie v4)
- Todas as tabelas novas são **tenant-scoped**, exceto `dimension_research_cache`
  que pode ser **central** pra evitar pesquisar o mesmo EAN 10x em tenants diferentes
- Migrations de tabela tenant rodam com:
  ```bash
  docker compose exec php php artisan tenants:artisan "migrate --database=tenant"
  ```
- Migrations da tabela cache central:
  ```bash
  docker compose exec php php artisan migrate
  ```
- Migrations com SQL raw (pgvector, índices HNSW) **devem** checar o driver:
  ```php
  if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
      return;
  }
  ```

### Schema (campos a adicionar em `products` — tenant)
- `dimension_status` — enum (pending|researching|awaiting_approval|approved|not_found|rejected)
- `similar_to_product_id` — FK self, nullOnDelete
- `dimension_source`, `dimension_source_url`, `dimension_confidence`
- `dimension_reasoning` (text), `dimension_warnings` (json)
- `dimension_researched_at`, `dimension_approved_by`, `dimension_approved_at`
- `description_embedding` — vector(768), índice HNSW (só pgsql)

### Tabela cache central (não-tenant)
- `dimension_research_cache`: ean (unique), dimensions (json), source, confidence,
  cached_at, expires_at
- TTL padrão: 90 dias

### Convenção de prateleira preservada
`shelf_order 1 = chão` — o agent NÃO toca em lógica de posicionamento.
Só preenche dimensões do produto. O cálculo `num_shelves - shelf_order` segue
em `app/Services/...` existente, sem mudança.

### Slot → product binding
A pesquisa de dimensão respeita `category_id` (FK). NÃO usar
`grouping_normalized` (campo removido). Na busca por similaridade, o filtro
de categoria usa exclusivamente `category_id`.

### Frontend (Inertia v3 + Vue 3 + Wayfinder)
- Rotas TypeScript geradas via Wayfinder — importar de `@/actions/...`
- Nunca hardcodar URL: usar `actions.products.dimensions.approve(product.id).url()`
- Após mudanças em controller: rodar
  ```bash
  docker compose exec -u root php php artisan wayfinder:generate --with-form
  ```
- Build completo só quando precisar ver no browser:
  ```bash
  docker compose exec -u root php php artisan wayfinder:generate --with-form && VITE_ENABLE_WAYFINDER=false npm run build
  ```

### Echo / Reverb (real-time na UI de aprovação)
- Evento `ProductDimensionResearched` broadcast em canal privado
  `tenant.{tenant_id}.dimensions`
- Frontend usa `useEcho` do `@laravel/echo-vue` pra atualizar lista em tempo real
- Ativar skill `echo-vue-development` quando mexer nesse arquivo

### Horizon
- Fila dedicada `ai-research` — adicionar em `config/horizon.php`:
  - supervisor próprio, `maxProcesses: 3` (respeita rate limit Gemini 15 req/min)
  - balance: `simple`
- Tag dos jobs: `ai-research`, `dimensions`, `tenant:{tenant_id}`

### Testes (Pest 4)
- Feature tests em `tests/Feature/Ai/`
- Usar tenant `albert` (`01jym02qk8n1cwdq2hd5drpgsz`) como tenant de teste
- `ProductDimensionResearcher::fake()` do AI SDK pra evitar chamada real
- SQLite em memória nos testes → pular criação do índice HNSW (já tratado no driver check)

## Estrutura de arquivos

```
app/
├── Ai/
│   ├── Agents/
│   │   └── ProductDimensionResearcher.php
│   └── Tools/
│       ├── SearchLocalProductDimensions.php
│       └── FetchCosmosBluesoft.php
├── Enums/
│   └── DimensionStatus.php
├── Events/
│   └── ProductDimensionResearched.php
├── Http/
│   └── Controllers/
│       └── Products/
│           └── DimensionApprovalController.php
├── Jobs/
│   └── ResearchProductDimensionsJob.php
├── Models/
│   └── DimensionResearchCache.php  # model central, sem tenant scope
├── Observers/
│   └── ProductDescriptionEmbeddingObserver.php
└── Services/
    └── ProductDimensionService.php

resources/js/pages/Products/
└── PendingDimensionsApproval.vue

routes/web.php  # adicionar grupo /products/dimensions
config/horizon.php  # adicionar supervisor ai-research
config/ai.php  # configurar Gemini default

tests/Feature/Ai/
├── ProductDimensionResearcherTest.php
└── DimensionApprovalControllerTest.php
```

## Regras de equivalência (Etapa 1)

TODOS os critérios precisam bater pra considerar dois produtos equivalentes:

| Critério | Tolerância |
|---|---|
| `category_id` | exato |
| `measurement_unit` (KG/G/L/ML/UN) | exato |
| `net_content` | ±5% |
| `packaging_type` (saco/PET/vidro/caixa/pote/lata/tetrapak) | exato |
| Similaridade semântica (pgvector cosine) | ≥ 0.75 |

**Por que tão rígido:** farinha 1kg em saco ≠ farinha 1kg em pote. Refrigerante
2L PET ≠ 2L vidro. Dimensão errada quebra planograma físico.

## Confidence levels

| Confidence | Quando |
|---|---|
| `high` | Cosmos retornou OK, ou ≥2 fontes web concordam ±10% |
| `medium` | Similaridade local, ou única fonte web confiável |
| `low` | Dados parciais ou sanity check falhou |

**Regra dura:** `source = local_similarity` → confidence máximo `medium`. Nunca `high`.

## Sanity checks (aplicados pelo Job antes de persistir)

- Produto sólido 1kg: altura entre 5–40cm
- Produto líquido garrafa: altura entre 15–35cm
- Volume calculado (A×L×P) compatível com volume/peso ±50%
- Nenhuma dimensão < 1cm ou > 100cm
- Peso (g) ≥ net_content declarado

Se falhar: adiciona warning, `confidence='low'`.

## Rate limits a respeitar

- Gemini Flash-Lite free: 1.000 req/dia, 15 req/min, 250k tokens/min
- Embeddings (`text-embedding-004`): 1.500 req/dia free
- Cosmos/Bluesoft: depende do plano (conferir `config/services.php`)
- Job verifica `Cache::lock('gemini-rate-limit')` antes de executar; se falhar,
  requeue com `delay(now()->addMinutes(5))`

## Pint após edits PHP

Sempre rodar antes de finalizar:
```bash
docker compose exec php vendor/bin/pint --dirty --format agent
```

## Idioma
- `instructions()` do agent, `reasoning`, `warnings`: **PT-BR**
- Labels do enum, mensagens de UI: **PT-BR**
- Código (classes, métodos, variáveis): **inglês**
