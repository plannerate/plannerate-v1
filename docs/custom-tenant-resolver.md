# TenantResolver personalizado

Este documento descreve como configurar e implementar um **TenantResolver customizado** no Laravel Raptor, e o que é importante cuidar para evitar erros e manter o multi-tenancy consistente.

## Quando usar um resolver customizado

Use um resolver customizado quando:

- A identificação do tenant não é só por **domínio na tabela `tenants`** (ex.: tabela `tenant_domains`, domínio por Client/Store).
- Você precisa de **dados adicionais** no contexto (ex.: `domainable_type` / `domainable_id` para Client ou Store).
- A regra de **landlord** é diferente (outro subdomínio, path, header, etc.).
- Você precisa de **cache ou lógica extra** antes de buscar o tenant (ex.: API, cookie, sessão).

O resolver padrão (`Callcocam\LaravelRaptor\Services\TenantResolver`) resolve apenas por **host** → coluna `domain` na tabela `tenants`, com status `Published`, e considera landlord pelo subdomínio configurado em `raptor.landlord.subdomain`.

---

## Configuração

No `config/raptor.php`:

```php
'services' => [
    'tenant_resolver' => \Callcocam\LaravelRaptor\Services\TenantResolver::class,  // padrão
    // Ou sua classe:
    'tenant_resolver' => \App\Services\MyTenantResolver::class,
],
```

A classe é registrada como **singleton** no container; o mesmo resolver é usado durante toda a requisição.

---

## Interface obrigatória

Seu resolver deve implementar `Callcocam\LaravelRaptor\Contracts\TenantResolverInterface`:

| Método | Obrigatório | Descrição |
|--------|-------------|-----------|
| `resolve(Request $request): mixed` | Sim | Resolve o tenant a partir da requisição e retorna o model ou `null`. Deve chamar `storeTenantContext()` quando encontrar tenant. |
| `getTenant(): mixed` | Sim | Retorna o tenant já resolvido nesta requisição (ou `null`). |
| `isResolved(): bool` | Sim | Indica se o tenant já foi resolvido (evita resolver duas vezes). |
| `storeTenantContext(mixed $tenant, ?object $domainData = null): void` | Sim | Grava o contexto no app (config, instâncias, banco). |
| `configureTenantDatabase(mixed $tenant, ?object $domainData = null): void` | Sim | Aplica apenas a configuração de banco do tenant (útil em jobs/commands). |

---

## O que o resolver padrão faz (referência)

1. **Cache por requisição**: `$this->resolved` e `$this->tenant` para não resolver duas vezes.
2. **Landlord**: se o host contém `{raptor.landlord.subdomain}.` (ex.: `landlord.`), define `config(['app.context' => 'landlord'])` e retorna `null` (sem tenant).
3. **Busca do tenant**: `Tenant::where($domainColumn, $domain)->where('status', TenantStatus::Published->value)->first()`.
4. **storeTenantContext**: monta `ResolvedTenantConfig::from($tenant, $domainData)`, registra instâncias (`tenant.context`, `current.tenant`, `tenant`, `ResolvedTenantConfig`), aplica `config($config->toAppConfig())`, `Landlord::addTenant()` e `TenantDatabaseManager::applyConfig($config)`.

Ao customizar, você pode **estender** `TenantResolver` e sobrescrever só `detectTenant()`, ou implementar a interface do zero.

---

## Pontos importantes ao implementar

### 1. Sempre tratar o contexto Landlord primeiro

Se a requisição for para o painel landlord, **não** tente resolver tenant:

- Defina `config(['app.context' => 'landlord'])`.
- Retorne `null` e **não** chame `storeTenantContext()`.

Caso contrário, middlewares e outras partes do pacote podem assumir contexto tenant e quebrar.

### 2. Resolver apenas uma vez por requisição

Mantenha um estado interno (`$this->resolved`, `$this->tenant`). Em `resolve()`:

- Se já resolveu, retorne `$this->tenant` e não chame `storeTenantContext()` de novo.
- Só chame `storeTenantContext()` quando **encontrar** um tenant e ainda não tiver armazenado.

Assim você evita reconfigurações desnecessárias e efeitos colaterais.

### 3. O que passar para `storeTenantContext($tenant, $domainData)`

- **$tenant**: deve ser um **Eloquent Model** (ex.: `Tenant`). É usado em `ResolvedTenantConfig::from()`:
  - `$tenant->getKey()` → identificador do tenant.
  - `$tenant->getAttribute('database')` → nome do banco dedicado (opcional). Se for string, o pacote configura a conexão para esse banco.
- **$domainData** (opcional): objeto com:
  - `domainable_type`: classe do “dono” do domínio (ex.: `App\Models\Client`).
  - `domainable_id`: ID desse dono.

