# Image Bank Module — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar o módulo `image-bank`, refatorar o resolver de imagens com cache global via `EanReference`, e reescrever o comando `process-product-images` para iterar múltiplos tenants com fast path por SQL batch.

**Architecture:** O `EanReference` (landlord DB) funciona como cache global de URLs de imagem. O resolver verifica essa tabela primeiro antes de bater em storage remoto ou web. O comando carrega o mapa EAN→URL uma vez e atualiza produtos elegíveis via SQL batch (fast path) ou despacha jobs (slow path) para EANs ainda não resolvidos.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Spatie Multitenancy, DigitalOcean Spaces (disco `do`), `Storage::disk('public')`, `Storage::disk('do')`.

---

## Files Changed

| Ação | Arquivo |
|---|---|
| Modify | `app/Support/Modules/ModuleSlug.php` |
| Modify | `database/seeders/LandlordPlansAndModulesSeeder.php` |
| Modify | `app/Services/ProductRepositoryImageResolver.php` |
| Modify | `app/Console/Commands/ProcessProductImages.php` |
| Modify | `tests/Unit/Services/ProductRepositoryImageResolverTest.php` |
| Create | `tests/Feature/Console/ProcessProductImagesCommandTest.php` |

---

## Task 1: Módulo `image-bank` — constante e seeder

**Files:**
- Modify: `app/Support/Modules/ModuleSlug.php`
- Modify: `database/seeders/LandlordPlansAndModulesSeeder.php`

- [ ] **Step 1.1: Adicionar constante IMAGE_BANK ao ModuleSlug**

Arquivo: `app/Support/Modules/ModuleSlug.php`

```php
<?php

namespace App\Support\Modules;

final class ModuleSlug
{
    public const KANBAN = 'kanban';
    public const IMAGE_BANK = 'image-bank';
}
```

- [ ] **Step 1.2: Adicionar módulo image-bank ao seeder**

Arquivo: `database/seeders/LandlordPlansAndModulesSeeder.php`

Adicionar após o bloco do módulo kanban (antes do `$this->command->info` final):

```php
$imageBank = Module::on('landlord')->firstOrCreate(
    ['slug' => 'image-bank'],
    [
        'name' => 'Banco de Imagens',
        'is_active' => true,
    ]
);

$this->command->info("  Module: {$imageBank->name} (slug={$imageBank->slug}) — disponível para ativação por tenant");
```

> **Nota:** O módulo `image-bank` **não** é atribuído automaticamente a todos os tenants (diferente do kanban). É opt-in — o landlord ativa por tenant via painel.

- [ ] **Step 1.3: Formatar**

```bash
./vendor/bin/sail php vendor/bin/pint app/Support/Modules/ModuleSlug.php database/seeders/LandlordPlansAndModulesSeeder.php
```

- [ ] **Step 1.4: Commit**

```bash
git add app/Support/Modules/ModuleSlug.php database/seeders/LandlordPlansAndModulesSeeder.php
git commit -m "feat: add image-bank module slug and seeder entry"
```

---

## Task 2: Resolver — cache EanReference + prioridade disco público

**Files:**
- Modify: `app/Services/ProductRepositoryImageResolver.php`
- Modify: `tests/Unit/Services/ProductRepositoryImageResolverTest.php`

### 2A — Testes primeiro

- [ ] **Step 2.1: Escrever testes para os novos comportamentos do resolver**

Adicionar ao final de `tests/Unit/Services/ProductRepositoryImageResolverTest.php`:

```php
test('resolver returns image_front_url from EanReference without hitting storage', function (): void {
    Storage::fake('public');
    Storage::fake('do');

    $ean = '7891234500001';
    $cachedPath = "repositorioimagens/frente/{$ean}.webp";

    DB::connection('landlord')->table('ean_references')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'ean' => $ean,
        'image_front_url' => $cachedPath,
        'unit' => 'cm',
        'has_dimensions' => false,
        'dimension_status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new ProductRepositoryImageResolver;
    $result = $service->resolveByEan($ean);

    expect($result)->not()->toBeNull()
        ->and($result['path'])->toBe($cachedPath);

    // Nenhuma chamada ao DO ou web foi feita
    Storage::disk('do')->assertMissing($cachedPath);
});

test('resolver uses public disk before hitting DO storage', function (): void {
    Storage::fake('public');
    Storage::fake('do');

    $ean = '7891234500002';
    $targetPath = "repositorioimagens/frente/{$ean}.webp";

    // Arquivo já existe no público, não existe no DO
    Storage::disk('public')->put($targetPath, 'cached-webp');

    $service = new ProductRepositoryImageResolver;
    $result = $service->resolveByEan($ean);

    expect($result)->not()->toBeNull()
        ->and($result['path'])->toBe($targetPath);

    Storage::disk('do')->assertMissing($targetPath);
});

test('resolver saves image_front_url to EanReference after resolving from DO', function (): void {
    Storage::fake('public');
    Storage::fake('do');

    $ean = '7891234500003';
    $doPath = "repositorioimagens/frente/{$ean}.webp";
    Storage::disk('do')->put($doPath, 'do-webp-content');

    $service = new ProductRepositoryImageResolver;
    $result = $service->resolveByEan($ean);

    expect($result)->not()->toBeNull()
        ->and($result['path'])->toBe($doPath);

    $ref = DB::connection('landlord')
        ->table('ean_references')
        ->where('ean', $ean)
        ->first();

    expect($ref?->image_front_url)->toBe($doPath);
});

test('resolver saves image_front_url to EanReference after resolving from public disk', function (): void {
    Storage::fake('public');
    Storage::fake('do');

    $ean = '7891234500004';
    $targetPath = "repositorioimagens/frente/{$ean}.webp";
    Storage::disk('public')->put($targetPath, 'local-webp');

    $service = new ProductRepositoryImageResolver;
    $service->resolveByEan($ean);

    $ref = DB::connection('landlord')
        ->table('ean_references')
        ->where('ean', $ean)
        ->first();

    expect($ref?->image_front_url)->toBe($targetPath);
});
```

- [ ] **Step 2.2: Rodar testes para confirmar que falham**

```bash
./vendor/bin/sail artisan test --compact --filter="resolver returns image_front_url from EanReference"
```

Esperado: **FAIL** — método não existe ainda.

### 2B — Implementação

- [ ] **Step 2.3: Reescrever `resolveByEan` e adicionar métodos privados**

Substituir o método `resolveByEan` e adicionar dois novos métodos em `app/Services/ProductRepositoryImageResolver.php`:

```php
use App\Models\EanReference;
use Illuminate\Support\Facades\DB;
```

Adicionar esses dois `use` ao bloco de imports existente.

Substituir o método `resolveByEan` completo:

```php
/**
 * @return array{path: string, public_url: string}|null
 */
public function resolveByEan(string $ean, ?float $width = null, ?float $height = null): ?array
{
    $normalizedEan = EanReference::normalizeEan($ean);
    $this->lastResolutionDebug = [
        'ean' => $normalizedEan,
        'repository_attempts' => [],
        'web_attempts' => [],
        'result' => null,
    ];

    if ($normalizedEan === '') {
        $this->lastResolutionDebug['result'] = 'invalid_ean';

        return null;
    }

    // Priority 1: EanReference cache no landlord (zero I/O remoto)
    $cachedPath = $this->resolveFromEanReference($normalizedEan);
    if ($cachedPath !== null) {
        $this->lastResolutionDebug['result'] = 'resolved_from_ean_reference';

        return [
            'path' => $cachedPath,
            'public_url' => Storage::disk('public')->url($cachedPath),
        ];
    }

    $targetPath = sprintf('repositorioimagens/frente/%s.webp', $normalizedEan);

    // Priority 2: Disco público local (arquivo já processado anteriormente)
    if (Storage::disk('public')->exists($targetPath)) {
        $this->lastResolutionDebug['result'] = 'resolved_from_public_disk';
        $this->saveToEanReference($normalizedEan, $targetPath);

        return [
            'path' => $targetPath,
            'public_url' => Storage::disk('public')->url($targetPath),
        ];
    }

    // Priority 3+4: DigitalOcean Spaces (webp → copia; png → converte)
    $processedPath = $this->resolveFromRepository(
        ean: $normalizedEan,
        targetPath: $targetPath,
        width: $width,
        height: $height,
    );

    if ($processedPath !== null) {
        $this->lastResolutionDebug['result'] = 'resolved_from_repository';
        $this->saveToEanReference($normalizedEan, $processedPath);

        return [
            'path' => $processedPath,
            'public_url' => Storage::disk('public')->url($processedPath),
        ];
    }

    // Priority 5: Web (OpenFoodFacts e similares)
    $webFallbackPath = $this->resolveFromWeb(
        ean: $normalizedEan,
        targetPath: $targetPath,
        width: $width,
        height: $height,
    );

    if ($webFallbackPath !== null) {
        $this->lastResolutionDebug['result'] = 'resolved_from_web';
        $this->saveToEanReference($normalizedEan, $webFallbackPath);

        return [
            'path' => $webFallbackPath,
            'public_url' => Storage::disk('public')->url($webFallbackPath),
        ];
    }

    $this->lastResolutionDebug['result'] = 'not_found';
    $this->logMissingImage($normalizedEan);

    return null;
}
```

