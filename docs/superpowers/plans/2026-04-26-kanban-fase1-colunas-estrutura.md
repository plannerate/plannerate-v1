# Kanban de Workflow — Fase 1: Colunas e Estrutura

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir o kanban de workflow de planogramas com filtros e colunas (etapas), substituindo `kanban/Index.vue` por `planograms/Kanban.vue` componentizado em `resources/js/components/kanban/`.

**Architecture:** `WorkflowKanbanController::index()` é unificado (remove `show()`) para aceitar `?planogram_id=` e `?store_id=` via query params. `ListFiltersBar` é reutilizado como base dos filtros. Componentes `KanbanFilters`, `KanbanBoard` e `KanbanColumn` em `resources/js/components/kanban/`. Cards e interatividade ficam na Fase 2.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3, TypeScript, Tailwind v4, shadcn/ui, Pest 4, Wayfinder

---

## File Map

| Ação | Arquivo |
|---|---|
| Criar | `tests/Feature/Tenant/WorkflowKanbanControllerTest.php` |
| Modificar | `app/Http/Controllers/Tenant/WorkflowKanbanController.php` |
| Modificar | `app/Http/Controllers/Tenant/PlanogramController.php` |
| Modificar | `routes/web.php` |
| Modificar | `resources/js/components/KankanNavigationLinks.vue` |
| Remover | `resources/js/pages/tenant/kanban/Index.vue` |
| Criar | `resources/js/components/kanban/types.ts` |
| Criar | `resources/js/composables/useKanban.ts` |
| Criar | `resources/js/components/kanban/KanbanFilters.vue` |
| Criar | `resources/js/components/kanban/KanbanColumn.vue` |
| Criar | `resources/js/components/kanban/KanbanBoard.vue` |
| Modificar | `resources/js/pages/tenant/planograms/Kanban.vue` |

---

## Task 1: Feature tests do WorkflowKanbanController

**Files:**
- Create: `tests/Feature/Tenant/WorkflowKanbanControllerTest.php`

- [ ] **Step 1: Criar arquivo de teste**

```bash
php artisan make:test --pest Tenant/WorkflowKanbanControllerTest
```

- [ ] **Step 2: Escrever os testes**

Substituir o conteúdo de `tests/Feature/Tenant/WorkflowKanbanControllerTest.php`:

```php
<?php

use App\Models\Module;
use App\Models\Planogram;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path'     => 'database/migrations/landlord',
        '--force'    => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--path'  => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('kanban index renderiza componente correto sem planograma selecionado', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-empty');
    $this->actingAs($context['user']);

    $this->withServerVariables(['HTTP_HOST' => $context['host']])
        ->get(route('kanban.index', [], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Kanban')
            ->has('planograms')
            ->has('stores')
            ->has('users')
            ->where('board', null)
            ->where('selected_planogram', null)
        );
});

test('kanban index carrega board quando planogram_id é passado', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-board');
    $this->actingAs($context['user']);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name'      => 'Planograma Teste',
        'slug'      => 'planograma-teste-'.Str::lower(Str::random(6)),
        'type'      => 'planograma',
        'status'    => 'draft',
    ]);

    $this->withServerVariables(['HTTP_HOST' => $context['host']])
        ->get(route('kanban.index', ['planogram_id' => $planogram->id], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Kanban')
            ->where('selected_planogram.id', $planogram->id)
            ->has('board')
        );
});

test('kanban index filtra planogramas por store_id', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-store');
    $this->actingAs($context['user']);

    $store = Store::factory()->create(['tenant_id' => $context['tenant']->id]);

    $planogramNaLoja = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id'  => $store->id,
    ]);

    Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id'  => null,
    ]);

    $this->withServerVariables(['HTTP_HOST' => $context['host']])
        ->get(route('kanban.index', ['store_id' => $store->id], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('planograms', fn ($planograms) =>
                count($planograms) === 1 && $planograms[0]['id'] === $planogramNaLoja->id
            )
        );
});

test('rota planograms.kanban redireciona para kanban.index', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-redirect');
    $this->actingAs($context['user']);

    $this->withServerVariables(['HTTP_HOST' => $context['host']])
        ->get(route('planograms.kanban', [], false))
        ->assertRedirect(route('kanban.index', [], false));
});

function setupKanbanTenantCtx(string $subdomain): array
{
    $user = User::factory()->create();

    $tenant = Tenant::query()->create([
        'name'     => strtoupper($subdomain),
        'slug'     => $subdomain,
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status'   => 'active',
    ]);

    $tenant->domains()->create([
        'host'       => $subdomain.'.'.config('app.landlord_domain'),
        'type'       => 'subdomain',
        'is_primary' => true,
        'is_active'  => true,
    ]);

    $kanban = Module::query()->create([
        'name'      => 'Kanban',
        'slug'      => ModuleSlug::KANBAN,
        'is_active' => true,
    ]);

    $tenant->modules()->attach($kanban->id);

    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return [
        'subdomain' => $subdomain,
        'host'      => $subdomain.'.'.config('app.landlord_domain'),
        'tenant'    => $tenant,
        'user'      => $user,
    ];
}
```

