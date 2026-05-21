# Plano de Refatoração — Composables `plannerate`

> Princípio geral: **o editor funciona hoje**. Refatorar em passos pequenos, cada um
> verde (testes + uso manual no browser) antes do próximo. Ordem do **menor risco** ao
> **maior risco**. Movimentação de pastas é o **último** passo.
>
> Regras do projeto a respeitar:
> - Comandos sempre via Docker: `docker compose exec php ...`
> - Wayfinder só com `-u root` (e, idealmente, não rodar — escrever actions à mão)
> - Pint após mexer em PHP (aqui é só TS/Vue → rodar ESLint/Prettier do projeto)
> - Cada mudança precisa de teste programático quando aplicável

## Como validar cada fase

```bash
# Type-check + lint do front (ajustar ao script real do projeto)
npm run build              # detecta erros de tipo/manifest
# ou, se existir:
npx vue-tsc --noEmit
npx eslint resources/js/composables/plannerate --fix
```

Depois, **teste manual no browser** do editor (golden path): abrir gôndola, mover
prateleira (drag + Ctrl+setas), mover/copiar segmento, ajustar quantidade por teclado,
desfazer/refazer, salvar, exportar PDF/imagem, posicionar produto rejeitado.

---

## FASE 0 — Rede de segurança (antes de tocar em qualquer coisa)

**Objetivo:** poder refatorar com confiança.

1. Garantir que o editor roda e os fluxos-chave funcionam (checklist acima). Anotar
   comportamento atual de undo em **Ctrl+←/→ de segmento** (hoje **não** desfaz — isso
   muda na Fase 3; documentar como mudança intencional).
2. Commit limpo do estado atual (`git status` limpo) para diffs nítidos.
3. (Opcional, recomendado) adicionar testes de unidade Vitest para funções puras já
   isoláveis: `useSectionHoles`, `useShelfZone`, `useShelfAreaCalculation`,
   `*Fields` (toCamel/toSnake/validate), `useArrayNavigation`,
   `useTargetStockAnalysis.getStockStatus/calculateSegmentCapacity`.

**Aceite:** build verde, checklist manual ok, baseline commitado.

---

## FASE 1 — Remoção de código morto (risco baixíssimo)

Cada item é removível isoladamente; rodar build entre eles.

1. **`reorderSegmentInShelf`** — remover de `editor/useSegmentOperations.ts:312`
   (0 usos confirmados).
2. **Deprecated alignment** — remover `alignHorizontal`, `alignVertical`, `distribute`
   de `usePlanogramEditor.ts:1387-1425` e do `return` (l.2667-2670). 0 usos externos.
3. **Bloco comentado `_convertOklchToRgb`** — remover `usePlanogramEditor.ts:2162-2216`.
4. **`debugHistory()` vazio** — remover função (l.2092) e do `return` (l.2688). Se algum
   componente referencia, manter um stub; grep antes.
5. **Seleção morta em `useGondolaState`** — remover `selectedType/selectedId/selectedItem`
   (l.24-28) **após** confirmar via grep que ninguém importa de `useGondolaState` esses
   nomes (componentes usam `usePlanogramSelection`). O `usePlanogramEditor` reexporta
   esses do state no `return` (l.2594-2596) — remover também, **conferindo** se algum
   `.vue` faz `editor.selectedItem`. (Grep inicial: só `selection.selectedItem` aparece.)
6. **`showDeleteConfirmation`/`showAddModuleDrawer` duplicados** — `useGondolaState`
   (l.31-32) vs refs locais no editor (l.1453/1458). Manter os locais do editor;
   remover os do state se sem consumidores.

> **Verificação obrigatória antes de cada remoção:**
> `grep -rn "<nome>" --include="*.ts" --include="*.vue" resources/js`

**Aceite:** build verde; checklist manual inalterado (nada some da UI).

---

## FASE 2 — Limpeza interna sem mudar contrato (risco baixo)

Sem alterar APIs públicas dos composables.

