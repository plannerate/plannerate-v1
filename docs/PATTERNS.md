# Padrões do Sistema — Plannerate

## Integracao Sysmo

Documentacao operacional da integracao (sync inicial, diario, lacunas, paginação por loja e manutencao):
- `docs/integracao-sysmo-sync.md`

## Multitenancy — Validacoes Tenant

Guia para evitar validacoes (`Rule::unique`/`Rule::exists`) consultando a conexao errada em ambiente com tenant dedicado:
- `docs/multitenancy-validacoes-tenant.md`

## ⚠️ Lembrete Crítico: Domínio do Tenant

Ao criar um tenant, o campo **"Domínio primário ativo"** (`domain_is_active` → `tenant_domains.is_active`) **DEVE estar `true`**.

O `DomainTenantWithDomainsFinder` filtra por `where('is_active', true)`. Com o domínio inativo, o tenant não é resolvido pelo subdomínio e toda autenticação/cadastro vai para o banco landlord.

---

## Backend — Modelos Tenant-Scoped

Modelos que pertencem a um tenant usam o trait `BelongsToTenant`:

```php
use App\Models\Traits\BelongsToTenant;

class Category extends Model
{
    use BelongsToTenant, HasFactory, HasSlug, HasUlids, SoftDeletes;
}
```

O trait faz duas coisas automaticamente:
- **Global scope**: filtra todas as queries por `tenant_id = Tenant::current()->id`
- **`creating` event**: auto-preenche `tenant_id` ao criar registros

Modelos com o trait: `Category`, `Product`, `Store`, `Provider`, `Cluster`.

**Escape hatches:**
```php
// Por query:
Category::withoutTenantScope()->get();

// Global (seeders/commands):
TenantScope::disable();
Category::all(); // retorna todos os tenants
TenantScope::enable();
```

---

## Backend — Controllers Tenant

Controllers tenant estendem `Controller` e usam `InteractsWithTenantContext`:

```php
class CategoryController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        // Query sem where('tenant_id') — BelongsToTenant cuida disso
        $categories = Category::query()->latest()->paginate(10);

        return Inertia::render('tenant/categories/Index', [
            'subdomain' => $this->tenantSubdomain(), // sempre passar
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            // NÃO passar tenant_id — o trait seta automaticamente
        ]);

        return to_route('tenant.categories.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Category $category): Response
    {
        unset($subdomain);
        // NÃO chamar ensureTenantOwnership — route model binding já filtra pelo scope
        $this->authorize('update', $category);
        // ...
    }
}
```

**Regras:**
- Nunca `->where('tenant_id', ...)` — o `BelongsToTenant` scope cuida disso
- Nunca `'tenant_id' => $this->tenantId()` no `create()` — o trait auto-preenche
- Nunca `ensureTenantOwnership()` — o scope garante que route model binding retorna 404 para outros tenants
- Sempre passar `'subdomain' => $this->tenantSubdomain()` para as views
- Sempre usar `$this->tenantRouteParameters()` em redirects

**O que `InteractsWithTenantContext` ainda oferece:**
- `tenantSubdomain()` — para views e routes
- `tenantRouteParameters()` — para redirects

**Form Requests** continuam usando `->where('tenant_id', $tenantId)` dentro de `Rule::exists()` / `Rule::unique()` — essas regras operam em SQL direto, não via Eloquent scope.

---

## Frontend — Páginas Index

Toda página de listagem segue este padrão:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ResourceController from '@/actions/App/Http/Controllers/Tenant/ResourceController';
import ListPage from '@/components/ListPage.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import EditButton from '@/components/EditButton.vue';
import DeleteButton from '@/components/DeleteButton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { Paginator } from '@/types';

type ResourceRow = { id: string; name: string; /* ... */ };

const props = defineProps<{
    subdomain: string;
    resources: Paginator<ResourceRow>;
    filters: { search: string };
}>();