- [ ] **Step 3: Confirmar que os testes falham (implementação ainda não existe)**

```bash
php artisan test --compact --filter=WorkflowKanbanControllerTest
```

Expected: falham porque `tenant/planograms/Kanban` não retorna os props corretos ainda.

---

## Task 2: Reescrever WorkflowKanbanController

**Files:**
- Modify: `app/Http/Controllers/Tenant/WorkflowKanbanController.php`

- [ ] **Step 1: Substituir o conteúdo do controller**

```php
<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Planogram;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Services\WorkflowKanbanService;
use App\Services\WorkflowPlanogramStepService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowKanbanController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(
        private readonly WorkflowKanbanService $kanbanService,
        private readonly WorkflowPlanogramStepService $stepService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkflowGondolaExecution::class);

        $planograms = Planogram::query()
            ->with('store:id,name')
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->store_id))
            ->orderBy('name')
            ->get(['id', 'name', 'store_id'])
            ->map(fn (Planogram $p): array => [
                'id'       => $p->id,
                'name'     => $p->name,
                'store'    => $p->store?->name,
                'store_id' => $p->store_id,
            ])
            ->all();

        $stores = Store::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        $filters = $request->only(['planogram_id', 'store_id', 'gondola_search']);

        $selectedPlanogram = null;
        $board = null;

        if ($request->filled('planogram_id')) {
            $planogram = Planogram::query()->find($request->input('planogram_id'));

            if ($planogram !== null) {
                $this->stepService->syncForPlanogram($planogram);
                $board = $this->kanbanService->buildBoardForPlanogram($planogram);
                $selectedPlanogram = [
                    'id'    => $planogram->id,
                    'name'  => $planogram->name,
                    'store' => $planogram->store?->name,
                ];
            }
        }

        return Inertia::render('tenant/planograms/Kanban', [
            'subdomain'          => $this->tenantSubdomain(),
            'planograms'         => $planograms,
            'stores'             => $stores,
            'users'              => $users,
            'filters'            => $filters,
            'board'              => $board,
            'selected_planogram' => $selectedPlanogram,
            'can_initiate'       => $request->user()?->can('start', WorkflowGondolaExecution::class) ?? false,
        ]);
    }
}
```

---

