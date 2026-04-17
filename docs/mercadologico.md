# Mercadológico — Resumo da funcionalidade

Documentação do módulo **Mercadológico**: tela de hierarquia de categorias com árvore, visualização em colunas (Kanban) e painel de detalhes.

---

## Visão geral

- **Rota:** `/mercadologico` (tenant).
- **Stack:** Laravel (backend) + Inertia + Vue 3.
- **Objetivo:** Navegar e gerenciar a árvore de categorias (segmentos, departamentos, subdepartamentos etc.) com expansão sob demanda, seleção, cores por hierarquia e ações (criar, duplicar, excluir, mover).

---

## URL e estado

A tela persiste estado na URL para permitir links diretos e compartilhamento.

| Parâmetro | Descrição |
|-----------|-----------|
| `expand` | IDs dos nós expandidos, separados por vírgula. O backend devolve só as raízes; os filhos são incluídos para cada ID em `expand`. |
| `selected` | Um ou mais IDs de categorias selecionadas (`id1` ou `id1,id2`). Define qual(is) ramo(s) aparecem destacados e qual nó é usado no painel de detalhes (e para `usage`). |

**Exemplo:**  
`/mercadologico?expand=idRaiz1,idRaiz2,idRaiz3&selected=idRaiz2`  
→ Três raízes expandidas; a segunda é a “selecionada” e cada raiz pode ter uma cor diferente no destaque.

---

## Backend

### Controller: `MercadologicoController`

- **index:** Lê `expand` e `selected` da query, monta a árvore com `getCategoriesTree($expandIds)`, calcula `usage` (filhos, produtos, planogramas) para o primeiro ID em `selected` e devolve props Inertia: `categories`, `expand`, `selected`, `usage`, `hierarchy_level_names`, URLs de move/destroy/store/duplicate.
- **move (PATCH):** Move categoria para outro pai (validação via `MercadologicoMoveRequest`).
- **destroy (DELETE):** Exclui categoria (com checagem de filhos/produtos/planogramas).
- **store (POST):** Cria subcategoria (nome + category_id; `nivel` e `level_name` definidos no backend).
- **duplicate (POST):** Duplica categoria e filhos (com `nivel` e `level_name`).

### Árvore de categorias

- **getExpandIdsFromRequest:** Normaliza `expand` (string ou array) para array de IDs.
- **getCategoriesTree($expandIds):**  
  - Raízes: `Category::whereNull('category_id')`.  
  - Para cada nó, `depth` vem de `nivel` (suporta nível 0 ou 1 para raiz).  
  - Filhos são carregados recursivamente apenas para IDs que estão em `$expandIds`.  
  - Cada nó na árvore inclui: `id`, `name`, `slug`, `depth`, `nivel`, `level_name`, `full_path`, `children`.

### Selected múltiplo

- `selected` na query pode ser uma string com vários IDs separados por vírgula.
- O controller normaliza para array e envia `selected` como array nas props.
- `usage` é calculado apenas para o primeiro ID de `selected` (painel de detalhes).

---

## Frontend

### Página: `resources/js/pages/tenant/mercadologico/index.vue`

- **Estado local:** `searchQuery`, `selectedIds` (sincronizado com a prop `selected` via `watch` com `immediate: true`).
- **Lógica de destaque e cores:**
  - **rootIds:** IDs das raízes (primeiro nível da árvore).
  - **activeRootIds:** Raízes que estão em `expand` **ou** que contêm algum nó em `selected`. São essas que recebem cor própria.
  - **highlightIds:** Para cada raiz ativa, a raiz + todos os descendentes; além disso, para cada nó em `selected`, o nó + ancestrais + descendentes (união). Assim, todas as árvores “ativas” ficam marcadas.
  - **rootColorIndex:** Mapa `rootId → índice` em `LEVEL_COLORS`, só para as raízes ativas, para que cada uma tenha uma cor distinta (na árvore e no Kanban).
- **Navegação:** `handleSelect` atualiza `selected` e `expand` na URL; `handleExpandToggle` atualiza `expand` (e mantém `selected` quando há um selecionado); `handleClear` limpa seleção e expand.

### Componentes principais

| Componente | Função |
|------------|--------|
| **MercadologicoPanelTree** | Árvore à esquerda: busca, lista de raízes, repasse de `expandIds`, `selectedIds`, `highlightIds`, `rootColorIndex`. |
| **MercadologicoPanelTreeItem** | Item recursivo da árvore: bolinha com cor da raiz (ou do nível), destaque com **cor da raiz** (fundo e barra lateral quando selecionado). |
| **MercadologicoKanban** | Colunas por profundidade (`flattenByDepth`); cards com borda esquerda e destaque usando a **cor da raiz** (fundo, borda, ring). |
| **MercadologicoDetailPanel** | Painel à direita: header, info (nível, nome, slug), ações (adicionar subcategoria, duplicar, excluir), legenda de níveis, instruções, modais (AddSubcategory, Delete, Duplicate). |

### Composable: `useMercadologicoTree.ts`

- **LEVEL_NAMES / LEVEL_COLORS:** Nomes e cores por nível (espelham o backend).
- **colorWithAlpha(rgb, alpha):** Converte `rgb(r,g,b)` em `rgba(..., alpha)` para fundos e bordas de destaque.
- **flattenByDepth:** Agrupa nós por `depth`; suporta `depth === 0` como primeiro nível (exibido como coluna 1 no Kanban).
- **getRootId(nodes, targetId):** Retorna o ID da raiz do nó (último ancestral ou o próprio id se for raiz).
- **getAncestorIds / getDescendantIds / findNodeById / getPathNames:** Navegação e caminho na árvore.

---

## Cores por hierarquia

- Cada **raiz ativa** (em `expand` ou com algum nó em `selected`) recebe um índice de cor (0, 1, 2, …) em `rootColorIndex`.
- Na **árvore:** bolinha e fundo de destaque usam a cor da raiz (`colorWithAlpha` para o fundo).
- No **Kanban:** borda esquerda do card e estilo de destaque (fundo, borda, ring) usam a cor da raiz.
- Assim, várias raízes podem ficar marcadas ao mesmo tempo, cada uma com uma cor diferente.

---

## Nível 0 vs 1

- O backend pode enviar raízes com `depth` a partir de `nivel` (ex.: `0` ou `1`).
- No frontend, `depth === 0` é tratado como primeiro nível para exibição (coluna 1 no Kanban, cor e indentação na árvore).
- `levelColor` e `cardColor` normalizam `depth` 0 para 1 ao indexar `LEVEL_COLORS`.

---

## Form Requests

- **MercadologicoStoreRequest:** `name`, `category_id` (opcional para raiz); validação com `exists:tenant.categories,id`.
- **MercadologicoMoveRequest:** `id`, `category_id` (nullable para mover para raiz); mesma regra de existência no tenant.

---

## Arquivos principais

**Backend**

- `app/Http/Controllers/Tenant/MercadologicoController.php`
- `app/Http/Requests/Tenant/MercadologicoStoreRequest.php`
- `app/Http/Requests/Tenant/MercadologicoMoveRequest.php`

**Frontend**

- `resources/js/pages/tenant/mercadologico/index.vue`
- `resources/js/composables/useMercadologicoTree.ts`
- `resources/js/components/mercadologico/*.vue` (PanelTree, PanelTreeItem, Kanban, DetailPanel e subcomponentes, dialogs)

**Testes**

- `tests/Feature/Tenant/MercadologicoUsageAndDestroyTest.php`
