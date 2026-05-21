# Análise dos Composables `plannerate` — Resumo

> Escopo: `resources/js/composables/plannerate/`
> Estado atual: **funcionando**. Objetivo: melhorar organização, remover código
> obsoleto/duplicado, unificar responsabilidades semelhantes e reorganizar por pastas.
> Data da análise: 2026-05-21

## 1. Panorama

34 arquivos, ~10.400 linhas. Distribuição por tamanho:

| Arquivo | Linhas | Papel |
|---|---:|---|
| `usePlanogramEditor.ts` | 2700 | **Hub central** (God-composable) |
| `usePlanogramKeyboard.ts` | 1067 | Atalhos de teclado |
| `editor/useShelfOperations.ts` | 580 | CRUD de prateleiras |
| `usePlanogramChanges.ts` | 524 | Delta/auto-save |
| `editor/useSegmentOperations.ts` | 473 | Move/copy/reorder de segmentos |
| `usePlanogramSelection.ts` | 434 | Seleção (única + múltipla) |
| `usePdfGenerator.ts` | 344 | Exportação PDF |
| `useShelfActions.ts` | 331 | Ações de prateleira (UI/teclado) |
| `usePlanogramHistory.ts` | 294 | Undo/redo + localStorage |
| `useSegmentActions.ts` | 278 | Ações de segmento (UI/teclado) |
| `useProductsPanel.ts` | 260 | Listagem/paginação de produtos |
| `useTargetStockAnalysis.ts` | 250 | Análise de estoque (Map por EAN) |
| `useSectionActions.ts` | 231 | Ações de seção (UI/teclado) |
| `useShelfFields.ts` | 212 | Campos camel/snake de prateleira |
| `useSectionFields.ts` | 206 | Campos camel/snake de seção |
| `analysis/useAnalysisFilters.ts` | 189 | Filtro/busca/ordenação genérico |
| `editor/useShelfDragDrop.ts` | 194 | Drag&drop na prateleira |
| `editor/useShelfLayout.ts` | 171 | Geometria visual da prateleira |
| `useGondolaFields.ts` | 168 | Campos camel/snake de gôndola |
| `useSectionHoles.ts` | 157 | Cálculo de furos da cremalheira |
| `editor/useReactivityHelpers.ts` | 148 | Forçar reatividade Vue |
| `useAbcClassification.ts` | 143 | Classificação ABC (Map por EAN) |
| `editor/useSectionOperations.ts` | 141 | CRUD de seções |
| `editor/useLookupHelpers.ts` | 137 | Busca na árvore da gôndola |
| `editor/useShelfDrag.ts` | 105 | Estado de drag da prateleira |
| `useArrayNavigation.ts` | 100 | Navegação genérica em array |
| `usePerformanceIndicators.ts` | 95 | Orquestra ABC + TargetStock |
| `useProductSales.ts` | 96 | Fetch de vendas |
| `useShelfZone.ts` | 90 | Zonas (olhos/mãos/etc.) |
| `useShelfAreaCalculation.ts` | 90 | Área clicável da prateleira |
| `useProductImage.ts` | 66 | Download de imagem por EAN |
| `editor/useGondolaState.ts` | 63 | Estado global (singletons) |
| `usePlanogramUtils.ts` | 39 | Helper de confirmação de delete |
| `editor/useRejectedProductsStore.ts` | 20 | Callback de produto posicionado |

## 2. Arquitetura atual

```
plannerate/
├── usePlanogramEditor.ts          ← hub: importa editor/* e orquestra tudo
├── usePlanogramKeyboard.ts        ← atalhos; importa actions + editor
├── usePlanogramChanges.ts         ← persistência (delta + auto-save)
├── usePlanogramHistory.ts         ← undo/redo
├── usePlanogramSelection.ts       ← seleção
├── usePlanogramUtils.ts           ← helper de confirmação
├── use*Actions.ts (section/shelf/segment) ← lógica compartilhada UI/teclado
├── use*Fields.ts (section/shelf/gondola)  ← mapeamento camel/snake + defaults
├── geometria: useSectionHoles, useShelfZone, useShelfAreaCalculation
├── features: useProductsPanel, useProductSales, useProductImage,
│             useAbcClassification, useTargetStockAnalysis,
│             usePerformanceIndicators, usePdfGenerator, useArrayNavigation
├── analysis/
│   └── useAnalysisFilters.ts
└── editor/                        ← núcleo de mutação de estado
    ├── useGondolaState.ts         ← refs singleton (currentGondola, seleção, drag…)
    ├── useLookupHelpers.ts        ← findSectionById/ShelfById/SegmentById/…
    ├── useReactivityHelpers.ts    ← update*Reactive, reorderShelvesByPosition
    ├── useSectionOperations.ts
    ├── useShelfOperations.ts
    ├── useSegmentOperations.ts
    ├── useShelfDrag.ts
    ├── useShelfDragDrop.ts
    ├── useShelfLayout.ts
    └── useRejectedProductsStore.ts
```