Adicionar os dois novos métodos privados antes de `logMissingImage`:

```php
private function resolveFromEanReference(string $normalizedEan): ?string
{
    $path = DB::connection('landlord')
        ->table('ean_references')
        ->where('ean', $normalizedEan)
        ->whereNull('deleted_at')
        ->value('image_front_url');

    return is_string($path) && $path !== '' ? $path : null;
}

protected function saveToEanReference(string $normalizedEan, string $path): void
{
    DB::connection('landlord')
        ->table('ean_references')
        ->updateOrInsert(
            ['ean' => $normalizedEan],
            ['image_front_url' => $path, 'updated_at' => now()]
        );
}
```

- [ ] **Step 2.4: Rodar todos os testes do resolver**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Services/ProductRepositoryImageResolverTest.php
```

Esperado: todos passando (incluindo os 5 existentes + 4 novos).

- [ ] **Step 2.5: Formatar**

```bash
./vendor/bin/sail php vendor/bin/pint app/Services/ProductRepositoryImageResolver.php
```

- [ ] **Step 2.6: Commit**

```bash
git add app/Services/ProductRepositoryImageResolver.php tests/Unit/Services/ProductRepositoryImageResolverTest.php
git commit -m "feat: resolver checks EanReference cache and public disk before DO/web, saves result to EanReference"
```

---

## Task 3: Reescrever `ProcessProductImages` — multi-tenant + fast path

**Files:**
- Modify: `app/Console/Commands/ProcessProductImages.php`
- Create: `tests/Feature/Console/ProcessProductImagesCommandTest.php`

### 3A — Testes primeiro

- [ ] **Step 3.1: Criar arquivo de teste**

Criar `tests/Feature/Console/ProcessProductImagesCommandTest.php`:

```php
<?php

use App\Jobs\DOProcessProductImageJob;
use App\Models\EanReference;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('public');
    Storage::fake('do');
});

function makeTenantWithImageBank(): Tenant
{
    $tenant = Tenant::on('landlord')->create([
        'id' => (string) Str::ulid(),
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-'.Str::random(4),
        'status' => 'active',
        'database' => config('database.connections.tenant.database'),
    ]);

    $module = Module::on('landlord')->firstOrCreate(
        ['slug' => ModuleSlug::IMAGE_BANK],
        ['name' => 'Banco de Imagens', 'is_active' => true]
    );

    $tenant->modules()->attach($module->id);

    return $tenant;
}

test('command dispatches job for product without url and unknown EAN', function (): void {
    $tenant = makeTenantWithImageBank();

    $tenant->execute(function () use ($tenant): void {
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'ean' => '7891234599901',
            'url' => null,
        ]);
    });

    $this->artisan('process-product-images', ['--tenant' => [$tenant->id]])
        ->assertSuccessful();

    Queue::assertDispatched(DOProcessProductImageJob::class);
});

