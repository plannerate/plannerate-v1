# Pipeline de Pesquisa de Dimensões — O que foi implementado e como usar

> Estado da implementação no projeto Plannerate v1.
> Atualizado em: 2026-05-22

---

## Visão Geral

O pipeline pesquisa automaticamente as dimensões físicas (largura, altura, profundidade, peso) de produtos do supermercado usando uma cascata de fontes: banco local → Cosmos/Bluesoft API → busca web. A IA decide qual fonte usar, aplica sanity checks, e só persiste se os dados passarem em validações básicas.

---

## Arquitetura em Cascata

```
Trigger (sync manual ou command)
        │
        ▼
┌─────────────────────────────────┐
│ EanReference lookup (síncrono)  │ ← Mais rápido, sem AI
│  produto já tem EAN no banco?   │
└─────────────────────────────────┘
        │ não encontrou
        ▼
┌─────────────────────────────────────────────────────────────────┐
│ ResearchProductDimensionsJob (fila ai-research, async)          │
│                                                                 │
│  ProductDimensionResearcher (Gemini 2.5 Flash, max 15 steps)   │
│                                                                 │
│  Etapa 1 — SearchLocalProductDimensions                        │
│    └─ SQL: category_id exato + unit + net_content ±5%          │
│    └─ pgvector: whereVectorSimilarTo(description, 0.75)        │
│    └─ Retorna até 5 candidatos com similarity_score            │
│                                                                 │
│  Etapa 2 — FetchCosmosBluesoft                                 │
│    └─ Cache central 90 dias (DimensionResearchCache)           │
│    └─ HTTP GET api.cosmos.bluesoft.com.br/gtins/{ean}          │
│    └─ Retry automático em 429, timeout 15s                     │
│                                                                 │
│  Etapa 3 — WebSearch (allowlist de sites de supermercado)      │
│    └─ Máx. 5 buscas                                            │
│    └─ Domínios: paodeacucar.com, carrefour.com.br,            │
│       atacadao.com.br, cosmos.bluesoft.com.br,                 │
│       mercadolivre.com.br                                      │
│                                                                 │
│  Sanity checks (antes de persistir):                           │
│    • Dimensão entre 1cm e 100cm                                │
│    • Peso ≥ conteúdo líquido declarado                         │
│    • local_similarity não pode ter confidence=high             │
└─────────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────┐
│ dimension_status = awaiting_    │
│ approval → notificação via      │
│ ProductDimensionResearched      │
│ (Broadcast PrivateChannel)      │
└─────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────┐
│ DimensionApprovalController     │
│ (revisão manual ou approve all) │
└─────────────────────────────────┘
```

---

## Arquivos Criados / Modificados

### PHP (Backend)

| Arquivo | Papel |
|---|---|
| `app/Enums/DimensionStatus.php` | Enum com `label()` e `color()` PT-BR: pending, researching, awaiting_approval, approved, not_found, rejected |
| `app/Models/Product.php` | Casts, scopes (`needingResearch`, `withApprovedDimensions`, `awaitingApproval`), relação `similarTo()` |
| `app/Models/DimensionResearchCache.php` | Cache central (não-tenant) de respostas da Cosmos API |
| `app/Ai/Agents/ProductDimensionResearcher.php` | Agent Gemini 2.5 Flash com output estruturado |
| `app/Ai/Tools/SearchLocalProductDimensions.php` | Busca SQL + pgvector no banco do tenant |
| `app/Ai/Tools/FetchCosmosBluesoft.php` | HTTP para Cosmos API com cache central |
| `app/Jobs/ResearchProductDimensionsJob.php` | Job na fila `ai-research`, 3 tentativas, backoff [60, 300, 900]s |
| `app/Services/ProductDimensionService.php` | `research()`, `approve()`, `reject()`, `dispatchPendingBatch()` |
| `app/Events/ProductDimensionResearched.php` | Broadcast `PrivateChannel("tenant.{id}.dimensions")` |
| `app/Http/Controllers/Tenant/ProductDimensionController.php` | CRUD + sync por EAN + logs estruturados |
| `app/Http/Controllers/Tenant/Products/DimensionApprovalController.php` | Aprovação, rejeição, pesquisa manual, approve-all em lote |
| `app/Console/Commands/Products/ResearchDimensionsCommand.php` | Command `products:research-dimensions` |
| `resources/ai/dimension-researcher-instructions.txt` | Instruções do agent (editável sem deploy) |
| `resources/ai/user-prompt-template.txt` | Template do prompt com variáveis `{{EAN}}`, `{{DESCRIPTION}}`, etc. |

### Vue (Frontend)

| Arquivo | Papel |
|---|---|
| `resources/js/pages/tenant/dimensions/Index.vue` | Lista de dimensões com coluna de Pesquisa AI enriquecida |
| `resources/js/pages/Products/PendingDimensionsApproval.vue` | Fila de aprovação manual com badges de fonte e confiança |

