# Documentação de Integração — Plannerate

> **Versão:** 1.0  
> **Audiência:** Desenvolvedores do ERP/sistema do cliente que precisam expor endpoints para o Plannerate consumir.

---

## Visão Geral

O Plannerate consome dados de produtos e vendas do sistema do cliente via HTTP. O **cliente é o servidor** — basta expor os endpoints descritos abaixo.

---

## Autenticação

Preferência: **HTTP Basic Auth**.

```
Authorization: Basic <base64(username:password)>
```

Também suportado: **Bearer Token** (estático ou via endpoint de login separado).

---

## Convenções

| Item | Padrão |
|------|--------|
| Método | `POST` (preferencial) ou `GET` |
| Content-Type | `application/json` |
| Encoding | UTF-8 |
| Datas | `YYYY-MM-DD` |
| Paginação | Server-side, obrigatória |

---

## Formato padrão da resposta

**Todos os endpoints devem seguir este envelope.** Não divergir.

```json
{
  "pagina": 1,
  "total_paginas": 10,
  "pagination": {
    "total": 9842
  },
  "dados": [
    { ... }
  ]
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `pagina` | integer | Página atual |
| `total_paginas` | integer | Total de páginas disponíveis |
| `pagination.total` | integer | Total de registros |
| `dados` | array | Lista de registros da página |

> Os nomes acima (`pagina`, `total_paginas`, `pagination.total`, `dados`) são os padrões esperados.

---

## Endpoint de Produtos

### Parâmetros aceitos (body para POST / query para GET)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `pagina` | integer | Sim | Número da página (começa em 1)                                                   |
| `tamanho_pagina` | integer | Sim | Registros por página                                                     |
| `produto` | string | Não | Filtro por produto — aceita EAN **ou** código ERP                                |
| `data_ultima_alteracao` | string (YYYY-MM-DD) | Não | Retorna apenas produtos com alteraçõe após essa data  |
| `data_cadastro` | string (YYYY-MM-DD) | Não | Retorna apenas produtos com cadastro após essa data           |
| `data_ultima_compra` | string (YYYY-MM-DD) | Não | Retorna apenas produtos com compra após essa data        |
| `data_ultima_venda` | string (YYYY-MM-DD) | Não | Retorna apenas produtos com venda após essa data          |
| `empresa` | string | Não | CNPJ da loja (somente dígitos), quando o sistema filtra por estabelecimento      |


### Campos esperados por produto (dentro de `dados[]`)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `produto` | string | **Sim** | Código ERP do produto |
| `ean` | string (EAN-13) | **Sim** | Código de barras |
| `descricao` | string | **Sim** | Nome/descrição do produto |
| `cadastro_ativo` | string | **Sim** | Status do cadastro (ex: `"S"` / `"N"`) |

### Exemplo de retorno

```json
{
  "pagina": 1,
  "total_paginas": 14,
  "pagination": { "total": 13842 },
  "dados": [
    {
      "produto": "001234",
      "ean": "7891000055120",
      "descricao": "LEITE INTEGRAL UHT 1L",
      "cadastro_ativo": "S",
      "descricao_comercial": "Leite Integral UHT 1L Marca X",
      "marca": "Marca X",
      "unidade": "UN",
      "peso_liquido": 1.0,
      "largura": 7.5,
      "altura": 24.0,
      "profundidade": 7.5
    }
  ]
}
```

> **Quaisquer outros campos são bem-vindos** — marca, medidas, tipo de embalagem, fornecedor, peso, etc. Quanto mais campos retornados, mais rico será o mapeamento.

---

## Endpoint de Vendas

### Parâmetros aceitos (body para POST / query para GET)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `produto` | string | Não | Filtro por produto — aceita EAN **ou** código ERP |
| `pagina` | integer | Sim | Número da página (começa em 1) |
| `tamanho_pagina` | integer | Sim | Registros por página |
| `data_inicial` | string (YYYY-MM-DD) | **Sim** | Data inicial do período |
| `data_final` | string (YYYY-MM-DD) | **Sim** | Data final do período |
| `empresa` | string | Não | CNPJ da loja (somente dígitos), quando o sistema filtra por estabelecimento |

### Campos esperados por registro de venda (dentro de `dados[]`)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `produto` | string | **Sim** | Código ERP do produto |
| `ean` | string (EAN-13) | Não | Código de barras |
| `data_venda` | date (YYYY-MM-DD) | **Sim** | Data da venda |
| `promocao` | string | **Sim** | Indicador de promoção (ex: `"S"` / `"N"`) |
| `quantidade` | decimal | **Sim** | Quantidade vendida |
| `valor_liquido` | decimal | A definir¹ | Valor líquido total da venda |
| `custo_comercial` | decimal | A definir¹ | Custo comercial |
| `custo_aquisicao` | decimal | A definir¹ | Custo de aquisição |
| `custo_medio_loja` | decimal | A definir¹ | Custo médio na loja |
| `valor_impostos` | decimal | A definir¹ | Total de impostos |

### Exemplo de retorno

```json
{
  "pagina": 1,
  "total_paginas": 5,
  "pagination": { "total": 4312 },
  "dados": [
    {
      "produto": "001234",
      "ean": "7891000055120",
      "data_venda": "2026-04-15",
      "promocao": "N",
      "quantidade": 24.0,
      "valor_liquido": 96.00,
      "custo_comercial": 12.50,
      "custo_aquisicao": 58.80,
      "custo_medio_loja": 60.00,
      "valor_impostos": 8.40
    }
  ]
}
```

> **Quaisquer outros campos são bem-vindos** — empresa, filial, operador, etc. Todos os campos extras serão armazenados e considerados durante o mapeamento.

> ¹ Esses campos precisam ser discutidos com o time Plannerate. A tendência é que sejam **obrigatórios**, pois são usados para calcular margens e indicadores de desempenho nos planogramas.

---

## Endpoint de Compras

### Parâmetros aceitos (body para POST / query para GET)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `produto` | string | Não | Filtro por produto — aceita EAN **ou** código ERP |
| `pagina` | integer | Sim | Número da página (começa em 1) |
| `tamanho_pagina` | integer | Sim | Registros por página |
| `data_inicial` | string (YYYY-MM-DD) | **Sim** | Data inicial do período |
| `data_final` | string (YYYY-MM-DD) | **Sim** | Data final do período |
| `empresa` | string | Não | CNPJ da loja (somente dígitos), quando o sistema filtra por estabelecimento |

### Campos esperados por registro de compra (dentro de `dados[]`)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `produto` | string | **Sim** | Código ERP do produto |
| `ean` | string (EAN-13) | Não | Código de barras |
| `data_compra` | date (YYYY-MM-DD) | **Sim** | Data da compra |
| `quantidade` | decimal | **Sim** | Quantidade comprada |
| `custo_unitario` | decimal | A definir¹ | Custo unitário do item |
| `custo_total` | decimal | A definir¹ | Custo total da compra |
| `fornecedor` | string | A definir¹ | Código ou nome do fornecedor |

### Exemplo de retorno

```json
{
  "pagina": 1,
  "total_paginas": 3,
  "pagination": { "total": 2180 },
  "dados": [
    {
      "produto": "001234",
      "ean": "7891000055120",
      "data_compra": "2026-04-10",
      "quantidade": 48.0,
      "custo_unitario": 2.45,
      "custo_total": 117.60,
      "fornecedor": "LATICÍNIOS EXEMPLO LTDA"
    }
  ]
}
```

> **Quaisquer outros campos são bem-vindos** — nota fiscal, pedido, filial, etc. Todos os campos extras serão armazenados e considerados durante o mapeamento.

> ¹ Campos de custo e fornecedor precisam ser discutidos com o time Plannerate. A tendência é que sejam **obrigatórios**, pois são usados para calcular indicadores de reposição nos planogramas.

---

## Códigos HTTP esperados

| Código | Comportamento |
|--------|--------------|
| `200` | Sucesso — dados processados |
| `4xx` | Falha imediata, sem retentativa |
| `5xx` | Retentativa com backoff (até 5 vezes) |

Timeout por requisição: **120 segundos**.
