# Análise — Lentidão de clique no editor de planograma

**Período:** 2026-06-23 / 2026-06-24
**Sintoma inicial:** clique em produto/prateleira "demorava pra liberar"; INP medido em produção = **5.240 ms** (péssimo). Alvo da interação sempre a imagem do produto (`img.z-20.object-cover`).
**Resultado:** INP mediana **16–40 ms**; picos por módulo eliminados.

Este documento lista **apenas** as alterações relacionadas a essa investigação e avalia o risco de regressão de cada uma.

---

## Causa raiz (resumo)

Eram **três** causas somadas, descobertas em camadas:

1. **Re-render de árvore inteira** ao destacar categoria: `selectedTemplateCategoryId` era passado como prop reativa por toda a árvore (Canvas → Sections → Section → Shelves → Shelf → Segment); qualquer mudança re-renderizava todos os segments.
2. **Clique engolido pelo drag:** seleção no `@click`, mas `draggable="true"` convertia micro-movimentos em `dragstart` e descartava o `click`.
3. **Imagens gigantes (causa dominante dos picos):** o pipeline mantinha a resolução ORIGINAL (até **5750×3612 px, 2,5 MB**) quando o produto não tinha `width`/`height`. Decodificá-las no primeiro clique de cada módulo travava a thread ~1 s.

---

## Alterações relacionadas

| # | Commit | Arquivos | O que faz |
|---|--------|----------|-----------|
| 1 | `4dd5b7d` | Canvas, Sections, Section, Shelves, Shelf, Segment `.vue` | Remove a prop `highlightGroupingNormalized` drilada; Shelf/Segment leem `selectedTemplateCategoryId` direto via computed escopado |
| 2 | `9d4adca` | Segment.vue | Seleção movida de `@click` → `@pointerdown`; novo `selectThisSegment()` com guard anti re-seleção; `@click` só faz `stopPropagation` |
| 3 | `9d4adca` | SegmentDetails.vue | Substitui **duas** varreduras O(N) da árvore por **uma** (`editor.findSegmentById`) por seleção |
| 4 | `f0e7f67` | Segment.vue, Layer.vue | Remove `shadow-xl`/`shadow-lg`/`transition-shadow` (repaint pesado); adiciona `decoding="async"` + `loading="lazy"` na imagem do canvas; data-attrs de diagnóstico (`data-module`/`data-shelf`/`data-ean`) |
| 5 | `f0e7f67` | Shelf.vue, Segment.vue, Layer.vue | Propaga `moduleNumber`/`shelfNumber` (diagnóstico) Shelf → Segment → Layer |
| 6 | `b8cbaf3` | DOProcessProductImageJob.php, ProductRepositoryImageResolver.php | Clamp de dimensão (lado maior ≤ **512 px**, preservando proporção) no resize — imagens novas nunca ficam gigantes |
| 7 | `b8cbaf3` | ResizeOversizedProductImagesCommand.php (novo) | Comando `plannerate:resize-oversized-images` (idempotente, `--dry-run`) para reescalar imagens já existentes |
| — | (operacional) | `storage/app/public/repositorioimages*/frente/*.webp` | 265 arquivos reescalados (−36 MB). Originais no disco `do` intactos (reversível) |

---

## Análise de risco

### ✅ CORRIGIDO — `loading="lazy"` podia quebrar exportação por imagem (item 4)

**Resolvido em 2026-06-24:** o `loading="lazy"` foi removido da imagem do canvas em `Layer.vue` (mantido `decoding="async"`). O texto abaixo fica como registro do que era o risco.

**Único risco real introduzido (já mitigado).** O export por screenshot ([useCanvasCapture.ts](../packages/callcocam/laravel-raptor-plannerate/resources/js/composables/plannerate/export/useCanvasCapture.ts)) usa `html2canvas` sobre o **canvas inteiro** (inclui módulos fora da viewport) e antes de capturar chama `waitForImagesReady`, que espera cada `<img>` disparar `load` ou estourar **timeout de 12 s**.

Imagens com `loading="lazy"` **fora da viewport não carregam** até serem roladas para perto da tela. Consequências possíveis no `exportAsImage` / PDF que captura o editor:

- **Atraso:** até ~12 s de espera por imagens lazy que nunca disparam `load`.
- **Imagens em branco:** módulos fora de vista podem sair sem a imagem do produto.

> **Recomendação:** **remover `loading="lazy"`** da imagem do canvas, mantendo `decoding="async"`. Como as imagens agora são pequenas (≤512 px, ~20–70 KB após o reprocessamento), carregar tudo eager é barato e elimina esse risco. O ganho de performance de clique veio do `decoding="async"` + imagens menores, **não** do lazy.
>
> Verificar também: o PDF usa `PdfLayer.vue` (separado, sem lazy) — provavelmente seguro; o risco está no `exportAsImage` do editor.

### 🟡 Mudanças de comportamento a validar (item 2 — pointerdown)

Funcionalmente corretas, mas com 2 efeitos colaterais sutis:

- **Seleção ao iniciar arraste:** agora arrastar um segmento o **seleciona** primeiro (antes não selecionava, pois o drag engolia o click). Comportamento esperado/aceitável, mas é uma mudança.
- **Guard anti re-seleção:** clicar de novo no segmento **já selecionado** não refaz `selectItem`. Validar: se o usuário **fechar o painel de propriedades** e clicar no MESMO segmento para reabrir, confirmar que reabre (o guard pula a re-seleção). Se o painel depende de re-disparar a seleção para abrir, pode não reabrir.

### 🟢 Baixo risco / sem regressão

- **Item 1 (categoria):** comportamento visual idêntico — a prop sempre valia exatamente `selectedTemplateCategoryId` (Canvas fazia `selectedGroupingNormalized = selectedTemplateCategoryId`). Confirmado que esses componentes do editor **não são reutilizados** com outra fonte de highlight (a cadeia `highlightGroupingNormalized` só existia no editor).
- **Item 3 (SegmentDetails):** lógica equivalente — mesma busca por `segment_id || id`, mesmo fallback `props.item`. Apenas deixou de varrer a árvore duas vezes.
- **Item 4 (sombras):** mudança **cosmética** (seleção/destaque agora sem drop-shadow, mantendo anel + fundo). `decoding="async"`: inócuo.
- **Itens 5 (data-attrs):** apenas atributos no DOM; sem efeito funcional. _Podem ser removidos quando o profiling não for mais necessário._
- **Item 6 (clamp backend):** só **reduz** (atua se `> maxSide`); nunca amplia. Não introduz distorção nova — o `resize()` para dimensões derivadas do produto já existia; o clamp escala ambos os lados pelo mesmo fator, preservando a proporção. Único trade-off: nitidez levemente menor no zoom máximo (512 px cobre ~300 px de exibição × DPR 2). **Não descer abaixo de ~320–384 px.**
- **Item 7 (comando):** idempotente, validado com `--dry-run`, `--threshold` ≥ `--max-side`; sobrescreve arquivos in-place, mas reversível reprocessando do original no disco `do`.

---

## Ações pendentes recomendadas

1. ✅ **FEITO (2026-06-24):** `loading="lazy"` removido de [Layer.vue](../packages/callcocam/laravel-raptor-plannerate/resources/js/components/plannerate/editor/Layer.vue) (mantido `decoding="async"`) — risco de export eliminado.
2. Validar reabertura do painel ao re-clicar o mesmo segmento após fechá-lo (item 2).
3. Testar `exportAsImage`/PDF de um planograma grande (vários módulos) antes e depois de (1).
4. (Opcional) Remover os data-attrs de diagnóstico (itens 4/5) quando não forem mais úteis.
5. (Opcional) Parametrizar `$maxSide` via config/env em vez de hardcode 512.

---

## Métricas

| Etapa | INP mediana | Pior caso |
|-------|-------------|-----------|
| Produção inicial | ~5.000 ms | 5.240 ms |
| Após itens 1–5 | ~16–40 ms | picos 700–2.900 ms (1º clique por módulo) |
| Após itens 6–7 (imagens pequenas) | 16–40 ms | picos eliminados |
