<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\SimilarGroup;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;

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

test('tenant admin can create a similar group with product pivot and shared dimensions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeSimilarGroupTenant('similar-pivot');
    assignSimilarGroupTenantAdminRole($user, $tenant->id);

    $productA = tenantProduct($tenant->id, 'Sabonete Lavanda 90g', '7891000000010');
    $productB = tenantProduct($tenant->id, 'Sabonete Lavanda Refil 90g', '7891000000027');

    $host = 'similar-pivot.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.similar-groups.store', ['subdomain' => 'similar-pivot'], false), [
            'grouper_code' => 'SAB-LAV-90',
            'name' => 'Sabonetes Lavanda 90g',
            'status' => 'published',
            'description' => 'Produtos equivalentes para exposição.',
            'product_ids' => [$productA->id, $productB->id],
            'dimension_source_product_id' => $productA->id,
            'apply_dimensions' => '1',
            'width' => '8.50',
            'height' => '12.00',
            'depth' => '3.25',
            'weight' => '90.00',
            'unit' => 'cm',
            'dimension_status' => 'published',
        ]);

    $response->assertRedirect(route('tenant.similar-groups.index', ['subdomain' => 'similar-pivot'], false));

    $group = SimilarGroup::query()
        ->where('tenant_id', $tenant->id)
        ->where('grouper_code', 'SAB-LAV-90')
        ->firstOrFail();

    expect($group->products()->pluck('products.id')->all())
        ->toContain($productA->id, $productB->id);
    expect($group->base_dimensions_product_ean)->toBe('7891000000010');

    $this->assertDatabaseHas('product_similar_group', [
        'tenant_id' => $tenant->id,
        'similar_group_id' => $group->id,
        'product_id' => $productA->id,
    ]);

    $productA->refresh();
    $productB->refresh();

    expect((string) $productA->width)->toBe('8.50')
        ->and((string) $productB->height)->toBe('12.00')
        ->and((string) $productA->depth)->toBe('3.25')
        ->and((bool) $productA->has_dimensions)->toBeTrue()
        ->and($productB->dimension_status)->toBe('published');
});

test('tenant similar group product search returns matching products', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeSimilarGroupTenant('similar-search');
    assignSimilarGroupTenantAdminRole($user, $tenant->id);

    tenantProduct($tenant->id, 'Biscoito Chocolate', '7892000000019');
    tenantProduct($tenant->id, 'Sabonete Neutro', '7892000000026');

    $this
        ->withServerVariables(['HTTP_HOST' => 'similar-search.'.config('app.landlord_domain')])
        ->getJson(route('tenant.similar-groups.products.search', [
            'subdomain' => 'similar-search',
            'search' => 'Biscoito',
        ], false))
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.name', 'Biscoito Chocolate');
});

if (! function_exists('makeSimilarGroupTenant')) {
    function makeSimilarGroupTenant(string $subdomain): Tenant
    {
        $defaultConnection = (string) config('database.default');
        $databaseAttributes = (array) config("database.connections.{$defaultConnection}");

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

if (! function_exists('assignSimilarGroupTenantAdminRole')) {
    function assignSimilarGroupTenantAdminRole(User $user, string $tenantId): void
    {
        $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

        setPermissionsTeamId($tenantId);
        $user->assignRole($role);
    }
}

if (! function_exists('tenantProduct')) {
    function tenantProduct(string $tenantId, string $name, string $ean): Product
    {
        return Product::query()->forceCreate([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'ean' => $ean,
            'status' => 'published',
            'dimension_status' => 'draft',
            'unit' => 'cm',
        ]);
    }
}