test('command does fast path update when EAN is in EanReference', function (): void {
    $tenant = makeTenantWithImageBank();
    $ean = '7891234599902';
    $imagePath = "repositorioimagens/frente/{$ean}.webp";

    DB::connection('landlord')->table('ean_references')->insert([
        'id' => (string) Str::ulid(),
        'ean' => $ean,
        'image_front_url' => $imagePath,
        'unit' => 'cm',
        'has_dimensions' => false,
        'dimension_status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $productId = null;
    $tenant->execute(function () use ($tenant, $ean, &$productId): void {
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'ean' => $ean,
            'url' => null,
        ]);
        $productId = $product->id;
    });

    $this->artisan('process-product-images', ['--tenant' => [$tenant->id]])
        ->assertSuccessful();

    Queue::assertNothingDispatched();

    $tenant->execute(function () use ($productId, $imagePath): void {
        $product = Product::find($productId);
        expect($product->url)->toBe($imagePath);
    });
});

test('command skips product with url pointing to existing public file', function (): void {
    $tenant = makeTenantWithImageBank();
    $ean = '7891234599903';
    $existingPath = "repositorioimagens/frente/{$ean}.webp";

    Storage::disk('public')->put($existingPath, 'exists');

    $tenant->execute(function () use ($tenant, $ean, $existingPath): void {
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'ean' => $ean,
            'url' => $existingPath,
        ]);
    });

    $this->artisan('process-product-images', ['--tenant' => [$tenant->id]])
        ->assertSuccessful();

    Queue::assertNothingDispatched();
});

test('command reprocesses product with url pointing to missing public file', function (): void {
    $tenant = makeTenantWithImageBank();
    $ean = '7891234599904';

    $tenant->execute(function () use ($tenant, $ean): void {
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'ean' => $ean,
            'url' => "repositorioimagens/frente/{$ean}.webp", // url set but file missing
        ]);
    });

    $this->artisan('process-product-images', ['--tenant' => [$tenant->id]])
        ->assertSuccessful();

    Queue::assertDispatched(DOProcessProductImageJob::class);
});

test('command warns and exits when no tenants have image-bank module', function (): void {
    $this->artisan('process-product-images')
        ->assertSuccessful();

    Queue::assertNothingDispatched();
});
```

- [ ] **Step 3.2: Rodar testes para confirmar que falham**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Console/ProcessProductImagesCommandTest.php
```

Esperado: **FAIL** — comando ainda não implementado.

### 3B — Implementação

- [ ] **Step 3.3: Reescrever o comando**

Substituir todo o conteúdo de `app/Console/Commands/ProcessProductImages.php`:

