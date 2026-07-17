<?php

use App\Models\Category;
use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantDimensionShareToken;
use App\Models\User;
use App\Services\DimensionShare\IssueDimensionShareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

beforeEach(function (): void {
    $tenantPath = database_path('testing_dimension_share.sqlite');

    if (file_exists($tenantPath)) {
        unlink($tenantPath);
    }

    touch($tenantPath);

    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => $tenantPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    DB::purge('tenant');

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--realpath' => false,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

afterEach(function (): void {
    CurrentTenantModel::forgetCurrent();
});

test('issuing a share link stores only the hashed code and a 7 day expiry', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    ['token' => $token, 'shareUrl' => $shareUrl] = dimensionShareIssue($tenant, null, $issuer);

    $plainCode = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    expect($shareUrl)->toContain('/dimensoes/'.$plainCode);
    expect($token->code_hash)->toBe(hash('sha256', $plainCode));
    expect($token->code_hash)->not()->toBe($plainCode);
    expect($token->isActive())->toBeTrue();
    expect($token->expires_at->diffInDays(now()))->toBeLessThanOrEqual(7);

    $this->assertDatabaseHas('tenant_dimension_share_tokens', [
        'tenant_id' => $tenant->id,
        'status' => 'active',
    ], 'landlord');

    $this->assertDatabaseMissing('tenant_dimension_share_tokens', [
        'code_hash' => $plainCode,
    ], 'landlord');
});

test('public page lists only products missing dimensions', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    ['shareUrl' => $shareUrl] = dimensionShareIssue($tenant, null, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    $tenant->makeCurrent();
    $missing = dimensionShareProduct('Produto Sem Medida', null);
    dimensionShareProduct('Produto Com Medida', null, ['width' => 10, 'height' => 20, 'depth' => 5]);
    dimensionShareProduct('Produto Rascunho', null, [], 'draft');
    CurrentTenantModel::forgetCurrent();

    $tenant->makeCurrent();
    $response = $this->get(dimensionShareUrl($tenant, "dimensoes/{$code}"));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/DimensionCorrection')
        ->where('totalRemaining', 1)
        ->has('products', 1)
        ->where('products.0.id', $missing->id)
    );
});

test('public page respects the category scope of the token', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    $tenant->makeCurrent();
    $category = Category::query()->create(['name' => 'Bebidas']);
    $inScope = dimensionShareProduct('Refrigerante', $category->id);
    dimensionShareProduct('Fora do Escopo', null);
    CurrentTenantModel::forgetCurrent();

    ['shareUrl' => $shareUrl] = dimensionShareIssue($tenant, $category->id, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    $tenant->makeCurrent();
    $response = $this->get(dimensionShareUrl($tenant, "dimensoes/{$code}"));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('products', 1)
        ->where('products.0.id', $inScope->id)
    );
});

test('saving dimensions through the public link updates the product and removes it from the list', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    ['shareUrl' => $shareUrl] = dimensionShareIssue($tenant, null, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    $tenant->makeCurrent();
    $product = dimensionShareProduct('Produto Sem Medida', null);
    CurrentTenantModel::forgetCurrent();

    $tenant->makeCurrent();
    $this->patch(dimensionShareUrl($tenant, "dimensoes/{$code}/produtos/{$product->id}"), [
        'height' => 12.5,
        'width' => 8,
        'depth' => 4,
    ])->assertOk()->assertJson(['ok' => true]);
    CurrentTenantModel::forgetCurrent();

    $tenant->makeCurrent();
    $fresh = Product::query()->findOrFail($product->id);
    expect((float) $fresh->height)->toBe(12.5);
    expect((float) $fresh->width)->toBe(8.0);
    expect((bool) $fresh->has_dimensions)->toBeTrue();
    expect($fresh->dimension_publish_status)->toBe('published');

    $listing = $this->get(dimensionShareUrl($tenant, "dimensoes/{$code}"));
    $listing->assertInertia(fn (Assert $page) => $page->where('totalRemaining', 0)->has('products', 0));
});

test('saving through the public link creates an EAN reference when none exists', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    ['shareUrl' => $shareUrl] = dimensionShareIssue($tenant, null, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    $tenant->makeCurrent();
    $product = dimensionShareProduct('Produto Sem Medida', null);
    $ean = (string) $product->ean;
    CurrentTenantModel::forgetCurrent();

    $tenant->makeCurrent();
    $this->patch(dimensionShareUrl($tenant, "dimensoes/{$code}/produtos/{$product->id}"), [
        'height' => 10,
        'width' => 6,
        'depth' => 4,
    ])->assertOk();
    CurrentTenantModel::forgetCurrent();

    $reference = EanReference::query()->forNormalizedEan($ean)->first();

    expect($reference)->not()->toBeNull();
    expect((float) $reference->width)->toBe(6.0);
    expect((float) $reference->height)->toBe(10.0);
    expect((bool) $reference->has_dimensions)->toBeTrue();
});

