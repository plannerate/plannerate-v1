# Multitenancy: Validacoes em Conexao Tenant Dedicada

Este documento define o padrao para validacoes com `Rule::unique()` e `Rule::exists()` em requests tenant quando usamos banco dedicado por tenant.

## Problema que ocorreu

Com `tenant_database_connection_name = 'tenant'`, algumas validacoes com tabela em string simples (ex.: `stores`) consultaram a conexao errada (`landlord`) e geraram erro como:

- `Table 'landlord.stores' doesn't exist`

Isso acontece porque `Rule::unique('stores', ...)` e `Rule::exists('stores', ...)` podem usar a conexao default no contexto de validacao, em vez da conexao tenant esperada.

## Padrao obrigatorio

Em `app/Http/Requests/Tenant/*`, **sempre** use tabela com conexao tenant explicita.

Use o helper do trait `InteractsWithTenantContext`:

- `tenantTable('stores')` -> `tenant.stores` (ou fallback `stores` quando nao houver conexao tenant configurada, como em alguns cenarios de teste)

## Implementacao base

No trait `App\Support\Tenancy\InteractsWithTenantContext` existe:

- `tenantTable(string $table): string`

Exemplo:

```php
$storesTable = $this->tenantTable('stores');
$tenantId = $this->tenantId();

'slug' => [
    'nullable',
    'string',
    Rule::unique($storesTable, 'slug')->where('tenant_id', $tenantId),
];
```

## Regras praticas para novas implementacoes

- Em request tenant, nao usar `Rule::unique('tabela', ...)` literal.
- Em request tenant, nao usar `Rule::exists('tabela', ...)` literal.
- Sempre resolver tabela via `tenantTable('tabela')`.
- Sempre manter filtro de escopo por tenant quando aplicavel:
  - `->where('tenant_id', $tenantId)`
- Para update:
  - manter `->ignore($model)` junto com tabela tenant.

## Checklist rapido (antes de subir PR)

- [ ] Todo `Rule::unique` em `app/Http/Requests/Tenant` usa `tenantTable(...)`
- [ ] Todo `Rule::exists` em `app/Http/Requests/Tenant` usa `tenantTable(...)`
- [ ] Requests de update mantem `ignore(...)`
- [ ] Filtros por `tenant_id` continuam presentes quando aplicavel

## Observacoes

- Este padrao evita regressao na migracao para conexao tenant dedicada.
- Para requests landlord (`app/Http/Requests/Landlord`), manter regras com conexao landlord quando necessario.