### Configuração

| Arquivo | O que foi adicionado |
|---|---|
| `config/horizon.php` | Supervisor `ai-research` (maxProcesses: 3, balance: simple) |
| `config/services.php` | `cosmos.token` e `cosmos.url` |
| `lang/pt_BR/app.php` | Chave `app.tenant.dimensions.ai_research_label` e outras |

---

## Campos no Modelo Product

Todos na migration tenant `add_dimension_research_fields_to_products_table`:

```php
// Status do pipeline AI (enum DimensionStatus)
$table->string('dimension_status')->default('pending');

// Resultado da pesquisa
$table->float('width')->nullable();   // largura em cm
$table->float('height')->nullable();  // altura em cm
$table->float('depth')->nullable();   // profundidade em cm
$table->float('weight')->nullable();  // peso em gramas

// Metadados da pesquisa
$table->string('dimension_source')->nullable();      // local_similarity | cosmos | web_search | not_found
$table->string('dimension_source_url')->nullable();  // URL da fonte
$table->string('dimension_confidence')->nullable();  // high | medium | low
$table->text('dimension_reasoning')->nullable();     // explicação em PT-BR
$table->json('dimension_warnings')->nullable();      // array de avisos
$table->timestamp('dimension_researched_at')->nullable();

// Referência cruzada (quando source=local_similarity)
$table->string('similar_to_product_id')->nullable();

// Vetor semântico para busca por similaridade (pgvector)
// Criado somente em PostgreSQL (driver check na migration)
// vector(768) com índice HNSW

// Aprovação manual
$table->string('dimension_approved_by')->nullable();
$table->timestamp('dimension_approved_at')->nullable();
```

---

## DimensionStatus — Ciclo de Vida

```
         ┌──────────────────────────────────────────┐
         │                                          │
    pending ──► researching ──► awaiting_approval ──► approved
         │                            │
         │                     (se rejeitado)
         │                            ▼
         │                        rejected ──► pending (re-pesquisa)
         │
         └──► not_found (EAN não existe em nenhuma fonte)
```

| Status | label() | color() | Significa |
|---|---|---|---|
| `pending` | Aguardando pesquisa | gray | Job ainda não executou |
| `researching` | Pesquisando… | blue | Job em execução |
| `awaiting_approval` | Aguardando aprovação | yellow | IA encontrou, aguarda humano |
| `approved` | Aprovado | green | Dimensões válidas e publicadas |
| `not_found` | Não encontrado | orange | IA não encontrou em nenhuma fonte |
| `rejected` | Rejeitado | red | Humano rejeitou, motivo salvo em `dimension_reasoning` |

---

## Output Estruturado do Agent

O `ProductDimensionResearcher` retorna JSON com este schema:

```json
{
  "found": true,
  "width": 8.5,
  "height": 22.0,
  "depth": 6.0,
  "weight": 450.0,
  "unit": "cm",
  "source": "cosmos",
  "source_url": "https://cosmos.bluesoft.com.br/...",
  "confidence": "high",
  "reasoning": "Dimensões obtidas diretamente da API Cosmos para o EAN 7891234567890. Produto Leite Integral Italac 1L. Dados consistentes com embalagem Tetrapak 1L.",
  "warnings": [],
  "similar_product_id": null
}
```

Valores possíveis para `source`: `local_similarity`, `cosmos`, `web_search`, `not_found`
Valores possíveis para `confidence`: `high`, `medium`, `low`

---

## Como Usar

### 1. Sync por EAN (UI)

Na lista de dimensões (`/dimensions`), cada linha tem um botão **Atualizar por EAN** (ícone de refresh). O fluxo:

1. Verifica se o produto já tem dimensões configuradas → publica se necessário
2. Normaliza o EAN e busca em `ean_references` (tabela local)
3. Se não encontra: dispara `ResearchProductDimensionsJob` (pesquisa AI assíncrona)
4. Toast indica o resultado imediatamente; pesquisa AI roda em background

### 2. Sync da Página Inteira (UI)

Botão **"Atualizar página por EAN"** no header — processa todos os produtos visíveis na página atual com a mesma lógica acima em lote.

### 3. Artisan Command (CLI / Cron)

```bash
# Pesquisar produtos com status 'pending' (padrão, até 50)
docker compose exec php php artisan products:research-dimensions

# Pesquisar até 100 produtos rejeitados
docker compose exec php php artisan products:research-dimensions --limit=100 --status=rejected

# Re-pesquisar não encontrados em lote
docker compose exec php php artisan products:research-dimensions --status=not_found --limit=200
```

**Opções disponíveis:**
- `--limit=N` — máximo de produtos a enfileirar (padrão: 50)
- `--status=X` — filtro de status: `pending`, `rejected`, `not_found` (padrão: `pending`)

