# Base EAN enxuta e util

## Objetivo

Ter uma base de referencia por EAN no escopo do tenant para aumentar a taxa de classificacao automatica de produtos novos vindos da API de clientes.

Em vez de redescobrir a categoria toda vez, o sistema consulta o EAN e reaproveita:
- categoria folha (`category_id`)
- descricao comercial (`reference_description` -> `products.name`)
- marca e embalagem (`brand`, `subbrand`, `packaging_type`, `packaging_size`, `measurement_unit`)

## Tabela usada

Tabela: `ean_references`

Migration principal: `database/migrations/2026_04_27_212314_create_ean_references_table.php`

Colunas relevantes:
- `tenant_id`
- `ean`
- `category_id` (FK para `categories.id`)
- `reference_description`
- `brand`
- `subbrand`
- `packaging_type`
- `packaging_size`
- `measurement_unit`
- `created_at`, `updated_at`, `deleted_at`

Regra chave:
- `UNIQUE (tenant_id, ean)` -> 1 EAN para 1 referencia por tenant.

## O que nao entra aqui

Nao guardar de novo a hierarquia inteira (segmento, departamento, subdepartamento etc.) nesta tabela.

A hierarquia continua centralizada em `categories`. Aqui fica apenas o `category_id` resolvido.

## Fluxo minimo

1. Importacao de categorias monta/resolve a hierarquia.
2. Com o EAN da linha, faz upsert em `ean_references` por `(tenant_id, ean)`.
3. Salva `category_id` e campos extras de produto.
4. Em importacoes de produtos (ou sync diario da API clients), busca o EAN em `ean_references`.
5. Se achar, aplica automaticamente categoria e campos comerciais no produto.
6. Se nao achar, produto segue sem classificacao automatica (fica para regra futura ou ajuste manual).

## Regras de negocio

- Sempre normalizar EAN antes de gravar/consultar (apenas digitos).
- Escopo sempre por tenant.
- Preferencia inicial: 1 EAN = 1 categoria por tenant.
- Se no futuro aparecer conflito real de categoria para o mesmo EAN, tratar com versionamento/historico, nao agora.

## Beneficio esperado

- Menos produto novo sem categoria.
- Reuso progressivo do conhecimento de cada importacao.
- Solucao simples e segura para evoluir sem exagero.