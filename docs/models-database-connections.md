# Models e Conexões de Banco - Referência Rápida

**Data**: 2026-02-14  
**Baseado em**: `docs/database-architecture.md`

---

## 📊 Classificação de Models

### 🏢 Models de LANDLORD (Banco Principal)

Estes models **sempre** usam a conexão `landlord` e suas migrations estão em `database/migrations/`.

| Model | Trait | Migration | Observações |
|-------|-------|-----------|-------------|
| `User` | `UsesLandlordConnection` | `database/migrations/` | Usuários do sistema |
| `Client` | `UsesLandlordConnection` | `database/migrations/` | Clientes |
| `Store` | `UsesLandlordConnection` | `database/migrations/` | Lojas |
| `Cluster` | `UsesLandlordConnection` | `database/migrations/` | Clusters de lojas |
| `Address` | `UsesLandlordConnection` | `database/migrations/` | Endereços (polimórfico) |
| `ClientIntegration` | `UsesLandlordConnection` | `database/migrations/` | Integrações de clientes |
| `IntegrationSyncLog` | `UsesLandlordConnection` | `database/migrations/` | Logs de sincronização |

**Trait usado**:
```php
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;
```

---

### 🏪 Models de TENANT (Banco do Client/Store)

Estes models usam a conexão `tenant` (dinâmica) e suas migrations estão em `database/migrations/clients/`.

| Model | Trait | Migration | Observações |
|-------|-------|-----------|-------------|
| `Planogram` | `UsesTenantDatabase` | `database/migrations/clients/` | Planogramas |
| `Gondola` | `UsesTenantDatabase` | `database/migrations/clients/` | Gôndolas |
| `Product` | `UsesTenantDatabase` | `database/migrations/clients/` | Produtos |
| `Sale` | `UsesTenantDatabase` | `database/migrations/clients/` | Vendas |
| `MonthlySalesSummary` | `UsesTenantDatabase` | `database/migrations/clients/` | Resumos mensais |
| `Category` | `UsesTenantDatabase` | `database/migrations/clients/` | Categorias |
| `Dimension` | `UsesTenantDatabase` | `database/migrations/clients/` | Dimensões |
| `ProductStore` | `UsesTenantDatabase` | `database/migrations/clients/` | Produtos por loja |
| `ProductAdditionalData` | `UsesTenantDatabase` | `database/migrations/clients/` | Dados adicionais |
| `Provider` | `UsesTenantDatabase` | `database/migrations/clients/` | Fornecedores |
| `FlowConfig`, `FlowConfigStep`, `FlowExecution`, `FlowStepTemplate`, `FlowHistory` | Pacote laravel-raptor-flow (conexão flow = tenant) | pacote | Workflow atual (config + execuções por gôndola) |
| ~~`GondolaWorkflowExecution`~~ | ~~Legado~~ | — | Descontinuado; usar FlowExecution |
| ~~`PlanogramWorkflowConfig`~~ | ~~Legado~~ | — | Descontinuado; usar FlowConfig |
| `WorkflowStepTemplate` | `UsesTenantDatabase` | `database/migrations/clients/` | Templates workflow |

**Trait usado**:
```php
use Callcocam\LaravelRaptor\Traits\UsesTenantDatabase;
```

---

## 🔗 Accessors Cross-Database

Models de tenant que referenciam dados de landlord (Client, Store, Cluster) devem usar **`DB::connection('landlord')`**.

### ✅ Models Corrigidos

Todos os accessors cross-database foram ajustados de:
```php
DB::connection(config('database.default'))  // ❌ ERRADO
```

Para:
```php
DB::connection('landlord')  // ✅ CORRETO
```

### 📝 Lista de Accessors Corrigidos

#### `app/Models/Planogram.php`
- ✅ `getStoreAttribute()` → usa `'landlord'`
- ✅ `getClusterAttribute()` → usa `'landlord'`
- ✅ `getClientAttribute()` → usa `'landlord'`