Isso preenche `app.current_domainable_type`, `app.current_domainable_id` e, se o tipo for Client/Store, `app.current_client_id` / `app.current_store_id`. Use quando o domínio estiver atrelado a Client ou Store em vez de só ao Tenant.

### 4. ResolvedTenantConfig e banco dedicado

- `ResolvedTenantConfig::from($tenant, $domainData)` espera um **Model** com `getKey()` e, se houver banco por tenant, atributo `database`.
- Se o seu “tenant” lógico for Client/Store, você ainda precisa de um **model Tenant** (ou equivalente) para passar para `ResolvedTenantConfig::from()`. Esse model deve refletir o banco a usar (ex.: tenant “pai” do client/store).
- `TenantDatabaseManager::applyConfig($config)` só altera conexão/banco se `$config->hasDedicatedDatabase()` for verdadeiro (atributo `database` preenchido).

### 5. Status e coluna de domínio

- No padrão, só tenant com **status** `TenantStatus::Published` é considerado. Em customizado, aplique a mesma regra ou a que fizer sentido (ex.: ativo, não suspenso).
- A coluna de domínio vem de `config('raptor.tenant.subdomain_column', 'domain')`. Se usar outra tabela (ex.: `tenant_domains`), sua lógica é responsável por buscar o tenant correto e passar o model para `storeTenantContext`.

### 6. configureTenantDatabase

- Usado quando o contexto de tenant já existe (ex.: job, command) e você só precisa **reaplicar** a configuração de banco (conexão/switch).
- Deve chamar `ResolvedTenantConfig::from($tenant, $domainData)` e em seguida `TenantDatabaseManager::applyConfig($config)`.
- Se `$tenant` for `null`, não faça nada (igual ao resolver padrão).

### 7. Não depender de ordem de boot

O resolver é chamado no middleware/request; não assuma que outras partes do app (ex.: outro serviço seu) já rodaram. Use apenas `config()`, `app()` e o que for seguro no momento da requisição.

---

## Exemplo mínimo (estendendo o padrão)

Sobrescrever só a detecção (ex.: domínio customizado ou tabela auxiliar):

```php
<?php

namespace App\Services;

use Callcocam\LaravelRaptor\Services\TenantResolver;
use Illuminate\Http\Request;

class MyTenantResolver extends TenantResolver
{
    protected function detectTenant(Request $request): mixed
    {
        $host = $request->getHost();
        $domain = str($host)->replace('www.', '')->toString();

        // Exemplo: landlord por path em vez de subdomínio
        if ($request->is('admin/*') && $request->user()?->isSuperAdmin()) {
            config(['app.context' => 'landlord']);
            return null;
        }

        $tenantModel = config('raptor.models.tenant');
        $domainColumn = config('raptor.tenant.subdomain_column', 'domain');

        return $tenantModel::where($domainColumn, $domain)
            ->where('status', \Callcocam\LaravelRaptor\Enums\TenantStatus::Published->value)
            ->first();
    }
}
```

Depois, em `config/raptor.php`: `'tenant_resolver' => \App\Services\MyTenantResolver::class`.

---

## Exemplo com domainData (Client/Store)

Quando o domínio está em tabela polimórfica e você quer expor client/store no contexto:

```php
// Após encontrar $tenant e opcionalmente $client ou $store:
$domainData = null;
if ($client) {
    $domainData = (object) [
        'domainable_type' => get_class($client),
        'domainable_id'   => (string) $client->getKey(),
    ];
}
$this->storeTenantContext($tenant, $domainData);
```

Assim `config('app.current_client_id')` (ou `current_store_id`) e `ResolvedTenantConfig` ficam alinhados com a documentação do pacote.

---

## Checklist rápido

- [ ] Implementar `TenantResolverInterface` (ou estender `TenantResolver`).
- [ ] Em `resolve()`: tratar landlord primeiro e retornar `null` sem chamar `storeTenantContext`.
- [ ] Cache por requisição em `resolve()` (evitar múltiplas resoluções).
- [ ] Chamar `storeTenantContext($tenant, $domainData)` apenas quando houver tenant; passar Model com `getKey()` e, se aplicável, `database`.
- [ ] Implementar `configureTenantDatabase` (e chamar `applyConfig`) para jobs/commands que rodam no contexto do tenant.
- [ ] Registrar a classe em `config/raptor.php` em `services.tenant_resolver`.
- [ ] Considerar status do tenant (ex.: Published) e coluna de domínio (ou tabela auxiliar) na sua lógica.

Com isso, o TenantResolver personalizado se integra de forma previsível ao restante do multi-tenancy (middlewares, Landlord, banco por tenant, jobs/commands).
