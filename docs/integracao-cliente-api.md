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

| Campo | Tipo | Obrigatório |
|-------|------|-------------|
| `produto` | string | **Sim** | código ERP
| `ean` | string (EAN-13) | **Sim** |
| `descricao` | string | **Sim**  |
| `cadastro_ativo` | string | **Sim**  |

> **Quaisquer outros campos são bem-vindos** — marca, medidas, tipo de embalagem, fornecedor, etc. Todos os campos extras retornados serão considerados durante o mapeamento.

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

| Campo | Tipo | Obrigatório |
|-------|------|-------------|
| `produto` | string | **Sim** | código ERP |
| `ean`  string | Não |
| `data_venda` | date (YYYY-MM-DD) | **Sim** |
| `promocao` | string | **Sim** |
| `quantidade` | decimal | **Sim** |
 <!-- valores disponiveis -->

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

| Campo | Tipo | Obrigatório |
|-------|------|-------------|
| `produto` | string | **Sim** | código ERP |
| `ean`  string | Não |
| `data_compra` | date (YYYY-MM-DD) | **Sim** |
| `quantidade` | decimal | **Sim** |
<!-- valores disponíveis -->

> ¹ Campos de custo e fornecedor precisam ser discutidos com o time Plannerate. A tendência é que sejam **obrigatórios**, pois são usados para calcular indicadores de reposição nos planogramas.

---

## Códigos HTTP esperados

| Código | Comportamento |
|--------|--------------|
| `200` | Sucesso — dados processados |
| `4xx` | Falha imediata, sem retentativa |
| `5xx` | Retentativa com backoff (até 5 vezes) |

Timeout por requisição: **120 segundos**.
