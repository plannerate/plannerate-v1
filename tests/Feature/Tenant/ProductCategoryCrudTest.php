<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant admin can execute category crud in tenant context', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-crud-a');
    assignTenantAdminRole($user, $tenant->id);

    $host = 'tenant-crud-a.'.config('app.landlord_domain');

    $createResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.categories.store', ['subdomain' => 'tenant-crud-a'], false), [
            'name' => 'Categoria Principal',
            'slug' => 'categoria-principal',
            'status' => 'draft',
            'is_placeholder' => '0',
        ]);

    $createResponse->assertRedirect(route('tenant.categories.index', ['subdomain' => 'tenant-crud-a'], false));

    $category = Category::query()->where('tenant_id', $tenant->id)->where('slug', 'categoria-principal')->firstOrFail();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'tenant_id' => $tenant->id,
        'name' => 'Categoria Principal',
    ]);

    $updateResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->put(route('tenant.categories.update', ['subdomain' => 'tenant-crud-a', 'category' => $category->id], false), [
            'name' => 'Categoria Atualizada',
            'slug' => 'categoria-atualizada',
            'status' => 'published',
            'is_placeholder' => '1',
        ]);

    $updateResponse->assertRedirect(route('tenant.categories.index', ['subdomain' => 'tenant-crud-a'], false));

    $category->refresh();
    expect($category->name)->toBe('Categoria Atualizada');
    expect($category->is_placeholder)->toBeTrue();

    $deleteResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.categories.destroy', ['subdomain' => 'tenant-crud-a', 'category' => $category->id], false));

    $deleteResponse->assertRedirect(route('tenant.categories.index', ['subdomain' => 'tenant-crud-a'], false));
    expect($category->fresh())->toBeNull();
});

test('tenant index is isolated by tenant_id for products and categories', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenant('tenant-a');
    $tenantB = makeTenant('tenant-b');
    assignTenantAdminRole($user, $tenantA->id);

    $categoryA = Category::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Categoria A',
        'slug' => 'categoria-a',
        'status' => 'published',
    ]);

    Category::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Categoria B',
        'slug' => 'categoria-b',
        'status' => 'published',
    ]);

    Product::query()->create([
        'tenant_id' => $tenantA->id,
        'category_id' => $categoryA->id,
        'name' => 'Produto A',
        'slug' => 'produto-a',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    Product::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Produto B',
        'slug' => 'produto-b',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    $categoriesResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-a.'.config('app.landlord_domain')])
        ->get(route('tenant.categories.index', ['subdomain' => 'tenant-a'], false));

    $categoriesResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/categories/Index')
            ->has('categories.data', 1)
            ->where('categories.data.0.slug', 'categoria-a'));

    $productsResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-a.'.config('app.landlord_domain')])
        ->get(route('tenant.products.index', ['subdomain' => 'tenant-a'], false));

    $productsResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'produto-a'));
});

test('tenant product store validates required fields', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-product-validation');
    assignTenantAdminRole($user, $tenant->id);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-product-validation.'.config('app.landlord_domain')])
        ->post(route('tenant.products.store', ['subdomain' => 'tenant-product-validation'], false), [
            'status' => 'draft',
            'dimensions_status' => 'draft',
        ]);

    $response
        ->assertSessionHasErrors(['name']);
});

test('sync single endpoint is mocked while import system is rebuilt', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-sync-mocked');
    assignTenantAdminRole($user, $tenant->id);

    $response = $this
        ->from(route('tenant.products.index', ['subdomain' => 'tenant-sync-mocked'], false))
        ->withServerVariables(['HTTP_HOST' => 'tenant-sync-mocked.'.config('app.landlord_domain')])
        ->post(route('tenant.products.sync-single', ['subdomain' => 'tenant-sync-mocked'], false), [
            'produto' => '7896038308600',
            'store_ids' => [],
        ]);

    $response
        ->assertRedirect(route('tenant.products.index', ['subdomain' => 'tenant-sync-mocked'], false))
        ->assertSessionHas('toast.type', 'info');
});

test('tenant admin can search product sortiment attributes', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-sortiment-search');
    assignTenantAdminRole($user, $tenant->id);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Amaciante Azul',
        'slug' => 'amaciante-azul',
        'status' => 'published',
        'dimensions_status' => 'published',
        'sortiment_attribute' => 'LIMPEZA | LAVANDERIA | AMACIANTE',
    ]);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Amaciante Rosa',
        'slug' => 'amaciante-rosa',
        'status' => 'published',
        'dimensions_status' => 'published',
        'sortiment_attribute' => 'LIMPEZA | LAVANDERIA | AMACIANTE',
    ]);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Detergente',
        'slug' => 'detergente',
        'status' => 'published',
        'dimensions_status' => 'published',
        'sortiment_attribute' => 'LIMPEZA | COZINHA | DETERGENTE',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-sortiment-search.'.config('app.landlord_domain')])
        ->getJson(route('tenant.products.sortiment-attributes', [
            'subdomain' => 'tenant-sortiment-search',
            'search' => 'amaci',
        ], false));

    $response
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'LIMPEZA | LAVANDERIA | AMACIANTE',
            ],
        ]);
});

