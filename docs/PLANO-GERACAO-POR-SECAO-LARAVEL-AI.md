# Plano: Geração de Planogramas por Section com Laravel AI SDK

## Objetivo

Trocar a geração "gôndola inteira" por **geração por Section (módulo)** e usar o **[Laravel AI SDK](https://laravel.com/docs/12.x/ai-sdk)** em vez do Prism PHP. Cada section tem largura fixa e um conjunto de prateleiras; a alocação é feita section a section, com análise de produtos/vendas e respeito às dimensões (largura da section, altura da prateleira, dimensões do produto).

---

## Por que por Section?

- **Gôndola inteira:** muitas prateleiras e produtos de uma vez → prompt enorme, custo alto, resposta instável e difícil de validar.
- **Por Section:** contexto menor (uma section = uma “fatia” da gôndola) → prompt menor, restrições claras (largura da section, alturas das prateleiras), resultado mais previsível e validável (soma de larguras ≤ largura da section, altura produto ≤ altura da prateleira).

Cada section tem:
- **Largura disponível** (ex.: `section->width` ou `section_width` calculado)
- **Prateleiras** (shelves) com altura (`shelf_height`) e mesma largura da section
- Alocação = quais produtos vão em qual prateleira, com quantos facings, sem estourar largura nem altura

---

## Laravel AI SDK (visão geral)

O [Laravel AI SDK](https://laravel.com/docs/12.x/ai-sdk) oferece:

- **Agents:** classes que definem instruções, contexto e **structured output** (schema JSON).
- **Structured output:** o agente devolve um JSON tipado (ex.: alocação por prateleira) via `HasStructuredOutput` + `schema(JsonSchema $schema)`.
- **Prompting:** `$agent->prompt('...')` com override de provider/model/timeout.
- **Provedores:** OpenAI, Anthropic, Gemini, Groq, xAI, etc.; configuração em `config/ai.php` e `.env`.
- **Testes:** `Agent::fake()` e asserções sobre prompts/respostas.

Não é necessário manter Prism para essa funcionalidade; o AI SDK cobre texto + structured output com interface Laravel.

---

## Arquitetura proposta

### Fluxo geral

1. **Entrada:** `gondola_id`, configuração (categoria, estratégia, etc.).
2. **Carregar gôndola:** `Gondola::with(['sections.shelves'])->find($gondolaId)`.
3. **Para cada Section (em ordem):**
   - Obter **largura disponível** da section (ex.: `$section->width` ou atributo calculado).
   - Obter **prateleiras** da section (id, altura, mesma largura da section).
   - Obter **candidatos de produtos** (categoria do planograma, com vendas/ABC) — pode ser um subconjunto ou pool compartilhado com limite por section.
   - **(Opcional) Análise de produtos e vendas** por section (ex.: ranqueamento já existente em `ProductSelectionService` ou versão “por section”).
   - Chamar **motor de alocação** para esta section:
     - **Com IA:** Agent (Laravel AI SDK) com structured output: dado seção (largura, prateleiras com altura) e produtos (id, nome, width, height, score/ABC), retornar `[{ shelf_id, product_id, facings }, ...]` respeitando:
       - Por prateleira: `sum(product.width * facings) <= section_width`
       - Por prateleira: `product.height <= shelf_height`
     - **Sem IA:** mesmo contrato de entrada/saída, implementação com regras (ex.: extensão do `LayoutOptimizationService` por section).
   - **Validar** alocação (somas de largura, alturas) e **persistir:** limpar segments/layers das shelves da section e criar Segment + Layer conforme alocação.
4. **Resposta:** totais (produtos alocados, sections processadas, etc.) e opcionalmente métricas por section.

Assim, a gôndola nunca é “calculada” de uma vez; cada section é um problema de alocação isolado, com restrições claras.

---

## Uso do Laravel AI SDK nesta ideia

### Opção A: Agent dedicado por section (recomendado)

- **Classe:** `App\Ai\Agents\PlanogramSectionAllocator` (ou nome similar).
- **Responsabilidade:** receber contexto de **uma** section (largura, prateleiras com id e altura) e lista de produtos (id, width, height, score/ABC, nome) e devolver alocação estruturada.
- **Interfaces:** `Agent` + `HasStructuredOutput` (sem conversa, sem tools obrigatórios).
- **Schema de saída (exemplo):**
  - `allocation`: array de `{ shelf_id: string, product_id: string, facings: integer }`
  - `reasoning`: string (opcional)
  - `unallocated`: array de `product_id` (opcional)
- **Prompt:** instruções fixas (você é um especialista em merchandising; restrições de largura/altura; formato de saída) + **contexto dinâmico** no texto do prompt (JSON da section + produtos), por exemplo construído em um service.

Exemplo de uso (implementado):

```php
$context = $this->sectionContextBuilder->build($section, $rankedProducts);
$response = (new PlanogramSectionAllocator)->prompt($context, provider: 'openai', model: 'gpt-4o-mini');
$result = SectionAllocationResultDTO::fromAgentResponse($response->toArray());
// $result->allocation (SectionAllocationItemDTO[]), $result->reasoning, $result->unallocated
// Validar e persistir Segment/Layer para esta section
```

**Arquivos criados (esboço):**
- `app/Ai/Agents/PlanogramSectionAllocator.php` — Agent com `instructions()` e `schema()` (reasoning, allocation, unallocated).
- `app/DTOs/Plannerate/SectionGenerate/SectionAllocationItemDTO.php` — Um item: shelf_id, product_id, facings.
- `app/DTOs/Plannerate/SectionGenerate/SectionAllocationResultDTO.php` — Resultado completo + `fromAgentResponse()` para mapear a resposta do Agent.
- `app/Services/Plannerate/SectionGenerate/SectionContextBuilder.php` — Monta o contexto (section + prateleiras + produtos) em JSON para o prompt. Recebe `Section` (com shelves) e `Collection<RankedProductDTO>`; devolve string com instrução + JSON (section.id, section.width_cm, shelves[{id, height_cm}], products[{id, name, width_cm, height_cm, score, abc_class}]).

**Nota:** O schema usa `$schema->array()` para `allocation` e `unallocated`. Se a versão do Laravel AI SDK não expuser `array()` no contrato JsonSchema, ajustar o schema (ex.: apenas `reasoning` como string e tratar allocation no prompt como JSON a ser parseado) ou atualizar o pacote.

### Opção B: Anonymous agent

- Usar `agent(instructions: '...', schema: fn ($schema) => [...])->prompt($context)` para não criar classe de Agent.
- Útil para MVP; depois pode ser extraído para `PlanogramSectionAllocator` para testes e reuso.

### Dados que o Agent precisa receber (no prompt/contexto)

- **Section:** id (opcional), largura disponível (cm).
- **Prateleiras:** para cada uma: id, altura (cm). (Largura = da section.)
- **Produtos:** id, nome, width, height (cm), score ou ABC, facings sugeridos (opcional).
- **Regras:** e.g. “produtos A preferem prateleiras 60–90% altura; soma de (width × facings) por prateleira ≤ largura da section; product.height ≤ shelf.height”.

O backend continua responsável por: garantir que a resposta obedeça às restrições (validação) e por criar Segment/Layer com width calculado (ex.: product.width * facings).

---

## Estrutura de código sugerida

- **`app/Ai/Agents/PlanogramSectionAllocator.php`**  
  Agent com `instructions()` e `schema()` para alocação de uma section.

- **`app/Services/Plannerate/SectionGenerate/`** (ou nome semelhante)  
  - **SectionPlanogramService:** orquestra “por section”: carrega gôndola, itera sections, chama análise de produtos (ou usa pool já ranqueado), chama allocator (IA ou regras), valida e persiste.
  - **SectionContextBuilder:** (implementado) monta o texto/JSON do contexto (section + prateleiras + produtos) para o Agent. `build(Section $section, Collection $rankedProducts): string`.
- **SectionRulesAllocator:** (implementado) alocação por regras para uma section; retorna `SectionAllocationResultDTO` (mesmo formato do Agent).
- **SectionPersistenceService:** (implementado) `clearSection(Section)` e `saveAllocation(Section, SectionAllocationResultDTO)`.
- **SectionAIAllocator:** (implementado) alocação por IA para uma section: usa SectionContextBuilder + PlanogramSectionAllocator; em falha (API/timeout) retorna alocação vazia para a section (produtos seguem para as próximas).
- **SectionPlanogramService:** (implementado) orquestra `generateBySections($gondolaId, $config, $useAi = false)`; se `$useAi` true usa SectionAIAllocator, senão SectionRulesAllocator; por cada section chama allocator, clearSection e saveAllocation; produtos já alocados não são repassados à section seguinte.
  - **SectionAllocationValidator:** valida que a alocação retornada respeita largura e altura (e opcionalmente regras de negócio).
  - **SectionPersistenceService:** limpa segments/layers das shelves da section e cria Segment + Layer a partir da alocação aprovada.

- **DTOs:**  
  - Entrada: section_id, lista de prateleiras, lista de produtos com dimensões e score.  
  - Saída do Agent: allocation (shelf_id, product_id, facings), reasoning, unallocated.  
  - Resultado final por section: allocated_count, unallocated_count, warnings.

- **Controller:**  
  Endpoint **POST .../gondolas/{gondola}/generate-by-sections** (rota `gondolas.generate-by-sections`) com `AutoGeneratePlanogramRequest`; parâmetro opcional **`use_ai`** (boolean): se `true`, usa Laravel AI SDK por section (SectionAIAllocator); se `false` ou omitido, usa regras (SectionRulesAllocator). Chama `SectionPlanogramService::generateBySections($gondolaId, $config, $useAi)`. Coexiste com `auto-generate` e `ia-generate`.

---

## Análise de produtos e vendas “por section”

- **Reuso:** usar o mesmo `ProductSelectionService` (AutoGenerate) para obter produtos ranqueados da categoria do planograma (vendas, ABC, estratégia). Não é obrigatório “recalcular” por section.
- **Escopo por section:** para não enviar centenas de produtos ao Agent, pode-se:
  - **Limitar por section:** ex. top N produtos por score para esta section, ou
  - **Repartir o pool:** dividir o ranque em blocos (section 1 = produtos 1–30, section 2 = 31–60, etc.) ou
  - **Compartilhar o mesmo pool:** enviar o mesmo conjunto (ex. top 50) para cada section e deixar o Agent escolher o que cabe; produtos já alocados em sections anteriores podem ser marcados como “já usados” e não repetidos (ou repetidos se fizer sentido).
- A decisão exata pode ser um segundo passo; o importante é que **cada chamada do Agent receba um conjunto limitado de produtos** e **uma única section** com largura e prateleiras bem definidos.

---

## Restrições a garantir (backend)

Independentemente da IA, o backend deve:

1. **Largura:** para cada prateleira da section, `sum(product.width * facings) <= section_width`.
2. **Altura:** para cada alocação, `product.height <= shelf.height` (ou valor definido para aquela shelf).
3. **IDs:** shelf_id e product_id devem pertencer à section e ao planograma/cliente.
4. **Facings:** mínimo/máximo por produto (config ou padrão).

Se a resposta do Agent vier fora dessas regras, o backend pode rejeitar a alocação daquela section, registrar log e opcionalmente retentar ou usar fallback (ex.: algoritmo por regras só para essa section).

---

## Ambiente (Laravel Sail)

O projeto usa **Laravel Sail** para desenvolvimento e testes. Comandos Artisan e testes devem rodar dentro do ambiente Sail para ter acesso ao banco (pgsql), Redis, etc.:

```bash
./vendor/bin/sail artisan test --compact --filter=Planogram
./vendor/bin/sail artisan wayfinder:generate
```

Ver também [COMANDOS_SETUP.md](./COMANDOS_SETUP.md) e [sync-commands.md](./sync-commands.md) para mais exemplos com Sail.

---

## Migração Prism → Laravel AI SDK

- **Instalar:** `composer require laravel/ai` (ou `./vendor/bin/sail composer require laravel/ai`) e publicar config/migrations do AI SDK.
- **Configurar:** `config/ai.php` e variáveis em `.env` (ex.: `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`). Remover dependência de chamadas diretas ao Prism no fluxo de planograma.
- **Substituir:** onde hoje se usa Prism para “gerar alocação com IA”, passar a usar o Agent (Laravel AI SDK) com structured output; manter o mesmo contrato de “alocação por section” (lista shelf_id, product_id, facings) para facilitar validação e persistência.
- **Manter:** `IAGenerate` atual pode permanecer disponível até a nova rota por section estar estável; depois pode ser descontinuada ou redirecionada para o novo fluxo.

---

## Próximos passos sugeridos

1. ~~**Instalar e configurar** o Laravel AI SDK no projeto.~~ ✅ Feito (`composer require laravel/ai`).
2. **Implementar SectionPlanogramService (sem IA):** fluxo “por section”, usando apenas regras (reaproveitando ProductSelectionService + lógica tipo LayoutOptimization mas por section) e persistência; validar largura/altura.
3. ~~**Criar PlanogramSectionAllocator:** Agent com `instructions` e `schema` para uma section; **SectionContextBuilder**.~~ ✅ Feito.
4. **Integrar Agent no SectionPlanogramService:** opção “com IA” que chama o Agent por section, valida a resposta e persiste.
5. **Novo endpoint e request** para “gerar por sections” (e, se desejado, front para escolher “por section” vs “gôndola inteira”).
6. **Testes:** feature tests para uma section (com e sem IA); fake do Agent para não depender de API externa.
7. **Documentar** no índice de docs (ex.: `PLANOGRAM-GERACAO-INDICE.md`) a nova opção “por section” e a migração para o Laravel AI SDK.

---

## Referências

- [Laravel AI SDK – Documentação](https://laravel.com/docs/12.x/ai-sdk)
- [Laravel AI SDK – Agents, Structured Output](https://laravel.com/docs/12.x/ai-sdk#structured-output)
- [Laravel AI SDK – Anonymous Agents](https://laravel.com/docs/12.x/ai-sdk#anonymous-agents)
- Índice de geração de planogramas: [PLANOGRAM-GERACAO-INDICE.md](./PLANOGRAM-GERACAO-INDICE.md)
- Geração sem IA (atual): [PLANOGRAM-GERACAO-SEM-IA.md](./PLANOGRAM-GERACAO-SEM-IA.md)
- Geração com IA (atual, Prism): [PLANOGRAM-GERACAO-COM-IA.md](./PLANOGRAM-GERACAO-COM-IA.md)
