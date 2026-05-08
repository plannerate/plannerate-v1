# Integração GesCooper - Endpoint `Produtos/Produtos`

Fonte oficial (Swagger):
- `https://web.cooasgo.com.br/GesCooper/Cadastro/swagger/v1/swagger.json`

Base URL observada no Swagger:
- server: `/GesCooper/Cadastro`
- endpoint: `GET /Api/Produtos/Produtos`

No ambiente configurado hoje no projeto, a chamada final fica no formato:
- `{base_url}/Produtos/Produtos`
- Exemplo: `https://web.cooasgo.com.br/GesCooper/Cadastro/Produtos/Produtos`

## Autenticação

O Swagger define `Authorization` no header (tipo `apiKey`), usando:
- `Authorization: Bearer {token}`

Token:
- Endpoint de geração no Swagger: `POST /Api/v1/Token`
- Payload (`UsuarioToken`): `usuario`, `senha`, `dispositivoUID` (opcional)

## Parâmetros de consulta de `GET /Api/Produtos/Produtos`

- `pagina` (integer, default `1`)
- `registros_por_pagina` (integer, default `200`)
- `ean` (string, opcional)
- `status_produto` (string, opcional)
- `data_cadastro_de` (string, format `date-time`, opcional)
- `data_cadastro_ate` (string, format `date-time`, opcional)
- `data_ultima_venda_de` (string, format `date-time`, opcional)
- `data_ultima_venda_ate` (string, format `date-time`, opcional)
- `api-version` (string, default `1.0`)

## Paginação

Parâmetros principais:
- `pagina`
- `registros_por_pagina`

No projeto, esses campos já são enviados por:
- [GesCooperProductsIntegrationService.php](/home/call/projects/plannerate-v1/app/Services/Integrations/GesCooper/GesCooperProductsIntegrationService.php)

## Filtro por período de cadastro (uso atual no projeto)

Implementação atual:
- Se vier `data_cadastro_de` e `data_cadastro_ate` no filtro, usa os dois explicitamente.
- Senão, se vier `date`, envia dia único (`de=ate=date`).
- Senão, calcula janela por `products_initial_days` (de `ontem - (dias-1)` até `ontem`).

Referência de implementação:
- [GesCooperProductsIntegrationService.php](/home/call/projects/plannerate-v1/app/Services/Integrations/GesCooper/GesCooperProductsIntegrationService.php#L326)

## Exemplo de chamada

```bash
curl -X GET \
  'https://web.cooasgo.com.br/GesCooper/Cadastro/Api/Produtos/Produtos?pagina=1&registros_por_pagina=200&data_cadastro_de=2026-05-01T00:00:00&data_cadastro_ate=2026-05-07T23:59:59&api-version=1.0' \
  -H 'Authorization: Bearer SEU_TOKEN'
```

## Observações

- No Swagger, `data_cadastro_*` está como `date-time`.
- No nosso código, hoje enviamos datas normalizadas em `Y-m-d`; se precisar aderência estrita ao Swagger, podemos ajustar para `Y-m-d\TH:i:s`.
