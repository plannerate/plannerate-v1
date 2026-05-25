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

## Endpoint de Produtos

### Parâmetros aceitos (body para POST / query para GET)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `pagina` | integer | Sim | Número da página (começa em 1) |
| `tamanho_pagina` | integer | Sim | Registros por página |
| `data_ultima_alteracao` | string (YYYY-MM-DD) | Não | Retorna apenas produtos alterados após essa data |
| `empresa` | string | Não | CNPJ da loja (somente dígitos), quando o sistema filtra por estabelecimento |

> Os nomes dos campos são configuráveis no painel. Os nomes acima são os padrões usados nas integrações existentes.

### Formato da resposta

```json
{
  "pagina": 1,
  "total_paginas": 14,
  "pagination": {
    "total": 13842
  },
  "dados": [
    {
      "produto": "001234",
      "descricao": "LEITE INTEGRAL UHT 1L",
      "descricao_comercial": "Leite Integral UHT 1L Marca X",
      "unidade_venda": {
        "codigo": "UN",
        "descricao": "Unidade"
      },
      "marca": {
        "descricao": "Marca X"
      },
      "gtins": {
        "completo": [
          { "principal": "S", "gtin": "7891000055120" }
        ]
      },
      "estoque": {
        "disponivel": 48.0
      },
      "fornecedores": [
        { "data_ultima_compra": "2026-04-15" }
      ],
      "cadastro_ativo": "S"
    }
  ]
}
```

### Campos esperados por produto

| Campo | Tipo | Obrigatório |
|-------|------|-------------|
| `produto` | string | **Sim** |
| `gtins.completo[principal=S].gtin` | string (EAN-13) | **Sim** |
| `descricao` | string | Não |
| `descricao_comercial` | string | Não |
| `unidade_venda.descricao` | string | Não |
| `unidade_venda.codigo` | string | Não |
| `marca.descricao` | string | Não |
| `estoque.disponivel` | decimal | Não |
| `fornecedores.*.data_ultima_compra` | date | Não |
| `cadastro_ativo` | string | Não |

> Registros sem `ean` ou `codigo_erp` são descartados. A estrutura dos campos é flexível — informe ao time Plannerate os nomes exatos usados no seu sistema.

---

## Endpoint de Vendas

### Parâmetros aceitos (body para POST / query para GET)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `pagina` | integer | Sim | Número da página (começa em 1) |
| `tamanho_pagina` | integer | Sim | Registros por página |
| `data_inicial` | string (YYYY-MM-DD) | **Sim** | Data inicial do período |
| `data_final` | string (YYYY-MM-DD) | **Sim** | Data final do período |
| `empresa` | string | Não | CNPJ da loja (somente dígitos), quando o sistema filtra por estabelecimento |

### Formato da resposta

```json
{
  "pagina": 1,
  "total_paginas": 5,
  "pagination": {
    "total": 4312
  },
  "dados": [
    {
      "produto": "001234",
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

### Campos esperados por registro de venda

| Campo | Tipo | Obrigatório |
|-------|------|-------------|
| `produto` | string | **Sim** |
| `data_venda` | date (YYYY-MM-DD) | **Sim** |
| `promocao` | string | **Sim** |
| `quantidade` | decimal | **Sim** |
| `valor_liquido` | decimal | A definir¹ |
| `custo_comercial` | decimal | A definir¹ |
| `custo_aquisicao` | decimal | A definir¹ |
| `custo_medio_loja` | decimal | A definir¹ |
| `valor_impostos` | decimal | A definir¹ |

> ¹ Esses campos precisam ser discutidos com o time Plannerate. A tendência é que sejam **obrigatórios**, pois são usados para calcular margens e indicadores de desempenho nos planogramas.

---

## Paginação — Campos de controle na resposta

O Plannerate lê os seguintes campos para controlar a paginação (nomes configuráveis):

| Campo | Descrição |
|-------|-----------|
| `pagina` | Página atual |
| `total_paginas` | Total de páginas |
| `pagination.total` | Total de registros |

O array de itens (aqui chamado `dados`) também é configurável — informe o nome do campo ao time Plannerate.

---

## Códigos HTTP esperados

| Código | Comportamento |
|--------|--------------|
| `200` | Sucesso — dados processados |
| `4xx` | Falha imediata, sem retentativa |
| `5xx` | Retentativa com backoff (até 5 vezes) |

Timeout por requisição: **120 segundos**.
