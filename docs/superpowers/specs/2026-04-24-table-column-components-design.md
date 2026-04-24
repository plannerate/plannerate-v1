# Design: Componentes de Coluna de Tabela Compartilhados

**Data:** 2026-04-24
**Status:** Aprovado

## Contexto

As 7 páginas de lista do tenant (`planograms`, `products`, `categories`, `gondolas`, `stores`, `providers`, `clusters`) usam tabelas HTML puras com padrões de células repetidos e não compartilhados:

- **StatusBadge:** `<Badge :variant="statusVariant(...)">` com `statusVariant()` definida inline e inconsistente entre páginas
- **Actions:** `EditButton` + `DeleteButton` duplicados em todas as 7 páginas
- **Image:** markup de thumbnail com fallback repetido em products
- **Date:** ícone de calendário + `formatDate()` repetido em planograms

O diretório `resources/js/components/table/columns/` existe mas está vazio. O objetivo é populá-lo com componentes Vue reutilizáveis e migrar todas as páginas para usá-los.

## Abordagem

**Componentes de conteúdo de célula (Approach A):** cada componente renderiza apenas o conteúdo dentro do `<td>`. O `<td>` permanece na página Index, mantendo flexibilidade para estilos por página. Nenhuma mudança de arquitetura — encaixa diretamente no padrão de tabelas HTML existente.

## Estrutura de Arquivos

```
resources/js/components/table/columns/
├── ColumnStatusBadge.vue    ← Badge com lógica statusVariant centralizada
├── ColumnActions.vue        ← EditButton + DeleteButton
├── ColumnImage.vue          ← thumbnail com fallback (ImageIcon)
├── ColumnDate.vue           ← Calendar icon + data formatada (única ou intervalo)
└── index.ts                 ← barrel export de todos os 4 componentes
```

## APIs dos Componentes

### ColumnStatusBadge.vue

```typescript
props: {
    status: string
}
```

- `statusVariant` centralizado internamente: `published → default`, `importer → secondary`, qualquer outro → `outline`
- Renderiza `<Badge :variant="..." class="capitalize">{{ status }}</Badge>`
- Unifica as implementações divergentes de planograms e categories

### ColumnActions.vue

```typescript
props: {
    editHref: string
    deleteHref: string
    deleteLabel?: string         // label de confirmação no modal de delete
    requireConfirmWord?: boolean // exige digitar palavra para confirmar (ex: planograms)
}
slots: {
    default?: void  // botões extras inseridos ANTES de edit/delete
}
```

- Renderiza `inline-flex items-center gap-2` com: slot default → `EditButton` → `DeleteButton`
- `deleteLabel` e `requireConfirmWord` repassados ao `DeleteButton`
- Slot default permite botões extras por página (ex: "Ver Gondolas" em planograms)

### ColumnImage.vue

```typescript
props: {
    src?: string | null
    alt?: string
}
```

- Se `src` for `null`/`undefined`: exibe `<ImageIcon class="size-10 text-muted-foreground">`
- Se `src` válido: exibe `<img class="h-10 w-10 rounded-md object-cover">`

### ColumnDate.vue

```typescript
props: {
    date?: string | null     // data única
    from?: string | null     // início de intervalo
    to?: string | null       // fim de intervalo
}
```

- Ícone `CalendarIcon` (lucide-vue-next) à esquerda
- `formatDate` definido internamente no componente (lógica extraída de planograms/Index.vue): `Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })`
- Data única: `formatDate(date)`
- Intervalo: `formatDate(from) – formatDate(to)`
- Se todos os valores forem null/undefined: exibe `—`

### index.ts (barrel)

```typescript
export { default as ColumnStatusBadge } from './ColumnStatusBadge.vue'
export { default as ColumnActions } from './ColumnActions.vue'
export { default as ColumnImage } from './ColumnImage.vue'
export { default as ColumnDate } from './ColumnDate.vue'
```

## Migração das Páginas

Todas as 7 páginas de lista serão atualizadas para substituir o markup inline pelos novos componentes:

| Página | Colunas migradas |
|--------|-----------------|
| planograms/Index.vue | ColumnStatusBadge, ColumnActions, ColumnDate |
| products/Index.vue | ColumnImage, ColumnActions |
| categories/Index.vue | ColumnStatusBadge, ColumnActions |
| gondolas/Index.vue | ColumnActions |
| stores/Index.vue | ColumnActions |
| providers/Index.vue | ColumnActions |
| clusters/Index.vue | ColumnActions |

Em cada página migrada:
- Substituir markup inline pelo componente correspondente
- Remover imports que ficarem sem uso (ex: `Badge`, `statusVariant` inline)

## Verificação

1. Abrir cada uma das 7 páginas de lista no browser
2. Confirmar que badges de status aparecem com cores corretas
3. Confirmar que botões de editar/deletar funcionam
4. Confirmar que imagens de produto aparecem com fallback correto
5. Confirmar que datas de planograma aparecem no formato correto
6. Rodar `./vendor/bin/sail npm run types:check` e confirmar sem novos erros
