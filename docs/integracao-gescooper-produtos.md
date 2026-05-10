# Integracao GesCooper (Status do Documento)

Este documento foi simplificado para evitar orientacoes antigas.

## Fonte oficial atual

Use somente:

- `docs/integrations-import-process.md`

## Regras vigentes (resumo)

- O disparo roda por `integrations:daily-imports`.
- O importer GesCooper usa token, cacheia token e busca produtos paginados.
- Se sales retornar 404, o fluxo deve registrar `skip` e nao quebrar importacao geral.
- `registros_por_pagina` deve vir da configuracao da integracao (com limite maximo de seguranca).
- Quando ja houver produtos, aplicar filtro incremental por `data_cadastro_de` / `data_cadastro_ate`.

## Observacao

Se houver qualquer divergencia entre este arquivo e o guia principal, siga `docs/integrations-import-process.md`.