O padrão central é bom: `usePlanogramEditor` aplica mudanças via **`commitOptimistic`**
(aplica → registra histórico before/after → agenda auto-save). As operações de baixo
nível vivem em `editor/*`. O problema é que o hub cresceu demais e há **três caminhos
paralelos** para algumas operações (editor, `*Operations`, `*Actions`).

## 3. Pontos fortes (preservar)

- **`commitOptimistic`** unifica histórico + persistência num único ponto. Excelente.
- Sistema de **delta/diff** em `usePlanogramChanges` com merge por entidade e debounce.
- `editor/*` já separa responsabilidades de mutação razoavelmente.
- `useAnalysisFilters` e `useArrayNavigation` são genéricos e reutilizáveis.
- `*Fields` centralizam conversão camel/snake com defaults — boa intenção.

## 4. Problemas encontrados

### 4.1 Código obsoleto / morto (remover)

| Item | Local | Evidência |
|---|---|---|
| `reorderSegmentInShelf` | `editor/useSegmentOperations.ts:312` | exportado, **0 usos** no projeto |
| `alignHorizontal`, `alignVertical`, `distribute` (deprecated) | `usePlanogramEditor.ts:1395-1425` | marcados `@deprecated`, **0 usos** fora do editor |
| Bloco `_convertOklchToRgb` comentado | `usePlanogramEditor.ts:2162-2216` | ~55 linhas em comentário |
| `debugHistory()` vazio | `usePlanogramEditor.ts:2092` | corpo vazio, só comentário |
| Seleção duplicada em `useGondolaState` | `editor/useGondolaState.ts:24-28` | `selectedType/Id/Item` não consumidos (componentes usam `usePlanogramSelection`) |
| `showDeleteConfirmation`/`showAddModuleDrawer` em `useGondolaState` | `editor/useGondolaState.ts:31-32` | `usePlanogramEditor` define os seus próprios `ref` locais (l.1453/1458); os do state são mortos |
| `showPropertiesPanel`/`showProductsPanel` redundância | verificar consumidores | possivelmente só um é usado |

### 4.2 Duplicação de lógica (unificar)

**a) Reordenação de segmento — 3 implementações:**
1. `usePlanogramEditor.swapSegmentPositions` → `editor/useSegmentOperations.swapSegmentPositions` (com histórico via `commitOptimistic`)
2. `useSegmentActions.swapSegment` (l.89) — reatividade manual + `recordChange`, **sem snapshot de histórico**
3. `editor/useSegmentOperations.reorderSegmentInShelf` — **morto**

Consequência real: Ctrl+← / Ctrl+→ no teclado usa o caminho (2), que **não gera undo**,
enquanto o drag usa (1), que gera. Inconsistência observável pelo usuário.

**b) Mover prateleira entre seções — 2 implementações:**
- `usePlanogramKeyboard.moveShelfToAdjacentSection` (l.555)
- `useShelfActions.moveBetweenSections` (l.258)

Ambas fazem a mesma coisa (calculam seção adjacente respeitando `flow`, setam flag
`shelvesMovingBetweenSections`, chamam `editor.updateShelf`). O keyboard deveria
delegar para `useShelfActions`.

**c) `captureBeforeState` / `captureAfterState`:**
`usePlanogramEditor.ts:80-307` — dois `switch` quase idênticos (~230 linhas).
Pode virar um único helper parametrizado por fase.

**d) Snapshot apply (`applyShelfSnapshot`, `applySegmentSnapshot`, …):**
~560 linhas (l.1497-2055) de handlers com forte repetição do padrão
"localiza entidade → `Object.assign(beforeState)` → força reatividade → `recordChange`".

**e) Stores por EAN (`useAbcClassification` vs `useTargetStockAnalysis`):**
Estrutura quase idêntica: `Map<ean, X>`, `lastAnalysisDate`, `isVisible`,
`toggle/setVisibility`, `stats` (classA/B/C), `hasData`, `clear/remove`. Candidato a
um factory genérico `createEanAnalysisStore<T>()`.

**f) Cálculo de furos:** `calculateHolePositions`, `calculateHoles`, `calculateHoleCount`
(em `useSectionHoles.ts`) repetem o mesmo prelúdio de extração/contagem. Extrair um
`computeHoleGeometry(section)` interno.