test('product index trashed filter scopes soft deleted records', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-trashed-filter');
    assignTenantAdminRole($user, $tenant->id);

    $host = 'tenant-trashed-filter.'.config('app.landlord_domain');

    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Cat',
        'slug' => 'cat-trashed',
        'status' => 'published',
    ]);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
        'name' => 'Active Product',
        'slug' => 'active-product',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    $deleted = Product::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
        'name' => 'Deleted Product',
        'slug' => 'deleted-product',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);
    $deleted->delete();

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.products.index', ['subdomain' => 'tenant-trashed-filter'], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'active-product')
            ->where('filters.trashed', 'without'));

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.products.index', ['subdomain' => 'tenant-trashed-filter', 'trashed' => 'only'], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'deleted-product')
            ->where('filters.trashed', 'only'));

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.products.index', ['subdomain' => 'tenant-trashed-filter', 'trashed' => 'with'], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('filters.trashed', 'with'));
});

test('product index category filter includes descendant categories', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-category-descendants');
    assignTenantAdminRole($user, $tenant->id);

    $host = 'tenant-category-descendants.'.config('app.landlord_domain');

    $parentCategory = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Categoria Pai',
        'slug' => 'categoria-pai',
        'status' => 'published',
    ]);

    $childCategory = Category::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $parentCategory->id,
        'name' => 'Categoria Filha',
        'slug' => 'categoria-filha',
        'status' => 'published',
    ]);

    $otherCategory = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Categoria Outra',
        'slug' => 'categoria-outra',
        'status' => 'published',
    ]);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $parentCategory->id,
        'name' => 'Produto da Categoria Pai',
        'slug' => 'produto-categoria-pai',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $childCategory->id,
        'name' => 'Produto da Categoria Filha',
        'slug' => 'produto-categoria-filha',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $otherCategory->id,
        'name' => 'Produto de Outra Categoria',
        'slug' => 'produto-outra-categoria',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.products.index', [
            'subdomain' => 'tenant-category-descendants',
            'category_id' => $parentCategory->id,
        ], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products.data', 2)
            ->where('filters.category_id', $parentCategory->id));
});

test('category index filter includes descendant categories recursively', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-category-recursive-filter');
    assignTenantAdminRole($user, $tenant->id);

    $host = 'tenant-category-recursive-filter.'.config('app.landlord_domain');

    $rootCategory = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Categoria Raiz',
        'slug' => 'categoria-raiz',
        'status' => 'published',
    ]);

    $childCategory = Category::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $rootCategory->id,
        'name' => 'Categoria Filha',
        'slug' => 'categoria-filha',
        'status' => 'published',
    ]);

    $grandchildCategory = Category::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $childCategory->id,
        'name' => 'Categoria Neta',
        'slug' => 'categoria-neta',
        'status' => 'published',
    ]);

    Category::query()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $grandchildCategory->id,
        'name' => 'Categoria Bisneta',
        'slug' => 'categoria-bisneta',
        'status' => 'published',
    ]);

    Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Categoria Isolada',
        'slug' => 'categoria-isolada',
        'status' => 'published',
    ]);

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.categories.index', [
            'subdomain' => 'tenant-category-recursive-filter',
            'category_id' => $childCategory->id,
        ], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/categories/Index')
            ->has('categories.data', 3)
            ->where('filters.category_id', $childCategory->id));
});

/**
 * @return array<string, mixed>
 */
if (! function_exists('tenantDatabaseAttributes')) {
    function tenantDatabaseAttributes(): array
    {
        $defaultConnection = (string) config('database.default');

        return (array) config("database.connections.{$defaultConnection}");
    }
}

if (! function_exists('makeTenant')) {
    function makeTenant(string $subdomain): Tenant
    {
        $databaseAttributes = tenantDatabaseAttributes();

        $tenant = Tenant::query()->create([
            'name' => strtoupper($subdomain),
            'slug' => $subdomain,
            'database' => (string) ($databaseAttributes['database'] ?? 'database.sqlite'),
            'status' => 'active',
        ]);

        $tenant->domains()->create([
            'host' => $subdomain.'.'.config('app.landlord_domain'),
            'type' => 'subdomain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        return $tenant;
    }
}

if (! function_exists('assignTenantAdminRole')) {
    function assignTenantAdminRole(User $user, string $tenantId): void
    {
        $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

        setPermissionsTeamId($tenantId);
        $user->assignRole($role);
    }
}
