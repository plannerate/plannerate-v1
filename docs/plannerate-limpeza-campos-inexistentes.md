# Limpeza de Campos Inexistentes no Plannerate

## Objetivo

Documentar o processo de limpeza aplicado no pacote `packages/callcocam/laravel-raptor-plannerate` para evitar erros de banco do tipo `Unknown column`, removendo apenas campos realmente usados em operações SQL e preservando campos auxiliares de cálculo.

## Problema Encontrado

Alguns serviços/controladores estavam enviando para `insert/update/select/where` campos que não existem nas tabelas definidas em `packages/callcocam/laravel-raptor-plannerate/database/migrations/clients`.

Exemplos de campos problemáticos encontrados:

- `segments`: `depth`, `position_x`, `position_y`, `segment_position`
- `layers`: `position_x`, `position_y`, `position_z`, `rotation`, `layer_position`, `gondola_id`
- `shelves`: `shelf_thickness`, `shelf_color`
- `gondolas`: `height`, `width`, `depth`, `description`
- `categories`: `parent_id` (schema usa `category_id`)

## Princípio Aplicado

Regra usada durante a limpeza:

1. **Remover apenas campos que entram em SQL** e não existem no schema real.
2. **Não remover campos auxiliares** usados só em DTO, payload, IA, cálculos em memória ou frontend.
3. Quando possível, **substituir campo inválido por JOIN relacional correto**, sem alterar regra de negócio.

## Mudanças Aplicadas

### Serviços (filtros de persistência)

- `SegmentService`:
    - removidos de update: `depth`, `position_x`, `position_y`.
- `LayerService`:
    - criação de segment (payload interno): removidos `depth`, `position_x`, `position_y`.
    - update de layer: removidos `position_x`, `position_y`, `position_z`, `rotation`.
- `ShelfService`:
    - criação de shelf: removidos `shelf_thickness`, `shelf_color`.
    - criação de segmentos da shelf: removidos `segment_position`, `depth`, `position_x`, `position_y`.
    - criação de layers da shelf: removido `layer_position`.
- `GondolaService`:
    - criação: removidos `height`, `width`, `depth`.
    - update: removido `description`.

### Leitura/Query com risco de erro SQL

- `GondolaController`:
    - removida dependência de `layers.gondola_id` (coluna inexistente).
    - substituído por JOIN: `layers -> segments -> shelves -> sections` com filtro em `sections.gondola_id`.
- `Layer` model:
    - removido `boot()` que tentava popular `gondola_id` em `creating`.
- `IAPlanogramService`:
    - corrigido `get(['id', 'name', 'parent_id'])` para `get(['id', 'name', 'category_id'])`.

## Checklist de Limpeza Futura

Use este checklist em novas limpezas:

1. Listar colunas reais das migrations do pacote.
2. Buscar no código operações SQL com campos suspeitos:
    - `insert`, `update`, `create`, `select`, `get([..])`, `where`, `orderBy`, `pluck`.
3. Comparar cada campo usado no SQL com as colunas reais da tabela.
4. Corrigir apenas os campos inválidos com risco de erro de banco.
5. Preservar campos auxiliares fora de SQL.
6. Rodar formatação e testes focados.

## Prompt Reutilizável para Próximas Limpezas

```text
Audite o pacote packages/callcocam/laravel-raptor-plannerate para identificar campos usados em operações SQL (insert/update/select/where/get/pluck/orderBy) que não existem nas tabelas das migrations em packages/callcocam/laravel-raptor-plannerate/database/migrations/clients.

Regras obrigatórias:
1) Remova/corrija apenas campos que causam risco real de erro SQL (Unknown column).
2) Não remova campos auxiliares usados apenas em cálculo, DTO, payload, frontend ou IA.
3) Quando houver campo inválido em query, prefira corrigir via JOIN/relacionamento correto em vez de quebrar regra de negócio.
4) Faça varredura completa em services, controllers, repositories e models do pacote.
5) Ao final, rode pint e testes focados, e entregue relatório com:
   - arquivos alterados,
   - campos removidos/corrigidos,
   - motivo técnico de cada ajuste.
```

## Validação Executada nesta Limpeza

- `vendor/bin/pint --dirty --format agent`
- `php artisan test --compact tests/Unit/PlannerateEditorModelConnectionTest.php`
- verificação de lint nos arquivos alterados

