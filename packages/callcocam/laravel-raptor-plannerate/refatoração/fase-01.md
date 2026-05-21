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
> - **Código bem comentado:** toda função nova ou movida deve ter um docblock JSDoc
>   (`/** ... */`) em PT-BR explicando o que faz, seguindo o estilo dos arquivos
>   vizinhos. Ao extrair/reescrever funções (Fases 3, 4, 5), manter ou adicionar esses
>   comentários.

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