const { t } = useT();
const indexPath = ResourceController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: t('app.tenant.resources.title'),
    title: t('app.tenant.resources.title'),
    description: t('app.tenant.resources.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.resources.navigation'), href: indexPath },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <ListPage
        :title="pageMeta.title"
        :description="pageMeta.description"
        :meta="props.resources"
        label="recurso"
        :action="indexPath"
        :clear-href="indexPath"
        :search-value="props.filters.search"
    >
        <template #action>
            <NewActionButton :href="ResourceController.create.url(props.subdomain)">
                {{ t('app.tenant.resources.actions.new') }}
            </NewActionButton>
        </template>

        <template #rows="{ item }">
            <td>{{ item.name }}</td>
            <td class="text-right">
                <EditButton :href="ResourceController.edit.url({ subdomain: props.subdomain, resource: item.id })" />
                <DeleteButton :href="ResourceController.destroy.url({ subdomain: props.subdomain, resource: item.id })" />
            </td>
        </template>
    </ListPage>
</template>
```

---

## Frontend — Páginas Form (Create/Edit)

```vue
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ResourceController from '@/actions/App/Http/Controllers/Tenant/ResourceController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

type ResourcePayload = { id: string; name: string; status: string };

const props = defineProps<{
    subdomain: string;
    resource: ResourcePayload | null;
}>();

const { t } = useT();
const isEdit = computed(() => props.resource !== null);
const indexPath = ResourceController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.tenant.resources.actions.edit') : t('app.tenant.resources.actions.new'),
    title: isEdit.value ? t('app.tenant.resources.actions.edit') : t('app.tenant.resources.actions.new'),
    description: t('app.tenant.resources.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.resources.navigation'), href: indexPath },
        {
            title: isEdit.value ? t('app.tenant.resources.actions.edit') : t('app.tenant.resources.actions.new'),
            href: isEdit.value
                ? ResourceController.edit.url({ subdomain: props.subdomain, resource: props.resource!.id })
                : ResourceController.create.url(props.subdomain),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />

    <div class="p-4">
        <Form
            :action="isEdit
                ? ResourceController.update.form({ subdomain: props.subdomain, resource: props.resource!.id })
                : ResourceController.store.form({ subdomain: props.subdomain })"
            class="space-y-6"
        >
            <FormCard :title="pageMeta.title" :description="pageMeta.description" :back-href="indexPath">
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('app.tenant.resources.fields.name') }}</Label>
                        <Input id="name" name="name" :value="props.resource?.name" />
                        <InputError name="name" />
                    </div>
                </div>
            </FormCard>
        </Form>
    </div>
</template>
```

---

## Frontend — Wayfinder (URLs tipadas)

Sempre usar Wayfinder em vez de URLs hardcoded:

```typescript
// Importação: nome do controller
import CategoryController from '@/actions/App/Http/Controllers/Tenant/CategoryController';

// Navegação
CategoryController.index.url(subdomain)
CategoryController.create.url(subdomain)
CategoryController.edit.url({ subdomain, category: id })

// Formulários (Inertia <Form>)
CategoryController.store.form({ subdomain })
CategoryController.update.form({ subdomain, category: id })
CategoryController.destroy.form({ subdomain, category: id })

// Remover prefixo de domínio para links internos:
CategoryController.index.url(subdomain).replace(/^\/\/[^/]+/, '')
```

Após alterar rotas: `./vendor/bin/sail artisan wayfinder:generate --with-form`

---

## Tokens de Design

| Token | Uso |
|-------|-----|
| `bg-primary` | Ação principal, brand |
| `bg-background` / `text-foreground` | Base da página |
| `bg-card` | Superfícies de card |
| `bg-muted` / `text-muted-foreground` | Texto secundário |
| `bg-destructive` | Ações destrutivas |
| `border-border` | Bordas |

- Sempre incluir variante `dark:`
- Ícones: `lucide-vue-next`
- Espaçamento com `gap-*`
- Border radius: `rounded-[var(--radius)]`
