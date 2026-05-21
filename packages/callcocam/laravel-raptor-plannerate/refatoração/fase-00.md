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

**Aceite:** build verde, checklist manual ok, baseline commitado.
 
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