```php
<?php

namespace App\Console\Commands;

use App\Jobs\DOProcessProductImageJob;
use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessProductImages extends Command
{
    protected $signature = 'process-product-images
        {--ean=      : EAN específico para processar}
        {--tenant=*  : ID(s) do tenant (todos com módulo image-bank se omitido)}';

    protected $description = 'Processa imagens de produtos para tenants com o módulo image-bank ativo';

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant com módulo image-bank ativo encontrado.');

            return self::SUCCESS;
        }

        // Carrega mapa global EAN → image_front_url do landlord (uma vez por execução)
        $eanRefMap = $this->loadEanReferenceMap();
        $this->line(sprintf('📋 %d EAN(s) em cache no EanReference.', count($eanRefMap)));

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info("🏢 {$tenant->name}");
            $tenant->execute(fn () => $this->processTenant($tenant, $eanRefMap));
        }

        $this->newLine();
        $this->info('✅ Concluído.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveTenants(): Collection
    {
        $tenantIds = $this->option('tenant');

        if (! empty($tenantIds)) {
            $ids = collect($tenantIds)
                ->flatMap(fn (string $v) => explode(',', $v))
                ->map(fn (string $v) => trim($v))
                ->filter()
                ->values()
                ->toArray();

            return Tenant::query()
                ->whereIn('id', $ids)
                ->get();
        }

        return Tenant::query()
            ->where('status', 'active')
            ->whereHasActiveModule(ModuleSlug::IMAGE_BANK)
            ->get();
    }

    /**
     * Mapa normalizedEan → image_front_url do landlord.
     *
     * @return array<string, string>
     */
    private function loadEanReferenceMap(): array
    {
        return DB::connection('landlord')
            ->table('ean_references')
            ->whereNull('deleted_at')
            ->whereNotNull('image_front_url')
            ->pluck('image_front_url', 'ean')
            ->all();
    }

    /**
     * @param  array<string, string>  $eanRefMap
     */
    private function processTenant(Tenant $tenant, array $eanRefMap): void
    {
        $ean = trim((string) $this->option('ean'));
        $stats = ['total' => 0, 'fast' => 0, 'dispatched' => 0, 'skipped' => 0];

        $query = Product::query()
            ->whereNotNull('ean')
            ->when($ean !== '', fn ($q) => $q->where('ean', $ean))
            ->select(['id', 'ean', 'url']);

        $progressBar = $this->output->createProgressBar($query->count());
        $progressBar->start();

        $query->chunkById(500, function ($products) use ($eanRefMap, &$stats, $progressBar): void {
            // Filtra produtos elegíveis: url null ou arquivo inexistente no disco público
            $eligible = $products->filter(function ($product): bool {
                if ($product->url === null || $product->url === '') {
                    return true;
                }

                return ! Storage::disk('public')->exists($product->url);
            });

            $stats['total'] += $products->count();
            $stats['skipped'] += $products->count() - $eligible->count();

            if ($eligible->isEmpty()) {
                $progressBar->advance($products->count());

                return;
            }

            // Fast path: EAN já está no cache do EanReference → UPDATE em lote
            $fastPath = $eligible->filter(
                fn ($p) => isset($eanRefMap[EanReference::normalizeEan((string) $p->ean)])
            );

            if ($fastPath->isNotEmpty()) {
                $grouped = $fastPath->groupBy(
                    fn ($p) => $eanRefMap[EanReference::normalizeEan((string) $p->ean)]
                );

                foreach ($grouped as $imageUrl => $group) {
                    DB::table('products')
                        ->whereIn('id', $group->pluck('id'))
                        ->update(['url' => $imageUrl, 'updated_at' => now()]);
                }

                $stats['fast'] += $fastPath->count();
            }

            // Slow path: EAN desconhecido → despacha job para resolução
            $slowPath = $eligible->reject(
                fn ($p) => isset($eanRefMap[EanReference::normalizeEan((string) $p->ean)])
            );

            foreach ($slowPath as $product) {
                DOProcessProductImageJob::dispatch($product->id);
                $stats['dispatched']++;
            }

            $progressBar->advance($products->count());
        });

        $progressBar->finish();
        $this->newLine();
        $this->line(sprintf(
            '  Total: %d | Ignorados: %d | Fast path: %d | Jobs: %d',
            $stats['total'],
            $stats['skipped'],
            $stats['fast'],
            $stats['dispatched'],
        ));
    }
}
```

- [ ] **Step 3.4: Rodar os testes do comando**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Console/ProcessProductImagesCommandTest.php
```

Esperado: todos passando.

- [ ] **Step 3.5: Rodar todos os testes do projeto**

```bash
./vendor/bin/sail artisan test --compact
```

Esperado: nenhuma regressão.

- [ ] **Step 3.6: Formatar**

```bash
./vendor/bin/sail php vendor/bin/pint app/Console/Commands/ProcessProductImages.php
```

- [ ] **Step 3.7: Commit**

```bash
git add app/Console/Commands/ProcessProductImages.php tests/Feature/Console/ProcessProductImagesCommandTest.php
git commit -m "feat: rewrite process-product-images with multi-tenant support and EanReference fast path"
```

---

## Verificação end-to-end

```bash
# 1. Ativar módulo image-bank para um tenant de teste
./vendor/bin/sail artisan db:seed --class=LandlordPlansAndModulesSeeder

# 2. Rodar para um tenant específico com EAN conhecido
./vendor/bin/sail artisan process-product-images --tenant=<id> --ean=<ean>

# 3. Verificar que o produto teve url atualizado
./vendor/bin/sail artisan tinker --execute 'echo \App\Models\Product::where("ean","<ean>")->value("url");'

# 4. Verificar que EanReference foi populado
./vendor/bin/sail artisan tinker --execute 'echo \Illuminate\Support\Facades\DB::connection("landlord")->table("ean_references")->where("ean","<ean>")->value("image_front_url");'

# 5. Rodar segunda vez — deve zero jobs e fast path
./vendor/bin/sail artisan process-product-images --tenant=<id> --ean=<ean>
# Output esperado: Fast path: 1 | Jobs: 0
```