O comando **não executa a pesquisa diretamente** — ele enfileira `ResearchProductDimensionsJob` para cada produto. O Horizon processa a fila `ai-research`.

### 4. Aprovar Dimensões (UI)

Acesse `/products/dimensions/pending-approval` (rota do `DimensionApprovalController`). Lá você pode:
- Aprovar individualmente (POST `approve`)
- Rejeitar com motivo (POST `reject`)
- Disparar nova pesquisa manual (POST `research`)
- Aprovar em lote todos com `confidence=high` (POST `approve-all`)

### 5. Via Service (PHP)

```php
use App\Services\ProductDimensionService;

$service = app(ProductDimensionService::class);

// Disparar pesquisa para um produto
$service->research($product);  // seta status=pending e dispara o Job

// Aprovar dimensões
$service->approve($product, $user);

// Rejeitar com motivo
$service->reject($product, $user, 'Dimensões inconsistentes com embalagem declarada');

// Disparar lote de pendentes (retorna quantidade enfileirada)
$count = $service->dispatchPendingBatch(limit: 100);
```

---

## Logs Gerados

O `ProductDimensionController` loga em `laravel.log` (canal padrão):

| Nível | Evento |
|---|---|
| `info` | Sync individual iniciado / concluído |
| `info` | EAN sem referência — encaminhando para AI |
| `info` | Sync de página iniciado / concluído (com totais) |
| `info` | EAN não encontrado em EanReference |
| `info` | Referência sem dimensões aplicáveis |
| `info` | Dimensões aplicadas da referência EAN (campos e valores) |
| `debug` | Verificação de configuração existente (width/height/depth) |
| `debug` | Produto já possui dimensões (se foi publicado agora) |
| `debug` | Referência EAN encontrada (com valores da referência) |
| `debug` | Produto processado no sync de página (resultado por produto) |
| `warning` | Produto não encontrado no sync de página |

O `ResearchProductDimensionsJob` já logava em `error` no método `failed()`.

O `FetchCosmosBluesoft` loga:
- `warning` quando a Cosmos API retorna status inesperado
- `error` em falha de conexão

Para acompanhar em tempo real:

```bash
docker compose exec php php artisan pail --filter="Dimensões"
```

---

## Coluna "Pesquisa AI" na Lista de Dimensões

A tela `/dimensions` agora exibe uma coluna **Pesquisa AI** com:

- **Badge de status** do pipeline (cor correspondente ao enum `DimensionStatus`)
- **Ícone de loading** quando `ai_status = 'researching'`
- **Ícone de aviso** (⚠) quando há `ai_warnings`, com tooltip mostrando todos os avisos
- **Fonte**: `Banco local`, `Cosmos` ou `Web`
- **Confiança**: badge colorido `high` (verde) / `medium` (amarelo) / `low` (vermelho)
- **Link externo** para a URL da fonte (quando disponível)
- **Raciocínio da IA**: ícone ⓘ com tooltip + texto truncado

---

## Horizon — Supervisor AI

Em `config/horizon.php`:

```php
'supervisors' => [
    // ...
    'ai-research' => [
        'connection' => 'redis',
        'queue' => ['ai-research'],
        'balance' => 'simple',
        'minProcesses' => 1,
        'maxProcesses' => 3,
        'tries' => 3,
        'timeout' => 120,
    ],
],
```

Acompanhe a fila em `http://localhost/horizon` (ou `/horizon` do tenant).

---

## Rate Limiting

O job usa `Cache::lock('gemini-rate-limit', 5)` para garantir no máximo 1 chamada ao Gemini a cada 5 segundos. Se o lock não estiver disponível, o job libera a si mesmo com `$this->release(60)`.

O Gemini free tem limite de ~1.000 req/dia. Com a cascata (SQL primeiro, depois Cosmos com cache), a maioria dos produtos resolve sem chamar o LLM.

---

## Variáveis de Ambiente

```env
# Cosmos/Bluesoft API
COSMOS_TOKEN=seu_token_aqui
COSMOS_URL=https://api.cosmos.bluesoft.com.br  # opcional, default acima

# Gemini (via Laravel AI SDK)
GEMINI_API_KEY=sua_chave_aqui
```

---

## Testes

```bash
# Rodar todos os testes de dimensões
docker compose exec php php artisan test --compact --filter=Dimension

# Rodar teste específico
docker compose exec php php artisan test --compact --filter=ResearchDimensionsCommand
docker compose exec php php artisan test --compact --filter=DimensionApprovalController
docker compose exec php php artisan test --compact --filter=SearchLocalProductDimensions
docker compose exec php php artisan test --compact --filter=FetchCosmosBluesoft
```

Os testes usam o tenant `albert` (`01jym02qk8n1cwdq2hd5drpgsz`) e mockam o agent via `ProductDimensionResearcher::fake()`.