**g) `*Fields` com colisão de nomes:** os três (`useSectionFields`, `useShelfFields`,
`useGondolaFields`) exportam `toSnakeCase`/`toCamelCase` com o **mesmo nome** — importar
dois ao mesmo tempo exige alias. Considerar prefixos ou um factory `createFieldMapper`.

**h) Exportação de imagem duplicada:** `usePlanogramEditor.exportAsImage` usa
`html2canvas` cru + conversão manual de `oklch`, enquanto `usePdfGenerator` usa
`html2canvas-pro` (que já trata `oklch`) com lógica robusta de espera de fontes/imagens.
`exportAsImage` deveria reusar o pipeline de captura do `usePdfGenerator`.

### 4.3 Problemas estruturais

| Problema | Local | Impacto |
|---|---|---|
| Função declarada **fora** do composable, **antes dos imports** | `editor/useShelfOperations.ts:1-50` (`invertSegmentsOrder`) | funciona por hoisting, mas é frágil/confuso |
| `usePlanogramEditor` é um God-object (2700 linhas, ~70 retornos) | `usePlanogramEditor.ts` | difícil de manter/testar |
| Estado de seleção em **dois lugares** | `useGondolaState` + `usePlanogramSelection` | fonte de verdade ambígua |
| `console.*` e `console.error` espalhados | vários | ruído em produção; faltam toasts ao usuário em alguns erros |
| Indentação inconsistente (2 vs 4 espaços) | `useShelfAreaCalculation`, `useArrayNavigation`, `usePdfGenerator` | rodar Prettier/ESLint |
| `any` pervasivo nos handlers de snapshot | `usePlanogramEditor` | perde segurança de tipos |
| `didMount` module-level | `useProductsPanel.ts:25` | compartilhado entre instâncias (risco se houver 2 painéis) |

## 5. Reorganização de pastas proposta

Mover de "tudo na raiz" para agrupamento por responsabilidade:

```
plannerate/
├── core/                  # estado + persistência + histórico + seleção
│   ├── useGondolaState.ts          (de editor/)
│   ├── useLookupHelpers.ts         (de editor/)
│   ├── useReactivityHelpers.ts     (de editor/)
│   ├── usePlanogramChanges.ts
│   ├── usePlanogramHistory.ts
│   ├── usePlanogramSelection.ts
│   └── usePlanogramEditor.ts       (slim, ver passo de split)
├── operations/            # mutações de baixo nível
│   ├── useSectionOperations.ts
│   ├── useShelfOperations.ts
│   ├── useSegmentOperations.ts
│   └── useSnapshots.ts             (extraído do editor: capture/apply)
├── actions/               # lógica compartilhada UI+teclado
│   ├── useSectionActions.ts
│   ├── useShelfActions.ts
│   └── useSegmentActions.ts
├── interactions/          # teclado + drag&drop
│   ├── usePlanogramKeyboard.ts
│   ├── useShelfDrag.ts
│   ├── useShelfDragDrop.ts
│   └── useRejectedProductsStore.ts
├── fields/                # mapeamento camel/snake + defaults
│   ├── useSectionFields.ts
│   ├── useShelfFields.ts
│   └── useGondolaFields.ts
├── geometry/              # cálculos visuais
│   ├── useSectionHoles.ts
│   ├── useShelfZone.ts
│   ├── useShelfAreaCalculation.ts
│   └── useShelfLayout.ts
├── analysis/              # ABC / target stock / filtros
│   ├── useAbcClassification.ts
│   ├── useTargetStockAnalysis.ts
│   ├── usePerformanceIndicators.ts
│   ├── useEanAnalysisStore.ts      (factory novo, ver 4.2.e)
│   └── useAnalysisFilters.ts
├── products/              # painel/vendas/imagem
│   ├── useProductsPanel.ts
│   ├── useProductSales.ts
│   └── useProductImage.ts
├── export/
│   └── usePdfGenerator.ts          (+ capture compartilhada)
└── shared/
    ├── useArrayNavigation.ts
    └── usePlanogramUtils.ts
```

> Observação: como há **muitos imports** apontando para os caminhos atuais (inclusive
> `@plannerate/...` e `@/composables/plannerate/...`), a movimentação de pastas é a
> mudança de maior risco. Fazer **por último** e com barrels (`index.ts`) de
> reexportação para minimizar quebras. Ver `01-plano-refatoracao.md`.

## 6. Próximos documentos

- `01-plano-refatoracao.md` — passos ordenados (baixo→alto risco), com critérios de aceite.