1. **Mover `invertSegmentsOrder` para dentro de `useShelfOperations()`**
   (`editor/useShelfOperations.ts:1-50`). Hoje está no topo do módulo, antes dos imports,
   funcionando por hoisting. Recolocar junto às outras funções, antes do `return`.
2. **Padronizar indentação/estilo:** rodar Prettier/ESLint em `useShelfAreaCalculation`,
   `useArrayNavigation`, `usePdfGenerator` (2→4 espaços conforme o resto).
3. **Reduzir `console.*`:** trocar logs de erro silenciosos por toasts onde o usuário
   precisa saber (ex.: falhas de fetch já têm toast; manter `console.error` só em
   `catch` realmente inesperados). Não remover em massa — revisar caso a caso.
4. **Tipar snapshots:** introduzir tipos para `beforeState`/`afterState` por `type`
   (discriminated union) reduzindo `any` em `usePlanogramEditor`. Pode ser incremental.

**Aceite:** build verde; diff só cosmético/organizacional; checklist manual ok.

---

## FASE 3 — Unificar lógica duplicada (risco médio) ⚠️ muda comportamento

> Aqui há uma **mudança intencional de comportamento**: undo passa a cobrir o
> Ctrl+←/→ de segmento. Comunicar/validar.

1. **Reordenação de segmento — fonte única.**
   - Fazer `useSegmentActions.moveLeft/moveRight` delegarem a
     `editor.swapSegmentPositions` (que passa por `commitOptimistic` → histórico).
   - Remover o `swapSegment` manual de `useSegmentActions.ts:89-203`.
   - Resultado: Ctrl+←/→ agora gera undo (consistente com o drag).
   - Manter o guard `segmentsMoving` (anti-duplo-disparo) no wrapper.

2. **Mover prateleira entre seções — fonte única.**
   - `usePlanogramKeyboard.handleShelfKeyboard` deve chamar
     `useShelfActions(...).moveLeft/moveRight` em vez do helper local
     `moveShelfToAdjacentSection`.
   - Remover `moveShelfToAdjacentSection` (`usePlanogramKeyboard.ts:555-621`).
   - Conferir que a reseleção da shelf após mover continua (mover essa lógica para o
     handler ou para a action).

3. **Exportação de imagem unificada.**
   - Extrair de `usePdfGenerator` a função `captureElementAsCanvas` para um util
     compartilhado (`export/useCanvasCapture.ts`) usando `html2canvas-pro`.
   - Reescrever `usePlanogramEditor.exportAsImage` para usar essa captura (remove ~190
     linhas de tratamento manual de `oklch`/clone/wrapper, l.2158-2348).

4. **Helpers de furos.**
   - Em `useSectionHoles`, extrair `computeHoleGeometry(section)` (retornando
     `{holeCount, marginTop, holeHeight, holeWidth, holeSpacing}`) e fazer as 3 funções
     públicas consumirem-no. Mesma saída, menos repetição.

**Aceite:** build verde; **teste manual focado**: undo após Ctrl+←/→ de segmento;
mover shelf entre seções por teclado; exportar imagem (cores corretas, sem `oklch`
quebrado); furos/snapping inalterados.

---

## FASE 4 — Extrair do God-object `usePlanogramEditor` (risco médio)

Reduzir as 2700 linhas mantendo a **mesma API pública** (o `return` não muda; só a
implementação é movida para módulos e reexportada).

1. **`operations/useSnapshots.ts`** — mover `captureBeforeState`, `captureAfterState`,
   `applySnapshot` e todos os `apply*Snapshot` (l.80-2055, exceto o que é orquestração).
   Recebem dependências (`history`, `recordChange`, lookups, reactivity helpers) por
   parâmetro/closure. Unificar capture before/after num helper parametrizado por fase.
2. **`core/useRejectedProducts.ts`** — mover `fetchRejectedProducts`, `placeFromRejected`,
   `swapRejectedProduct`, `deleteRejectedProduct`, `patchRejectedProductToLastAction`
   (l.2350-2578).
