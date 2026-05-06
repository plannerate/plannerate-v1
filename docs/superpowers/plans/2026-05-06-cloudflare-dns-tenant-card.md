# Cloudflare DNS Tenant Card — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a DNS status card to the tenant edit form showing the Cloudflare CNAME status with create/delete actions, using Inertia v3 deferred props.

**Architecture:** A new `TenantCloudflareController` handles store/destroy of the CNAME record via the existing `CloudflareService`. The DNS status is passed as a deferred Inertia prop in `TenantController::edit()`, so page load is not blocked. A `CloudflareDnsCard.vue` component renders the status with confirmation dialogs.

**Tech Stack:** PHP 8.5, Laravel 13, Inertia v3, Vue 3, CloudflareService (existing), `Http::fake()` for tests, Pest v4, Wayfinder v0.

---

## File Map

| File | Action |
|------|--------|
| `config/cloudflare.php` | **Create** — exposes Cloudflare env vars |
| `.env.example` | **Modify** — add 4 Cloudflare vars |
| `app/Http/Controllers/Landlord/TenantCloudflareController.php` | **Create** — store + destroy |
| `routes/web.php` | **Modify** — add 2 landlord routes |
| `app/Http/Controllers/Landlord/TenantController.php` | **Modify** — add deferred prop to `edit()` |
| `resources/js/components/CloudflareDnsCard.vue` | **Create** — status card component |
| `resources/js/pages/landlord/tenants/Form.vue` | **Modify** — add prop, import card, render |
| `tests/Feature/Landlord/TenantCloudflareControllerTest.php` | **Create** — feature tests |

---

## Task 1: Config file and env vars

**Files:**
- Create: `config/cloudflare.php`
- Modify: `.env.example`

- [ ] **Step 1: Create `config/cloudflare.php`**

```php
<?php

return [
    'api_token' => env('CLOUDFLARE_API_TOKEN', ''),
    'zone_id' => env('CLOUDFLARE_ZONE_ID', ''),
    'cname_target' => env('CLOUDFLARE_CNAME_TARGET', ''),
    'base_uri' => env('CLOUDFLARE_BASE_URI', 'https://api.cloudflare.com/client/v4'),
    'timeout' => env('CLOUDFLARE_TIMEOUT', 30),
];
```

- [ ] **Step 2: Add vars to `.env.example`**

Find the end of the file and append:

```
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ZONE_ID=
CLOUDFLARE_CNAME_TARGET=
CLOUDFLARE_BASE_URI=https://api.cloudflare.com/client/v4
```

- [ ] **Step 3: Commit**

```bash
git add config/cloudflare.php .env.example
git commit -m "feat: add Cloudflare config file and env vars"
```

---

## Task 2: Feature tests (write first, run to verify they fail)

**Files:**
- Create: `tests/Feature/Landlord/TenantCloudflareControllerTest.php`

- [ ] **Step 1: Create the test file**

```bash
php artisan make:test --pest Landlord/TenantCloudflareControllerTest
```

- [ ] **Step 2: Replace generated content with these tests**

