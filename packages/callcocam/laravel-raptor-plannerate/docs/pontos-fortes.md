# Pontos Fortes — O que o sistema faz melhor que a spec `subir-nivel.md`

> Inventário das implementações que superam, detalham ou vão além do que a spec descreve.
> Compilado da auditoria `43-RELATORIO-cobertura-template-vs-automatico.md` (2026-05-27).

---

## Arquitetura

### 🟦 Uma só fonte de verdade — engine compartilhado entre template e automático

A spec descreve template e automático como modos independentes.
Na implementação real, o modo automático **sintetiza um subtemplate e delega ao mesmo
`TemplatePlacementEngine`** ([AutoPlanogramService.php:97-124](app/Services/AutoPlanogram/AutoPlanogramService.php#L97-L124)).
Resultado: qualquer melhoria no engine vale para os dois modos sem custo extra. Obrigatórios,
bloqueados, relatório de explicação, reorder/redistribute — tudo funciona em automático de graça.

### 🟦 Promoção automática de modo após geração automática

Após a primeira geração automática, `gondola.generation_mode` muda para `'template'` e
`gondola.template_id` recebe o ID do subtemplate sintetizado
([AutoGenerationRunner.php:135-137](app/Services/AutoPlanogram/AutoGenerationRunner.php#L135-L137)).
O operador ganha acesso imediato ao editor de template para refinamentos manuais — sem passo
extra. A spec não prevê essa transição fluida entre modos.

### 🟦 Overrides por gôndola sem alterar o template global

`GondolaSlotOverride` permite configurar parâmetros de geração (min/max_facings, zone priority,
limites de participação, etc.) por categoria diretamente em cada gôndola, e propagar ao template
quando quiser. A spec não cobre esse nível de granularidade operacional.

---

## Algoritmo de scoring

### 🟦 Log-transform para vendas com distribuição power-law

A spec fala em "ABC ponderada" genericamente.
O `CompositeScorer` aplica `log(quantity + 1) / log(qMax + 1)` antes de normalizar
([CompositeScorer.php:120](app/Services/AutoPlanogram/Scoring/CompositeScorer.php#L120)).
Evita que um SKU com 27.000 unidades esmague todos os outros no score — comportamento real
de vendas em supermercados.

### 🟦 Score neutro 0.5 para produtos sem histórico de venda

A spec descreve "produtos novos" como caso especial complicado (Etapa 14).
A implementação resolve elegantemente: `scoreOrNeutral` dá score 0.5 (ponto médio) para
qualquer produto sem venda ([AutoPlanogramService.php:65](app/Services/AutoPlanogram/AutoPlanogramService.php#L65)).
Produtos novos entram no mix sem precisar de configuração manual de "status = novo".

### 🟦 Cortes de ABC configuráveis (cutoffA / cutoffB)

`AutoGenerateConfigDTO` expõe `abcCutoffA=0.80` e `abcCutoffB=0.90` como parâmetros
configuráveis ([AutoGenerateConfigDTO.php:75,85](app/Services/AutoPlanogram/DTO/AutoGenerateConfigDTO.php#L75-L85)).
A spec não menciona cutoffs — a implementação permite tuning fino por tenant.

---

## Síntese de template (modo automático)

### 🟦 Demanda por largura física com overflow-routing

Em vez de dividir prateleiras proporcionalmente por volume (abordagem ingênua), a síntese
calcula `ceil(totalWidth / shelfWidth)` por subcategoria
([SlotPlanBuilder.php:474-479](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L474-L479)).
Subcategorias com último slot parcialmente cheio recebem prateleiras extras via overflow-routing
([SlotPlanBuilder.php:561-604](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L561-L604)).
Resultado: layout real reflete a largura física dos produtos, não só o volume.

### 🟦 Micro-categorias compartilham prateleira

Subcategorias com mix estreito (totalWidth < 35% da largura da prateleira) não desperdiçam
uma prateleira inteira — são encaixadas na prateleira da categoria precedente
([SlotPlanBuilder.py:317-356](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L317-L356)).
Adensamento que a spec não descreve.

### 🟦 Inferência automática de papel da categoria

`CategoryRoleInferrer` deduz destino/rotina/impulso/complementar a partir de giro e margem
normalizados ([CategoryRoleInferrer.php:37-79](app/Services/AutoPlanogram/Synthesis/CategoryRoleInferrer.php#L37-L79)).
Respeita `categories.role` manual quando definido — o operador pode corrigir a inferência
sem mudar código. A spec só prevê papel configurado; aqui é automático e auditável.

### 🟦 Poda de slots vazios pós-geração

Após o placement, slots do subtemplate sintetizado sem nenhum produto são removidos
([AutoPlanogramService.php:147-162](app/Services/AutoPlanogram/AutoPlanogramService.php#L147-L162)).
O template persistido reflete exatamente o que foi usado — sem slots fantasma.

### 🟦 Idempotência por `source_gondola_id`

Regerações da mesma gôndola reutilizam o mesmo template (por `source_gondola_id + origin='auto'`)
e recriam os slots ([AutoTemplateSynthesizer.php:83-87](app/Services/AutoPlanogram/Synthesis/AutoTemplateSynthesizer.php#L83-L87)).
Nenhum template órfão acumula no banco.

---

## Placement engine

### 🟦 min_facings por classe ABC (não fixo)

Em vez de um `min_facings` uniforme, a síntese deriva o piso por classe dominante:
A=3, B=2, C=1 frentes mínimas ([SlotPlanBuilder.py:644-649](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L644-L649)).
Produtos de curva A garantem visibilidade sem precisar de configuração explícita.

### 🟦 Deduplicação global de produtos entre slots

`globalPlacedProductIds` garante que um produto não apareça em dois slots da mesma gôndola
([TemplatePlacementEngine.php:38,252](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L38)).
A spec não aborda deduplicação — em supermercados é crítico evitar faces duplicadas.

### 🟦 Falso-positivo de rejeição eliminado

`RejectedProductsWriter` cruza rejeitados com produtos colocados antes de persistir
([RejectedProductsWriter.php](app/Services/AutoPlanogram/Placement/RejectedProductsWriter.php)).
Um produto rejeitado de um slot cheio mas alocado em outro não aparece como "rejeitado" no
relatório — o que a spec não endereça.

### 🟦 Obrigatórios e bloqueados funcionam nos dois modos sem config extra

`loadProductRules` e `withProductRules` são chamados em `generateWithTemplate` que serve tanto
template quanto automático ([AutoPlanogramService.php:236-248](app/Services/AutoPlanogram/AutoPlanogramService.php#L236-L248)).
Regras por produto, marca e subcategoria respeitadas em todos os caminhos.

---

## UX / frontend

### 🟦 Snapshot manager (undo/redo) + painel de rejeitados com swap

O editor possui histórico de ações desfazíveis e painel que mostra produtos rejeitados com
botão de troca direta por outro SKU. A spec menciona "ajustes dinâmicos" vagamente;
a implementação é consideravelmente mais rica.

### 🟦 Relatório de explicação por produto alocado e rejeitado

`explanationReport` expõe, por produto: classe ABC, zona, papel, frentes, motivo de rejeição
com label legível ([TemplatePlacementEngine.php:1302+](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L1302)).
Exibido no `PlanogramCapacityBanner.vue` com breakdown ABC e alertas de estoque alvo.
A spec pede justificativas; a implementação entrega um relatório estruturado auditável.

### 🟦 AlterationClassifier espelhado no frontend

`AlterationClassifier.php` é espelhado em TypeScript (`alteration-classifier.ts`).
O frontend classifica mudanças e avisa o operador antes de ele salvar: "Slot atualizado —
planogramas precisam de: Regeneração" etc. A spec só descreve os três tipos de alteração;
o feedback preventivo no UI é adição de valor.

---

## Resumo quantitativo

| Categoria | Itens melhores que a spec |
|---|---|
| Arquitetura | 3 |
| Scoring | 3 |
| Síntese (automático) | 5 |
| Placement engine | 5 |
| UX / frontend | 3 |
| **Total** | **19** |