## Task 3: Atualizar rotas e PlanogramController

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Tenant/PlanogramController.php`

- [ ] **Step 1: Remover a rota `kanban.show` em `routes/web.php`**

Localizar e remover a linha:
```php
Route::get('kanban/{planogram}', [WorkflowKanbanController::class, 'show'])->name('kanban.show');
```

- [ ] **Step 2: Atualizar `PlanogramController::kanban()`**

Localizar em `app/Http/Controllers/Tenant/PlanogramController.php`:

```php
public function kanban(): Response
{
    $this->authorize('viewAny', Planogram::class);

    return Inertia::render('tenant/planograms/Kanban', [
        'subdomain' => $this->tenantSubdomain(),
    ]);
}
```

Substituir por (o import `RedirectResponse` já existe no arquivo):

```php
public function kanban(): RedirectResponse
{
    return redirect()->route('kanban.index');
}
```

- [ ] **Step 3: Regenerar arquivos Wayfinder**

```bash
php artisan wayfinder:generate
```

Expected: `resources/js/actions/App/Http/Controllers/Tenant/WorkflowKanbanController.ts` atualizado — sem método `show`.

---

## Task 4: Remover kanban/Index.vue (evita erros TypeScript)

**Files:**
- Delete: `resources/js/pages/tenant/kanban/Index.vue`

- [ ] **Step 1: Remover o arquivo e diretório**

```bash
git rm resources/js/pages/tenant/kanban/Index.vue
rmdir resources/js/pages/tenant/kanban/
```

---

## Task 5: Pint + testes backend + commit

- [ ] **Step 1: Rodar pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Rodar os testes**

```bash
php artisan test --compact --filter=WorkflowKanbanControllerTest
```

Expected: 4 testes passam.

- [ ] **Step 3: Commit do backend**

```bash
git add app/Http/Controllers/Tenant/WorkflowKanbanController.php \
        app/Http/Controllers/Tenant/PlanogramController.php \
        routes/web.php \
        resources/js/actions/ \
        tests/Feature/Tenant/WorkflowKanbanControllerTest.php
git commit -m "feat: unify kanban controller to single index action with query params"
```

---

## Task 6: Criar types.ts

**Files:**
- Create: `resources/js/components/kanban/types.ts`

- [ ] **Step 1: Criar o arquivo**

```typescript
export type AssignedUser = {
    id: string
    name: string
}

export type Execution = {
    id: string
    gondola_id: string
    gondola_name: string | null
    gondola_location: string | null
    planogram_name: string | null
    step_name: string | null
    status: 'pending' | 'active' | 'paused' | 'completed' | 'cancelled'
    assigned_to_user: AssignedUser | null
    started_at: string | null
    sla_date: string | null
}

export type BoardStep = {
    id: string
    name: string
    description: string | null
    color: string | null
    icon: string | null
    suggested_order: number
    is_required: boolean
    status: string
}

export type BoardColumn = {
    step: BoardStep
    executions: Execution[]
}

export type ExecutionDetails = {
    execution: {
        id: string
        status: Execution['status']
        gondola: { id: string; name: string | null; location: string | null } | null
        step: { id: string; name: string; description: string | null } | null
        assigned_to_user: AssignedUser | null
        started_at: string | null
        sla_date: string | null
    }
    allowed_users: AssignedUser[]
}

export type KanbanPageProps = {
    subdomain: string
    planograms: Array<{ id: string; name: string; store: string | null; store_id: string | null }>
    stores: Array<{ id: string; name: string }>
    users: Array<{ id: string; name: string }>
    filters: { planogram_id?: string; store_id?: string; gondola_search?: string }
    board: BoardColumn[] | null
    selected_planogram: { id: string; name: string; store: string | null } | null
    can_initiate: boolean
}
```

---

## Task 7: Criar useKanban.ts composable

**Files:**
- Create: `resources/js/composables/useKanban.ts`

- [ ] **Step 1: Criar o arquivo**

```typescript
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue';
import type { BoardColumn, Execution } from '@/components/kanban/types';