```php
<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());

    config([
        'cloudflare.api_token' => 'test-token-abc',
        'cloudflare.zone_id' => 'zone-abc123',
        'cloudflare.cname_target' => 'app.example.com',
    ]);
});

function tenantWithHost(string $host = 'client.example.com'): Tenant
{
    $tenant = Tenant::factory()->create(['status' => 'active']);

    TenantDomain::query()->create([
        'tenant_id' => $tenant->id,
        'host' => $host,
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

test('store creates CNAME record and redirects back', function (): void {
    $tenant = tenantWithHost('client.example.com');

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'rec123',
                'type' => 'CNAME',
                'name' => 'client.example.com',
                'content' => 'app.example.com',
            ],
        ], 200),
    ]);

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'zones/zone-abc123/dns_records')
        && $request->data()['type'] === 'CNAME'
        && $request->data()['name'] === 'client.example.com'
        && $request->data()['content'] === 'app.example.com'
    );
});

test('store redirects with error when api_token is empty', function (): void {
    config(['cloudflare.api_token' => '']);
    $tenant = tenantWithHost();

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertNothingSent();
});

test('store redirects with error when zone_id is empty', function (): void {
    config(['cloudflare.zone_id' => '']);
    $tenant = tenantWithHost();

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertNothingSent();
});

test('store redirects with error when tenant has no host', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertNothingSent();
});

test('store redirects with error when Cloudflare API returns failure', function (): void {
    $tenant = tenantWithHost();

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => false,
            'errors' => [['message' => 'Invalid record name']],
        ], 400),
    ]);

    $response = $this->post(route('landlord.tenants.cloudflare.store', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
});

test('destroy deletes DNS record when found', function (): void {
    $tenant = tenantWithHost('client.example.com');

    Http::fake([
        'api.cloudflare.com/*/dns_records*' => Http::sequence()
            ->push([
                'success' => true,
                'result' => [[
                    'id' => 'rec123',
                    'type' => 'CNAME',
                    'name' => 'client.example.com',
                    'content' => 'app.example.com',
                ]],
            ], 200)
            ->push([
                'success' => true,
                'result' => ['id' => 'rec123'],
            ], 200),
    ]);

    $response = $this->delete(route('landlord.tenants.cloudflare.destroy', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
});

test('destroy redirects with warning when no record found', function (): void {
    $tenant = tenantWithHost();

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => [],
        ], 200),
    ]);

    $response = $this->delete(route('landlord.tenants.cloudflare.destroy', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertSentCount(1);
});

test('destroy redirects with error when tenant has no host', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'active']);

    $response = $this->delete(route('landlord.tenants.cloudflare.destroy', $tenant));

    $response->assertRedirect(route('landlord.tenants.edit', $tenant));
    Http::assertNothingSent();
});
```

