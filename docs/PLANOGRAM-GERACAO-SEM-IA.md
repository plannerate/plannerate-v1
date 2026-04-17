# Geração de Planogramas sem IA (AutoGenerate)

## Visão geral

Geração automática de planogramas por **regras fixas**: seleção e ranqueamento de produtos (vendas, ABC, margem), regras de merchandising (altura por ABC, facings) e distribuição no layout. **Não usa LLM**; resultado determinístico e sem custo de API.

---

## Arquitetura

```
Controller: AutoPlanogramController::generate()
    ↓
AutoPlanogramService::generate($gondolaId, AutoGenerateConfigDTO)
    ↓
1. ProductSelectionService::selectAndRankProducts()
2. MerchandisingRulesService (facings, shelf index, groupBySubcategory)
3. LayoutOptimizationService::distributeProducts()
4. clearGondola() + saveProductsToGondola()
    ↓
AutoGenerateResultDTO
```

---

## Endpoint e request

**Rota:** `POST /api/tenant/plannerate/gondolas/{gondola}/auto-generate`  
**Nome da rota:** `gondolas.auto-generate`  
**Request:** `AutoGeneratePlanogramRequest` → `AutoGenerateConfigDTO`

A **categoria** vem do planograma da gôndola (`$gondola->planogram_id` → `Planogram::category_id`). O body não envia `category_id`.

### Parâmetros (body)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `strategy` | string | Sim | `abc`, `sales`, `margin`, `mix` |
| `use_existing_analysis` | bool | Sim | Usar tabela `product_analyses` |
| `start_date` | date | Se não usar análise | Início do período de vendas |
| `end_date` | date | Se não usar análise | Fim do período (≥ start_date) |
| `min_facings` | int 1–10 | Sim | Mínimo de facings por produto |
| `max_facings` | int 1–20, ≥ min | Sim | Máximo de facings |
| `group_by_subcategory` | bool | Sim | Agrupar por subcategoria (lógico) |
| `include_products_without_sales` | bool | Sim | Incluir produtos sem vendas |
| `table_type` | string | Sim | `sales` ou `monthly_summaries` |

---

## Serviços (`app/Services/Plannerate/AutoGenerate/`)

### 1. AutoPlanogramService

- Configura conexão do tenant (`BelongsToConnection`).
- Carrega gôndola (`sections.shelves`) e planograma.
- Orquestra: seleção → merchandising → layout → limpar gôndola → salvar Segment/Layer.
- Retorna `AutoGenerateResultDTO` (shelves, unallocated, totais).

### 2. ProductSelectionService

- **Produtos:** CTE recursiva em `categories` para buscar categoria do planograma e todas as filhas; produtos dessas categorias.
- **Vendas:** Período do **ano anterior** (sazonalidade) usando `planogram->start_date` e `end_date`; tabela `sales` ou `monthly_sales_summaries` conforme `table_type`.
- **ABC:** Se `use_existing_analysis`, lê `product_analyses`; senão pode recalcular ou não usar.
- **Score:** Estratégias `abc`, `sales`, `margin`, `mix` com normalização (max sales/margin para escala 0–100) e combinação com ABC.
- Retorna `Collection<RankedProductDTO>`.

### 3. MerchandisingRulesService

- **determineShelfIndex:** Range por ABC (A 60–90%, B 30–60%, C 5–30%, default 0–20%) + `scoreRatio` para posição dentro do range.
- **calculateFacings:** Base por ABC (A=3, B=2, C=1), ajuste por target_stock se existir, fator de vendas (curva suavizada), limites min/max.
- **groupBySubcategory:** Agrupa em arrays por `subcategoryId`; o layout usa esses grupos, mas a proximidade física depende do `LayoutOptimizationService`.

### 4. LayoutOptimizationService

- Monta lista de `ShelfLayoutDTO` a partir da gôndola (todas as sections/shelves).
- Para cada produto: prateleira ideal via `determineShelfIndex`, tenta alocar; se não couber, tenta prateleiras próximas (±3).
- Retorna `shelves` (com produtos alocados) e `unallocated`.

---

## DTOs (`app/DTOs/Plannerate/AutoGenerate/`)

| DTO | Uso |
|-----|-----|
| `AutoGenerateConfigDTO` | Entrada: opções do request |
| `AutoGenerateResultDTO` | Saída: shelves, unallocated, totalAllocated, totalUnallocated, config, generatedAt |
| `RankedProductDTO` | Produto + score, abcClass, salesTotal, facings, etc. |
| `ShelfLayoutDTO` | Prateleira (id, dimensões) + lista de RankedProductDTO |

---

## Fluxo de dados

1. Request validado → `AutoGenerateConfigDTO::fromArray($request->validated())`.
2. Gôndola + planograma carregados; categoria = `planogram->category_id`.
3. Produtos da hierarquia + vendas (ano anterior) + ABC → scores → `RankedProductDTO` ordenados.
4. Facings calculados por produto; grupos por subcategoria (se ativo).
5. Distribuição: prateleira ideal por ABC/score, fallback ±3 prateleiras.
6. Limpeza de Segment/Layer da gôndola; criação de novos Segment + Layer por produto alocado.
7. Resposta: mensagem de sucesso com totais (alocados, não alocados, prateleiras usadas).

---

## Testes

- Comando: `php artisan plannerate:test-auto-generate` (ou com `{planogram_id}` e `{gondola_id}`).
- Cobertura sugerida: `ProductSelectionService`, `MerchandisingRulesService`, `LayoutOptimizationService`, `AutoPlanogramService` (feature test com gôndola/planograma de teste).

---

## Índice

- [Índice geral (Sem IA vs Com IA)](./PLANOGRAM-GERACAO-INDICE.md)
- [Geração com IA (IAGenerate)](./PLANOGRAM-GERACAO-COM-IA.md)