export function useKanban(board: MaybeRefOrGetter<BoardColumn[] | null>) {
    const onlyOverdue = ref(false);
    const showCompleted = ref(true);

    const filteredBoard = computed((): BoardColumn[] => {
        const columns = toValue(board);
        if (!columns) { return []; }

        return columns.map((column) => ({
            ...column,
            executions: column.executions.filter((exec) => {
                if (!showCompleted.value && exec.status === 'completed') { return false; }
                if (onlyOverdue.value && !isOverdue(exec)) { return false; }

                return true;
            }),
        }));
    });

    function isOverdue(exec: Execution): boolean {
        if (!exec.sla_date) { return false; }

        return new Date(exec.sla_date) < new Date();
    }

    function formatDate(iso: string | null): string {
        if (!iso) { return '—'; }

        return new Date(iso).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
        });
    }

    const statusColors: Record<string, string> = {
        pending: 'bg-muted text-muted-foreground',
        active: 'bg-primary/15 text-primary',
        paused: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        completed: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        cancelled: 'bg-destructive/15 text-destructive',
    };

    const statusLabels: Record<string, string> = {
        pending: 'Pendente',
        active: 'Em andamento',
        paused: 'Pausado',
        completed: 'Concluído',
        cancelled: 'Cancelado',
    };

    function statusLabel(status: string): string {
        return statusLabels[status] ?? status;
    }

    return {
        onlyOverdue,
        showCompleted,
        filteredBoard,
        isOverdue,
        formatDate,
        statusColors,
        statusLabel,
    };
}
```

---

## Task 8: Criar KanbanFilters.vue

**Files:**
- Create: `resources/js/components/kanban/KanbanFilters.vue`

- [ ] **Step 1: Criar o componente**

```vue
<script setup lang="ts">
import { computed } from 'vue';
import ListFiltersBar from '@/components/ListFiltersBar.vue';
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';

const props = defineProps<{
    subdomain: string
    planograms: Array<{ id: string; name: string; store: string | null; store_id: string | null }>
    stores: Array<{ id: string; name: string }>
    filters: { planogram_id?: string; store_id?: string; gondola_search?: string }
    onlyOverdue: boolean
    showCompleted: boolean
}>();

const emit = defineEmits<{
    'update:onlyOverdue': [value: boolean]
    'update:showCompleted': [value: boolean]
}>();

const kanbanUrl = computed(() =>
    WorkflowKanbanController.index.url(props.subdomain).replace(/^\/\/[^/]+/, ''),
);

const filteredPlanograms = computed(() => {
    if (!props.filters.store_id) { return props.planograms; }

    return props.planograms.filter((p) => p.store_id === props.filters.store_id);
});
</script>

<template>
    <ListFiltersBar
        :action="kanbanUrl"
        :clear-href="kanbanUrl"
        :search-value="filters.gondola_search ?? ''"
        search-name="gondola_search"
        search-placeholder="Buscar gôndola..."
        filter-label="Filtrar"
        clear-label="Limpar"
    >
        <!-- Loja -->
        <div class="flex flex-col gap-1">
            <label for="kanban-store" class="text-xs font-medium text-foreground">Loja</label>
            <select
                id="kanban-store"
                name="store_id"
                :value="filters.store_id ?? ''"
                class="h-9 min-w-36 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">Todas</option>
                <option v-for="store in stores" :key="store.id" :value="store.id">
                    {{ store.name }}
                </option>
            </select>
        </div>

        <!-- Planograma -->
        <div class="flex flex-col gap-1">
            <label for="kanban-planogram" class="text-xs font-medium text-foreground">Planograma</label>
            <select
                id="kanban-planogram"
                name="planogram_id"
                :value="filters.planogram_id ?? ''"
                class="h-9 min-w-56 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option value="">Selecione um planograma</option>
                <option v-for="p in filteredPlanograms" :key="p.id" :value="p.id">
                    {{ p.name }}{{ p.store ? ` — ${p.store}` : '' }}
                </option>
            </select>
        </div>

        <!-- Checkboxes client-side (sem name, não submetem com o form) -->
        <div class="flex flex-col justify-end gap-1.5 pb-0.5">
            <label class="flex cursor-pointer items-center gap-2 text-xs text-foreground">
                <input
                    type="checkbox"
                    :checked="onlyOverdue"
                    class="h-4 w-4 rounded border-input"
                    @change="emit('update:onlyOverdue', ($event.target as HTMLInputElement).checked)"
                />
                Apenas atrasadas
            </label>
            <label class="flex cursor-pointer items-center gap-2 text-xs text-foreground">
                <input
                    type="checkbox"
                    :checked="showCompleted"
                    class="h-4 w-4 rounded border-input"
                    @change="emit('update:showCompleted', ($event.target as HTMLInputElement).checked)"
                />
                Mostrar concluídas
            </label>
        </div>
    </ListFiltersBar>