3. **`export/` ** — `exportAsImage` (já reescrita na Fase 3) + print/reports.
4. **`usePlanogramEditor` slim** — passa a compor esses módulos e expor o mesmo objeto.

> Cada extração é mecânica: cortar função → colar no módulo → importar de volta →
> manter no `return`. Build entre cada uma.

**Aceite:** API pública idêntica (mesmo `return`); build verde; checklist completo ok.

---

## FASE 5 — Factory para stores por EAN (risco médio)

1. Criar `analysis/useEanAnalysisStore.ts`:
   ```ts
   createEanAnalysisStore<T extends { classificacao?: 'A'|'B'|'C' }>(name: string)
   // expõe: set/setBatch/get/has/clear/remove, stats, hasData, isVisible,
   //        toggleVisibility/setVisibility, lastAnalysisDate
   ```
2. Refazer `useAbcClassification` e `useTargetStockAnalysis` sobre o factory,
   mantendo seus métodos específicos (`getStockStatus`, `calculateSegmentCapacity`,
   `calculateToleranceMargin`, `getClassification`) por composição.
3. **Preservar nomes de retorno** para não quebrar `usePerformanceIndicators` e os
   componentes (`AbcResultsList`, `TargetStockResultsList`, `Segment`).

**Aceite:** build verde; análises ABC e TargetStock no canvas e nas listas idênticas;
toggle de visibilidade e clear funcionam.

---

## FASE 6 — Unificar fonte de verdade da seleção (risco médio)

1. Confirmar que `usePlanogramSelection` é a única fonte usada por componentes (grep da
   análise indica que sim).
2. Remover qualquer resíduo de seleção em `useGondolaState`/`usePlanogramEditor`
   (parte já feita na Fase 1; aqui é o fechamento e a documentação do contrato).
3. Documentar no topo de `usePlanogramSelection` que é o **único** dono da seleção.

**Aceite:** build verde; seleção única e múltipla, atalhos de delete/duplicação ok.

---

## FASE 7 — Reorganização de pastas (MAIOR risco — por último)

> Só depois de tudo acima estável. Muitos arquivos `.vue` importam destes caminhos.

Estratégia para minimizar quebras:
1. Criar a nova estrutura de pastas (ver `00-resumo-analise.md §5`) movendo arquivos
   com `git mv` (preserva histórico).
2. Em cada pasta, criar `index.ts` (barrel) reexportando os símbolos públicos.
3. Criar um **barrel raiz** `composables/plannerate/index.ts` reexportando tudo, e
   atualizar imports dos `.vue` para o barrel quando possível.
4. Atualizar caminhos relativos internos dos composables.
5. Conferir aliases: `@/composables/plannerate/...`, `@plannerate/...` (libs/validation,
   libs/wayfinderPath) — ajustar `tsconfig`/`vite` se necessário.
6. Rodar build e corrigir imports quebrados em lote.

> Alternativa de menor risco: **não mover fisicamente**; apenas introduzir os barrels e
> documentar os agrupamentos lógicos. Avaliar custo/benefício com o time.

**Aceite:** build verde; `grep` não acha imports órfãos; checklist manual completo ok.

---

## Resumo de prioridade × risco × ganho

| Fase | Risco | Ganho | Quebra comportamento? |
|---|---|---|---|
| 1 — código morto | muito baixo | médio | não |
| 2 — limpeza interna | baixo | médio | não |
| 3 — unificar duplicação | médio | **alto** | **sim** (undo de segmento) |
| 4 — split do God-object | médio | **alto** | não (API igual) |
| 5 — factory EAN | médio | médio | não |
| 6 — seleção única | médio | médio | não |
| 7 — pastas | **alto** | médio | não (se barrels) |

**Recomendação:** executar 1→2→4 primeiro (ganho alto, sem mudar comportamento), depois
3 (com validação dedicada), e 5/6/7 conforme disponibilidade. A Fase 7 é opcional se o
time preferir manter caminhos atuais.
