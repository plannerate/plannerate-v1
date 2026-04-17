# Índice: Geração de Planogramas

## Visão geral

O Plannerate oferece **duas abordagens** para gerar planogramas automaticamente:

| Abordagem | Descrição | Endpoint | Status |
|-----------|-----------|----------|--------|
| **Sem IA** | Algoritmo baseado em regras (vendas, ABC, merchandising) | `POST .../gondolas/{id}/auto-generate` | ✅ Ativo |
| **Com IA** | Geração via LLM (Prism PHP: OpenAI/Anthropic) | `POST .../gondolas/{id}/ia-generate` | ✅ Ativo |
| **Por section** | Geração section a section (regras ou IA via Laravel AI SDK) | `POST .../gondolas/{id}/generate-by-sections` (opção `use_ai`) | ✅ Ativo |

Ambas usam a **mesma gôndola/planograma** e gravam o resultado em **Segment** + **Layer**. A diferença está no **motor de decisão**: regras fixas vs raciocínio da IA.

---

## Quando usar cada uma

| Critério | Sem IA | Com IA |
|----------|--------|--------|
| **Velocidade** | ~2s | ~8–15s |
| **Custo** | Zero | ~$0,10–0,50 por geração (tokens) |
| **Agrupamento visual** | Lógico (arrays), não garante proximidade física | Sim |
| **Ordenação inteligente** | Limitada | Sim (marca → linha → tamanho) |
| **Instruções customizadas** | Não | Sim (`additional_instructions`) |
| **Explicabilidade** | Logs e regras fixas | Raciocínio em texto + confiança |
| **Previsibilidade** | Alta | Variável |

---

## Documentação por abordagem

- **[Geração sem IA (AutoGenerate)](./PLANOGRAM-GERACAO-SEM-IA.md)**  
  Serviços em `app/Services/Plannerate/AutoGenerate/`, DTOs, request, fluxo e melhorias conhecidas.

- **[Geração com IA (IAGenerate)](./PLANOGRAM-GERACAO-COM-IA.md)**  
  Serviços em `app/Services/Plannerate/IAGenerate/`, Prism, prompts e uso.  
  Detalhes de API e uso: **[IA-PLANOGRAM-USAGE.md](./IA-PLANOGRAM-USAGE.md)**.

---

## Por Section + Laravel AI SDK

- **[Plano: Geração por Section com Laravel AI SDK](./PLANO-GERACAO-POR-SECAO-LARAVEL-AI.md)**  
  Geração **por Section (módulo)** com **Laravel AI SDK** ([docs](https://laravel.com/docs/12.x/ai-sdk)). Endpoint `generate-by-sections` aceita **`use_ai`** (boolean): `true` usa o Agent por section; `false` ou omitido usa regras (SectionRulesAllocator). Serviços: SectionPlanogramService, SectionAIAllocator, SectionContextBuilder, SectionRulesAllocator, SectionPersistenceService.

---

## Estrutura no código

```
app/Services/Plannerate/
├── AutoGenerate/          # Sem IA
│   ├── AutoPlanogramService.php
│   ├── ProductSelectionService.php
│   ├── MerchandisingRulesService.php
│   └── LayoutOptimizationService.php
└── IAGenerate/            # Com IA (Prism)
    ├── IAPlanogramService.php
    ├── IAPromptBuilderService.php
    └── IAResponseParserService.php

app/DTOs/Plannerate/
├── AutoGenerate/          # DTOs do fluxo sem IA
└── IAGenerate/            # DTOs do fluxo com IA

app/Http/Controllers/Tenant/Plannerate/
└── AutoPlanogramController.php   # generate() e iaGenerate()
```

Rotas (ex.: em `AutoPlanogramServiceProvider`):

- `gondolas.auto-generate` → `AutoPlanogramController@generate`
- `gondolas.ia-generate`   → `AutoPlanogramController@iaGenerate`
- `gondolas.generate-by-sections` → `AutoPlanogramController@generateBySections`

---

## Próximos passos (nova versão com IA)

- Seguir o **[Plano: Geração por Section com Laravel AI SDK](./PLANO-GERACAO-POR-SECAO-LARAVEL-AI.md)**.
- Instalar e configurar o Laravel AI SDK; implementar fluxo por section (primeiro sem IA, depois com Agent).
- Atualizar este índice quando o novo endpoint e a migração estiverem em uso.