</template>
```

---

## Task 9: Criar KanbanColumn.vue

**Files:**
- Create: `resources/js/components/kanban/KanbanColumn.vue`

- [ ] **Step 1: Criar o componente**

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Kanban } from 'lucide-vue-next';
import type { BoardColumn } from '@/components/kanban/types';

const props = defineProps<{
    column: BoardColumn
}>();

const columnSearch = ref('');

const visibleExecutions = computed(() => {
    const search = columnSearch.value.toLowerCase().trim();
    if (!search) { return props.column.executions; }

    return props.column.executions.filter(
        (e) => (e.gondola_name ?? '').toLowerCase().includes(search),
    );
});

const topColor = computed(() => props.column.step.color ?? '#64748b');
</script>

<template>
    <div
        class="flex h-full w-72 shrink-0 flex-col rounded-lg border bg-card transition-all"
        :style="{ borderTopWidth: '3px', borderTopColor: topColor }"
    >
        <!-- Cabeçalho -->
        <div class="sticky top-0 z-10 space-y-2 rounded-t-lg border-b bg-card p-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <h3 class="truncate font-semibold text-foreground">
                        {{ column.step.name }}
                    </h3>
                    <p v-if="column.step.description" class="truncate text-xs text-muted-foreground">
                        {{ column.step.description }}
                    </p>
                </div>
                <span class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                    {{ column.executions.length }}
                </span>
            </div>

            <input
                v-model="columnSearch"
                type="text"
                placeholder="Buscar gôndola"
                class="h-8 w-full rounded-md border border-input bg-background px-3 text-xs text-foreground placeholder:text-muted-foreground outline-none transition focus:border-primary/60 focus:ring-1 focus:ring-primary/20"
            />
        </div>

        <!-- Lista -->
        <div class="flex-1 space-y-2 overflow-y-auto p-2">
            <template v-if="visibleExecutions.length > 0">
                <div
                    v-for="exec in visibleExecutions"
                    :key="exec.id"
                    class="rounded-lg border border-border bg-background p-3 text-sm shadow-sm"
                >
                    <p class="truncate font-medium text-foreground">
                        {{ exec.gondola_name ?? '—' }}
                    </p>
                    <p v-if="exec.gondola_location" class="truncate text-xs text-muted-foreground">
                        {{ exec.gondola_location }}
                    </p>
                </div>
            </template>

            <div
                v-else
                class="flex h-24 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground"
            >
                <Kanban class="size-5 opacity-30" />
                <span>Nenhuma gôndola encontrada</span>
            </div>
        </div>
    </div>
</template>
```

---

## Task 10: Criar KanbanBoard.vue

**Files:**
- Create: `resources/js/components/kanban/KanbanBoard.vue`

- [ ] **Step 1: Criar o componente**

```vue
<script setup lang="ts">
import KanbanColumn from '@/components/kanban/KanbanColumn.vue';
import type { BoardColumn } from '@/components/kanban/types';

defineProps<{
    board: BoardColumn[]
}>();
</script>

<template>
    <div class="flex-1 overflow-x-auto overflow-y-hidden">
        <div class="flex h-full gap-3 px-4 py-3" style="min-width: max-content">
            <KanbanColumn
                v-for="column in board"
                :key="column.step.id"
                :column="column"
            />
        </div>
    </div>
</template>
```

---

## Task 11: Reescrever Kanban.vue page

**Files:**
- Modify: `resources/js/pages/tenant/planograms/Kanban.vue`