- [ ] **Step 3: Run tests to confirm they fail (routes/controller don't exist yet)**

```bash
php artisan test --compact --filter=TenantCloudflareController
```

Expected: all tests fail with "Route [landlord.tenants.cloudflare.store] not defined" or similar.

---

## Task 3: Create TenantCloudflareController

**Files:**
- Create: `app/Http/Controllers/Landlord/TenantCloudflareController.php`

- [ ] **Step 1: Generate the controller**

```bash
php artisan make:class App/Http/Controllers/Landlord/TenantCloudflareController --no-interaction
```

- [ ] **Step 2: Replace the generated file with this content**

```php
<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Cloudflare\CloudflareService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TenantCloudflareController extends Controller
{
    public function __construct(
        private readonly CloudflareService $cloudflare,
    ) {}

    public function store(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->load('primaryDomain:id,tenant_id,host');
        $host = $tenant->primaryDomain?->host;
        $zoneId = config('cloudflare.zone_id', '');
        $cnameTarget = config('cloudflare.cname_target', '');

        if (! $this->cloudflare->isConfigured() || $zoneId === '') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cloudflare não está configurado.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        if (! $host) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'O tenant não possui um host configurado.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        $result = $this->cloudflare->createRecord($zoneId, [
            'type' => 'CNAME',
            'name' => $host,
            'content' => $cnameTarget,
            'proxied' => true,
        ]);

        if (! ($result['success'] ?? false)) {
            $message = $result['errors'][0]['message'] ?? 'Erro ao criar registro DNS.';
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return to_route('landlord.tenants.edit', $tenant);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro CNAME criado com sucesso.']);

        return to_route('landlord.tenants.edit', $tenant);
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->load('primaryDomain:id,tenant_id,host');
        $host = $tenant->primaryDomain?->host;
        $zoneId = config('cloudflare.zone_id', '');

        if (! $this->cloudflare->isConfigured() || $zoneId === '') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cloudflare não está configurado.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        if (! $host) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'O tenant não possui um host configurado.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        $listResult = $this->cloudflare->listRecords($zoneId, 'CNAME', $host);
        $records = $listResult['result'] ?? [];
        $record = $records[0] ?? null;

        if (! $record) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Nenhum registro DNS encontrado para este host.']);

            return to_route('landlord.tenants.edit', $tenant);
        }

        $deleteResult = $this->cloudflare->deleteRecord($zoneId, $record['id']);

        if (! ($deleteResult['success'] ?? false)) {
            $message = $deleteResult['errors'][0]['message'] ?? 'Erro ao remover registro DNS.';
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return to_route('landlord.tenants.edit', $tenant);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro DNS removido com sucesso.']);

        return to_route('landlord.tenants.edit', $tenant);
    }
}
```

---

## Task 4: Add routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add the 2 Cloudflare routes inside the landlord domain group, after the `provision` route**

Find this line in `routes/web.php`:
```php
    Route::post('tenants/{tenant}/provision', [LandlordTenantController::class, 'provision'])
        ->name('landlord.tenants.provision');
```

Add after it (still inside the landlord domain group):
```php
    Route::post('tenants/{tenant}/cloudflare', [TenantCloudflareController::class, 'store'])
        ->name('landlord.tenants.cloudflare.store');
    Route::delete('tenants/{tenant}/cloudflare', [TenantCloudflareController::class, 'destroy'])
        ->name('landlord.tenants.cloudflare.destroy');
```

- [ ] **Step 2: Add the import at the top of `routes/web.php`**

Find the existing landlord controller imports (around line 10) and add:
```php
use App\Http\Controllers\Landlord\TenantCloudflareController;
```

- [ ] **Step 3: Verify routes registered**

```bash
php artisan route:list --name=landlord.tenants.cloudflare
```

Expected output includes two rows: `POST` and `DELETE` for `tenants/{tenant}/cloudflare`.

- [ ] **Step 4: Run the tests — they should now pass**

```bash
php artisan test --compact --filter=TenantCloudflareController
```

Expected: all 8 tests pass.

- [ ] **Step 5: Run pint on the modified files**

```bash
vendor/bin/pint app/Http/Controllers/Landlord/TenantCloudflareController.php routes/web.php --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Landlord/TenantCloudflareController.php routes/web.php tests/Feature/Landlord/TenantCloudflareControllerTest.php
git commit -m "feat: add TenantCloudflareController with store/destroy routes and tests"
```

---

## Task 5: Add deferred prop to TenantController::edit()

**Files:**
- Modify: `app/Http/Controllers/Landlord/TenantController.php`

- [ ] **Step 1: Add the `CloudflareService` import at the top of the file**

Find the existing imports block and add:
```php
use App\Services\Cloudflare\CloudflareService;
```

- [ ] **Step 2: Add the deferred prop to the `edit()` method**

Find the `edit()` method return statement — it currently ends:
```php
            'statuses' => $this->statusesForSelect(),
        ]);
```

Change it to:
```php
            'statuses' => $this->statusesForSelect(),
            'cloudflare_record' => Inertia::defer(fn (): ?array => $this->resolveCloudflareRecord($tenant)),
        ]);
```

- [ ] **Step 3: Add the private helper method** to `TenantController` (after the `edit()` method):

```php
    private function resolveCloudflareRecord(Tenant $tenant): ?array
    {
        $host = $tenant->primaryDomain?->host;
        $zoneId = config('cloudflare.zone_id', '');

        /** @var CloudflareService $cloudflare */
        $cloudflare = app(CloudflareService::class);

        if (! $cloudflare->isConfigured() || $zoneId === '' || ! $host) {
            return null;
        }

        $result = $cloudflare->listRecords($zoneId, 'CNAME', $host);

        if (! ($result['success'] ?? false)) {
            return null;
        }

        $records = $result['result'] ?? [];
        $record = $records[0] ?? null;

        if (! $record) {
            return ['exists' => false, 'cname_target' => config('cloudflare.cname_target', '')];
        }

        return [
            'exists' => true,
            'id' => $record['id'],
            'name' => $record['name'],
            'content' => $record['content'],
            'cname_target' => config('cloudflare.cname_target', ''),
        ];
    }
```

- [ ] **Step 4: Run pint on the modified file**

```bash
vendor/bin/pint app/Http/Controllers/Landlord/TenantController.php --format agent
```

- [ ] **Step 5: Run the existing tenant CRUD test to verify no regression**

```bash
php artisan test --compact --filter=TenantCrudTest
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Landlord/TenantController.php
git commit -m "feat: add cloudflare_record deferred prop to tenant edit page"
```

---

## Task 6: Generate Wayfinder types

**Files:**
- Auto-generated: `resources/js/actions/App/Http/Controllers/Landlord/TenantCloudflareController.ts`

- [ ] **Step 1: Run wayfinder:generate**

```bash
php artisan wayfinder:generate
```

- [ ] **Step 2: Verify the generated file exists**

```bash
ls resources/js/actions/App/Http/Controllers/Landlord/TenantCloudflareController.ts
```

Expected: file exists with `store` and `destroy` exports.

- [ ] **Step 3: Commit**

```bash
git add resources/js/actions/
git commit -m "chore: regenerate wayfinder types for TenantCloudflareController"
```

---

## Task 7: Create CloudflareDnsCard.vue

**Files:**
- Create: `resources/js/components/CloudflareDnsCard.vue`

- [ ] **Step 1: Create the file with this content**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Cloud, Loader2, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type CloudflareRecordNotFound = { exists: false; cname_target: string };
type CloudflareRecordFound = { exists: true; id: string; name: string; content: string; cname_target: string };
type CloudflareRecord = CloudflareRecordNotFound | CloudflareRecordFound;

const props = defineProps<{
    cloudflareRecord: CloudflareRecord | null | undefined;
    createHref: string;
    deleteHref: string;
    host: string;
}>();

const isLoading = computed(() => props.cloudflareRecord === undefined);
const recordExists = computed(
    () => props.cloudflareRecord !== null && props.cloudflareRecord !== undefined && props.cloudflareRecord.exists === true,
);
const record = computed(() =>
    recordExists.value ? (props.cloudflareRecord as CloudflareRecordFound) : null,
);
const cnameTarget = computed(
    () => (props.cloudflareRecord as CloudflareRecord | null)?.cname_target ?? '',
);

const createOpen = ref(false);
const deleteOpen = ref(false);

const createForm = useForm({});
const deleteForm = useForm({});

function confirmCreate(): void {
    createForm.post(props.createHref, {
        onSuccess: () => {
            createOpen.value = false;
        },
    });
}

function confirmDelete(): void {
    deleteForm.delete(props.deleteHref, {
        onSuccess: () => {
            deleteOpen.value = false;
        },
    });
}
</script>

<template>
    <div v-if="cloudflareRecord !== null" class="rounded-lg border border-border">
        <!-- Loading skeleton -->
        <div v-if="isLoading" class="flex items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="size-4 animate-pulse rounded bg-muted" />
                <div class="h-4 w-28 animate-pulse rounded bg-muted" />
                <div class="h-5 w-24 animate-pulse rounded-full bg-muted" />
            </div>
            <div class="h-8 w-24 animate-pulse rounded bg-muted" />
        </div>

        <!-- Loaded state -->
        <div v-else class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex flex-wrap items-center gap-3">
                <Cloud class="size-4 shrink-0 text-muted-foreground" />
                <span class="text-sm font-medium">DNS Cloudflare</span>

                <span
                    v-if="recordExists"
                    class="inline-flex items-center gap-1 rounded-full border border-green-500/30 bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400"
                >
                    <span class="size-1.5 rounded-full bg-green-500" />
                    DNS Ativo
                </span>
                <span
                    v-else
                    class="inline-flex items-center gap-1 rounded-full border border-yellow-500/30 bg-yellow-500/10 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:text-yellow-400"
                >
                    <span class="size-1.5 rounded-full bg-yellow-500" />
                    Sem registro DNS
                </span>

                <span class="font-mono text-xs text-muted-foreground">
                    <template v-if="record">{{ record.name }} → {{ record.content }}</template>
                    <template v-else>{{ host }} → {{ cnameTarget }}</template>
                </span>
            </div>

            <!-- Create action -->
            <Dialog v-if="!recordExists" v-model:open="createOpen">
                <DialogTrigger as-child>
                    <Button size="sm" variant="outline" class="inline-flex items-center gap-1.5">
                        <Cloud class="size-3.5" />
                        Criar CNAME
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Criar registro CNAME?</DialogTitle>
                        <DialogDescription>
                            O seguinte registro será criado no Cloudflare:
                        </DialogDescription>
                    </DialogHeader>
                    <div class="rounded-md border border-border bg-muted/40 px-4 py-3 font-mono text-sm">
                        {{ host }} → {{ cnameTarget }}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" :disabled="createForm.processing" @click="createOpen = false">
                            Cancelar
                        </Button>
                        <Button :disabled="createForm.processing" @click="confirmCreate">
                            <Loader2 v-if="createForm.processing" class="mr-1.5 size-3.5 animate-spin" />
                            {{ createForm.processing ? 'Criando...' : 'Criar' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete action -->
            <Dialog v-if="recordExists" v-model:open="deleteOpen">
                <DialogTrigger as-child>
                    <Button size="sm" variant="destructive" class="inline-flex items-center gap-1.5">
                        <TriangleAlert class="size-3.5" />
                        Remover
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                                <TriangleAlert class="size-5 text-destructive" />
                            </div>
                            <div>
                                <DialogTitle>Remover registro DNS?</DialogTitle>
                                <DialogDescription class="mt-0.5">
                                    Remove o CNAME <strong>{{ record?.name }}</strong> do Cloudflare. Esta ação não pode ser desfeita.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" :disabled="deleteForm.processing" @click="deleteOpen = false">
                            Cancelar
                        </Button>
                        <Button variant="destructive" :disabled="deleteForm.processing" @click="confirmDelete">
                            <Loader2 v-if="deleteForm.processing" class="mr-1.5 size-3.5 animate-spin" />
                            {{ deleteForm.processing ? 'Removendo...' : 'Remover' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/CloudflareDnsCard.vue
git commit -m "feat: add CloudflareDnsCard component with create/delete DNS actions"
```

---

## Task 8: Update Form.vue

**Files:**
- Modify: `resources/js/pages/landlord/tenants/Form.vue`

- [ ] **Step 1: Add the type definition and prop to the `<script setup>` section**

After the existing type definitions (after `type ModuleOption`), add:

```ts
type CloudflareRecordNotFound = { exists: false; cname_target: string };
type CloudflareRecordFound = { exists: true; id: string; name: string; content: string; cname_target: string };
type CloudflareRecord = CloudflareRecordNotFound | CloudflareRecordFound;
```

Then update `defineProps` to include the new prop:

```ts
const props = defineProps<{
    tenant: TenantPayload | null;
    plans: PlanOption[];
    modules: ModuleOption[];
    statuses: StatusOption[];
    cloudflare_record?: CloudflareRecord | null;
}>();
```

- [ ] **Step 2: Add imports at the top of `<script setup>`**

Add these two imports after the existing import lines:

```ts
import TenantCloudflareController from '@/actions/App/Http/Controllers/Landlord/TenantCloudflareController';
import CloudflareDnsCard from '@/components/CloudflareDnsCard.vue';
```

- [ ] **Step 3: Add computed hrefs for the Cloudflare actions**

After the existing `tenantsIndexPath` line, add:

```ts
const cloudflareCreateHref = computed(() =>
    isEdit.value && props.tenant?.host
        ? TenantCloudflareController.store.url(props.tenant!.id).replace(/^\/\/[^/]+/, '')
        : '',
);

const cloudflareDeleteHref = computed(() =>
    isEdit.value && props.tenant?.host
        ? TenantCloudflareController.destroy.url(props.tenant!.id).replace(/^\/\/[^/]+/, '')
        : '',
);
```

- [ ] **Step 4: Insert `<CloudflareDnsCard>` in the template**

Find the `<!-- Domain -->` comment in the template:

```html
                <!-- Domain -->
                <div class="grid gap-4">
```

Insert the card **before** that comment:

```html
                <!-- Cloudflare DNS -->
                <CloudflareDnsCard
                    v-if="isEdit && props.tenant?.host"
                    :cloudflare-record="props.cloudflare_record"
                    :create-href="cloudflareCreateHref"
                    :delete-href="cloudflareDeleteHref"
                    :host="props.tenant!.host!"
                />

                <!-- Domain -->
                <div class="grid gap-4">
```

- [ ] **Step 5: Run pint on modified PHP files (none changed here, only Vue — skip pint)**

- [ ] **Step 6: Run all landlord tests to verify no regressions**

```bash
php artisan test --compact tests/Feature/Landlord/
```

Expected: all tests pass including the 8 new Cloudflare tests.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/landlord/tenants/Form.vue
git commit -m "feat: integrate CloudflareDnsCard into tenant edit form"
```

---

## Verification

1. Set in `.env`:
   ```
   CLOUDFLARE_API_TOKEN=your-real-token
   CLOUDFLARE_ZONE_ID=your-zone-id
   CLOUDFLARE_CNAME_TARGET=yourapp.com
   ```
2. Open a tenant edit page where `host` is set
3. The DNS card appears with a pulsing skeleton, then resolves to either "DNS Ativo" (green) or "Sem registro DNS" (yellow)
4. Click "Criar CNAME" → dialog shows `host → CLOUDFLARE_CNAME_TARGET` → confirm → card refreshes to green
5. Click "Remover" → dialog warns → confirm → card refreshes to yellow
6. Remove `CLOUDFLARE_API_TOKEN` from `.env` → card disappears
7. Open a tenant with no `host` set → card does not appear

**Run full Cloudflare test suite:**
```bash
php artisan test --compact --filter=TenantCloudflareController
```
