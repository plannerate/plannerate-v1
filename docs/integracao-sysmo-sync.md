# Integracao Sysmo (Status do Documento)

Este documento foi descontinuado e mantido apenas por historico.

## Fonte oficial atual

Use somente:

- `docs/integrations-import-process.md`

## Motivo

Os comandos e fluxos antigos (`integrations:dispatch-*`, cadeias legadas e runbooks anteriores) foram substituidos pelo fluxo atual baseado em:

- `integrations:daily-imports`
- jobs de fetch/processamento em lote
- persistencia idempotente por IDs deterministicos
- regras de importacao por provider (`sysmo`, `gescooper`)

## Regra pratica

Se houver conflito entre este arquivo e `docs/integrations-import-process.md`, considere **sempre** o `integrations-import-process.md` como verdadeiro.