- [ ] **Step 1: Substituir o conteúdo completo**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Kanban } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import KanbanFilters from '@/components/kanban/KanbanFilters.vue';
import KanbanBoard from '@/components/kanban/KanbanBoard.vue';
import KankanNavigationLinks from '@/components/KankanNavigationLinks.vue';
import { useKanban } from '@/composables/useKanban';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import type { KanbanPageProps } from '@/components/kanban/types';

const props = defineProps<KanbanPageProps>();

const pageMeta = useCrudPageMeta({
    headTitle: 'Kanban',
    title: 'Kanban',
    description: 'Gerencie o fluxo de trabalho dos planogramas',
    breadcrumbs: [
        { title: 'Dashboard', href: '/' },
        { title: 'Kanban', href: '#' },
    ],
});

const { onlyOverdue, showCompleted, filteredBoard } = useKanban(() => props.board);
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head title="Kanban" />

        <template #header-actions>
            <KankanNavigationLinks :subdomain="props.subdomain" />
        </template>

        <div class="flex h-full flex-col">
            <div class="border-b border-border bg-background px-4 py-3">
                <KanbanFilters
                    :subdomain="props.subdomain"
                    :planograms="props.planograms"
                    :stores="props.stores"
                    :filters="props.filters"
                    :only-overdue="onlyOverdue"
                    :show-completed="showCompleted"
                    @update:only-overdue="onlyOverdue.value = $event"
                    @update:show-completed="showCompleted.value = $event"
                />
            </div>

            <!-- Sem planograma selecionado -->
            <div
                v-if="!props.selected_planogram"
                class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground"
            >
                <Kanban class="size-10 opacity-20" />
                <p class="text-sm">Selecione um planograma para visualizar o kanban</p>
            </div>

            <!-- Planograma sem etapas -->
            <div
                v-else-if="filteredBoard.length === 0"
                class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground"
            >
                <Kanban class="size-10 opacity-20" />
                <p class="text-sm">Nenhuma etapa configurada para este planograma</p>
            </div>

            <!-- Board -->
            <KanbanBoard v-else :board="filteredBoard" />
        </div>
    </AppLayout>
</template>
```

---

## Task 12: Corrigir KankanNavigationLinks.vue

**Files:**
- Modify: `resources/js/components/KankanNavigationLinks.vue`

O link do kanban aponta para `/planograms/kanban` (que redireciona para `/kanban`), mas o estado ativo verifica `/planograms/kanban`. Após o redirect o usuário fica em `/kanban`, então o link nunca aparece como ativo. Corrija os dois computeds:

- [ ] **Step 1: Atualizar `kanbanPath` e `isKanbanActive`**

Localizar em `resources/js/components/KankanNavigationLinks.vue`:

```typescript
import planograms from '@/routes/tenant/planograms';
```

Adicionar o import do WorkflowKanbanController logo abaixo:

```typescript
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';
```

Localizar:
```typescript
const isKanbanActive = computed(() => currentPath.value === '/planograms/kanban');
```
Substituir por:
```typescript
const isKanbanActive = computed(() => currentPath.value.startsWith('/kanban'));
```

Localizar:
```typescript
const kanbanPath = computed(() => planograms.kanban.url(props.subdomain).replace(/^\/\/[^/]+/, ''));
```
Substituir por:
```typescript
const kanbanPath = computed(() =>
    WorkflowKanbanController.index.url(props.subdomain).replace(/^\/\/[^/]+/, ''),
);
```

---

## Task 13: Commit final

- [ ] **Step 1: Rodar pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Verificar que os testes passam**

```bash
php artisan test --compact --filter=WorkflowKanbanControllerTest
```

Expected: 4 testes passam.

- [ ] **Step 3: Commit dos componentes frontend**

```bash
git add resources/js/components/kanban/ \
        resources/js/composables/useKanban.ts \
        resources/js/pages/tenant/planograms/Kanban.vue \
        resources/js/components/KankanNavigationLinks.vue
git commit -m "feat: kanban Phase 1 - columns, filters and board structure"
```
