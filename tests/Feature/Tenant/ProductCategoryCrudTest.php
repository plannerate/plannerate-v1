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

/**
 * @return array<string, mixed>
 */
function tenantDatabaseAttributes(): array
{
    $defaultConnection = (string) config('database.default');

    return (array) config("database.connections.{$defaultConnection}");
}

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

function assignTenantAdminRole(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}
