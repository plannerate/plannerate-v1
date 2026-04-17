# Geração de Planogramas com IA (IAGenerate)

## Visão geral

Geração de planogramas usando **LLM** (OpenAI/Anthropic) via **Prism PHP**. A IA recebe contexto estruturado (gôndola, prateleiras, produtos) e devolve um JSON de alocações (shelf_id, product_id, facings, justificativa). O backend persiste em Segment/Layer e retorna métricas (confiança, tokens, tempo, raciocínio).

---

## Arquitetura

```
Controller: AutoPlanogramController::iaGenerate()
    ↓
IAPlanogramService::generate($gondolaId, IAGenerateConfigDTO)
    ↓
1. setupTenantConnection()
2. loadGondola()
3. selectProducts()           → array (até 50 produtos; categoria do config ou inferida da gôndola)
4. buildContext()             → PlanogramContextDTO
5. IAPromptBuilderService::buildPrompt()
6. callPrismAI()              → Prism (OpenAI/Claude)
7. IAResponseParserService::parseResponse()
8. saveAllocations()
    ↓
IAGenerateResultDTO (totalAllocated, totalUnallocated, confidence, tokensUsed, executionTime, reasoning, shelves)
```

A **seleção de produtos** no fluxo com IA é própria: CTE recursiva de categorias, `Product::whereIn('category_id', $categoryIds)->limit(50)`. Não usa o `ProductSelectionService` do AutoGenerate (sem IA) neste fluxo.

---

## Endpoint e request

**Rota:** `POST /api/tenant/plannerate/gondolas/{gondola}/ia-generate`  
**Nome da rota:** `gondolas.ia-generate`  
**Request:** `IAGeneratePlanogramRequest` → `IAGenerateConfigDTO`

### Parâmetros principais

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `category_id` | ULID | Não* | Categoria raiz; se omitido, inferido da gôndola/planograma |
| `strategy` | string | Sim | `abc`, `sales`, `margin`, `mix` (usado no contexto do prompt) |
| `subcategory_id` | ULID | Não | Filtro opcional |
| `brand_id` | ULID | Não | Filtro opcional |
| `respect_seasonality` | bool | Não | Default true |
| `apply_visual_grouping` | bool | Não | Default true |
| `intelligent_ordering` | bool | Não | Default true |
| `load_balancing` | bool | Não | Default true |
| `additional_instructions` | string (max 1000) | Não | Instruções livres para a IA |
| `model` | string | Não | `gpt-4o`, `gpt-4o-mini`, `claude-3-5-sonnet-20241022`, `claude-3-5-haiku-20241022` |
| `max_tokens` | int | Não | Default 4000 |
| `temperature` | float 0–2 | Não | Default 0.7 |

\* Se não enviar `category_id`, o serviço tenta obter do planograma da gôndola ou usa uma categoria raiz.

---

## Serviços (`app/Services/Plannerate/IAGenerate/`)

### 1. IAPlanogramService

- Multi-tenant: `setupTenantConnection()`.
- Carrega gôndola com `sections.shelves`.
- Seleção de produtos: categoria (config ou inferida), CTE recursiva para IDs de categorias, produtos limitados a 50.
- Monta `PlanogramContextDTO` (gôndola, prateleiras, produtos, regras).
- Chama `IAPromptBuilderService::buildPrompt()` e depois Prism.
- Parse da resposta com `IAResponseParserService` e gravação em Segment/Layer.
- Retorna `IAGenerateResultDTO`.

### 2. IAPromptBuilderService

- Monta o prompt enviado ao LLM: papel (merchandiser), contexto (JSON com gôndola, prateleiras, produtos), regras (strategy, flags) e formato de saída (JSON schema).
- Garante que o modelo receba instruções claras para devolver o JSON esperado.

### 3. IAResponseParserService

- Recebe o texto da resposta da IA.
- Valida e decodifica o JSON (allocation, reasoning, confidence, summary).
- Retorna estrutura tipada usada para `saveAllocations` e para preencher `IAGenerateResultDTO`.

---

## DTOs (`app/DTOs/Plannerate/IAGenerate/`)

| DTO | Uso |
|-----|-----|
| `IAGenerateConfigDTO` | Entrada: category_id, strategy, flags de IA, model, max_tokens, temperature |
| `IAGenerateResultDTO` | Saída: totalAllocated, totalUnallocated, confidence, tokensUsed, executionTime, reasoning, shelves |
| `PlanogramContextDTO` | Contexto enviado ao prompt: gôndola, prateleiras, produtos, hierarquia, regras |

---

## Dependências

- **Prism PHP** (`echolabs/prism`): abstração para OpenAI e Anthropic.
- Variáveis de ambiente: `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` (opcional).

---

## Documentação detalhada de uso

Para **parâmetros completos**, **exemplos de request/response**, **configuração do Prism**, **troubleshooting** e **dicas de uso** (modelo, temperatura, custos), use:

**[IA-PLANOGRAM-USAGE.md](./IA-PLANOGRAM-USAGE.md)**

Esse documento cobre:

- Exemplo de body e resposta da API
- Funcionamento interno (fluxo, prompt, formato de saída da IA)
- Comparativo com o algoritmo tradicional (sem IA)
- Configuração (Prism, API keys)
- Monitoramento e testes
- Dicas (escolha de modelo, temperature, instruções, custos)
- Troubleshooting

---

## Índice

- [Índice geral (Sem IA vs Com IA)](./PLANOGRAM-GERACAO-INDICE.md)
- [Geração sem IA (AutoGenerate)](./PLANOGRAM-GERACAO-SEM-IA.md)
- [Uso da API e Prism (Com IA)](./IA-PLANOGRAM-USAGE.md)