#### `app/Models/Sale.php`
- ✅ `getClientAttribute()` → usa `'landlord'`
- ✅ `getStoreAttribute()` → usa `'landlord'`

#### `app/Models/MonthlySalesSummary.php`
- ✅ `getStoreAttribute()` → usa `'landlord'`
- ✅ `getClientAttribute()` → usa `'landlord'`

#### `app/Models/Editor/Product.php`
- ✅ `getClientAttribute()` → usa `'landlord'`

#### `app/Models/Editor/Sale.php`
- ✅ `getClientAttribute()` → usa `'landlord'`
- ✅ `getStoreAttribute()` → usa `'landlord'`

#### `app/Models/Editor/MonthlySalesSummary.php`
- ✅ `getStoreAttribute()` → usa `'landlord'`
- ✅ `getClientAttribute()` → usa `'landlord'`

#### `app/Models/Editor/Planogram.php`
- ✅ `getStoreAttribute()` → usa `'landlord'`
- ✅ `getClusterAttribute()` → usa `'landlord'`
- ✅ `getClientAttribute()` → usa `'landlord'`

---

## 🎯 Padrão de Implementação

### Para Models de LANDLORD

```php
namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;

class MyLandlordModel extends AbstractModel
{
    use UsesLandlordConnection;  // ← Adicionar este trait
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        static::$landlord->enable();  // ou disable(), dependendo do escopo
    }
}
```

### Para Models de TENANT

```php
namespace App\Models;

use Callcocam\LaravelRaptor\Models\AbstractModel;
use Callcocam\LaravelRaptor\Traits\UsesTenantDatabase;

class MyTenantModel extends AbstractModel
{
    use UsesTenantDatabase;  // ← Adicionar este trait
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        static::$landlord->enable();
    }
    
    // Accessors cross-database DEVEM usar 'landlord'
    public function getClientAttribute()
    {
        if (!$this->client_id) {
            return null;
        }
        
        return cache()->remember("client:{$this->client_id}", 3600, function () {
            return DB::connection('landlord')  // ← SEMPRE 'landlord'
                ->table('clients')
                ->where('id', $this->client_id)
                ->first();
        });
    }
}
```

---

## 🚨 Erros Comuns

### Erro: "relation does not exist" no banco do tenant

**Causa**: Model de landlord não está usando `UsesLandlordConnection`.

**Solução**:
```php
// Adicionar trait
use Callcocam\LaravelRaptor\Support\Landlord\UsesLandlordConnection;

class MyModel extends AbstractModel
{
    use UsesLandlordConnection;
}
```

### Erro: Accessor retorna null em contexto tenant

**Causa**: Accessor usa `config('database.default')` que aponta para banco do tenant.

**Solução**:
```php
// Trocar de:
DB::connection(config('database.default'))

// Para:
DB::connection('landlord')
```

---

## ✅ Checklist de Verificação

Ao criar ou modificar um model:

### [ ] Classificar o Model

- **Dados do SISTEMA** (User, Client, Store)? → Landlord
- **Dados do TENANT** (Planogram, Gondola, Sale)? → Tenant

### [ ] Adicionar Trait Correto

- **Landlord**: `use UsesLandlordConnection;`
- **Tenant**: `use UsesTenantDatabase;`

### [ ] Verificar Accessors Cross-Database

- Busca dados de outra tabela com `DB::connection()`?
- Usa **`'landlord'`** se buscar Client/Store/Cluster?

### [ ] Verificar Migration

- **Landlord**: `database/migrations/`
- **Tenant**: `database/migrations/clients/`

### [ ] Testar em Contexto Tenant

- Acessa o sistema via domínio de tenant?
- O model busca do banco correto?
- Accessors funcionam corretamente?

---

## 📚 Documentação Relacionada

- **Arquitetura Completa**: `docs/database-architecture.md`
- **Custom Resolver**: `docs/custom-tenant-resolver.md`

---

**Última atualização**: 2026-02-14  
**Status**: ✅ Todos os models ajustados e testados