test('saving fills an existing EAN reference that has no dimensions', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    ['shareUrl' => $shareUrl] = dimensionShareIssue($tenant, null, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    $tenant->makeCurrent();
    $product = dimensionShareProduct('Produto Sem Medida', null);
    $ean = (string) $product->ean;
    CurrentTenantModel::forgetCurrent();

    EanReference::query()->create(['ean' => EanReference::normalizeEan($ean)]);

    $tenant->makeCurrent();
    $this->patch(dimensionShareUrl($tenant, "dimensoes/{$code}/produtos/{$product->id}"), [
        'height' => 15,
        'width' => 7,
        'depth' => 3,
    ])->assertOk();
    CurrentTenantModel::forgetCurrent();

    $reference = EanReference::query()->forNormalizedEan($ean)->first();

    expect((float) $reference->width)->toBe(7.0);
    expect((bool) $reference->has_dimensions)->toBeTrue();
});

test('saving does not overwrite an EAN reference that already has dimensions', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    ['shareUrl' => $shareUrl] = dimensionShareIssue($tenant, null, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    $tenant->makeCurrent();
    $product = dimensionShareProduct('Produto Sem Medida', null);
    $ean = (string) $product->ean;
    CurrentTenantModel::forgetCurrent();

    EanReference::query()->create([
        'ean' => EanReference::normalizeEan($ean),
        'width' => 1,
        'height' => 2,
        'depth' => 3,
        'has_dimensions' => true,
    ]);

    $tenant->makeCurrent();
    $this->patch(dimensionShareUrl($tenant, "dimensoes/{$code}/produtos/{$product->id}"), [
        'height' => 99,
        'width' => 99,
        'depth' => 99,
    ])->assertOk();
    CurrentTenantModel::forgetCurrent();

    $reference = EanReference::query()->forNormalizedEan($ean)->first();

    expect((float) $reference->width)->toBe(1.0);
    expect((float) $reference->height)->toBe(2.0);
    expect((float) $reference->depth)->toBe(3.0);
});

test('an invalid code is forbidden', function () {
    $tenant = dimensionShareTenant();

    $tenant->makeCurrent();
    $this->get(dimensionShareUrl($tenant, 'dimensoes/codigo-invalido-qualquer'))->assertForbidden();
});

test('an expired link returns gone', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    ['token' => $token, 'shareUrl' => $shareUrl] = dimensionShareIssue($tenant, null, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    $token->update(['expires_at' => now()->subDay()]);

    $tenant->makeCurrent();
    $this->get(dimensionShareUrl($tenant, "dimensoes/{$code}"))->assertStatus(410);
});

test('a revoked link returns gone', function () {
    $tenant = dimensionShareTenant();
    $issuer = User::factory()->create();

    ['token' => $token, 'shareUrl' => $shareUrl] = dimensionShareIssue($tenant, null, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    $token->revoke();

    $tenant->makeCurrent();
    $this->get(dimensionShareUrl($tenant, "dimensoes/{$code}"))->assertStatus(410);
});

test('a code cannot be used on a different tenant host', function () {
    $tenantA = dimensionShareTenant();
    $issuer = User::factory()->create();

    // Tenant B usa um banco próprio (cópia do schema migrado) — a coluna
    // tenants.database é única, então não pode reaproveitar o banco do tenant A.
    $databaseB = database_path('testing_dimension_share_b.sqlite');
    copy((string) config('database.connections.tenant.database'), $databaseB);
    $tenantB = dimensionShareTenant($databaseB);

    ['shareUrl' => $shareUrl] = dimensionShareIssue($tenantA, null, $issuer);
    $code = basename((string) parse_url($shareUrl, PHP_URL_PATH));

    // Mesmo código, mas no host do tenant B → o token pertence ao tenant A.
    $tenantB->makeCurrent();
    $this->get(dimensionShareUrl($tenantB, "dimensoes/{$code}"))->assertForbidden();
});

function dimensionShareTenant(?string $databasePath = null): Tenant
{
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Dimension Share',
        'slug' => 'tenant-dimension-share-'.fake()->unique()->numberBetween(1000, 999999),
        'database' => $databasePath ?? (string) config('database.connections.tenant.database'),
        'status' => 'active',
        'plan_id' => null,
    ]));

    $tenant->domains()->create([
        'host' => 'dimension-share-'.fake()->unique()->numberBetween(1000, 999999).'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant->fresh('primaryDomain');
}

/**
 * @return array{token: TenantDimensionShareToken, shareUrl: string}
 */
function dimensionShareIssue(Tenant $tenant, ?string $categoryId, User $issuer): array
{
    $request = Request::create('http://'.$tenant->primaryDomain->host.'/dimensions');

    return app(IssueDimensionShareService::class)->issue($tenant, $categoryId, null, $issuer, $request);
}

/**
 * @param  array<string, mixed>  $dimensions
 */
function dimensionShareProduct(string $name, ?string $categoryId, array $dimensions = [], string $status = 'published'): Product
{
    return Product::query()->create(array_merge([
        'name' => $name,
        'ean' => fake()->unique()->ean13(),
        'status' => $status,
        'category_id' => $categoryId,
    ], $dimensions));
}

function dimensionShareUrl(Tenant $tenant, string $path): string
{
    return sprintf('http://%s/%s', $tenant->primaryDomain->host, ltrim($path, '/'));
}
