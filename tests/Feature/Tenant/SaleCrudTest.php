<?php

use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant admin can execute sales crud in tenant context', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-sales-crud');
    assignTenantAdminRole($user, $tenant->id);

    $store = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja Base',
        'status' => 'published',
    ]);

    $host = 'tenant-sales-crud.'.config('app.landlord_domain');

    $createResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.sales.store', ['subdomain' => 'tenant-sales-crud'], false), [
            'store_id' => $store->id,
            'codigo_erp' => 'ERP-100',
            'sale_date' => '2026-04-23',
            'promotion' => 'SEMANA-01',
            'ean' => '7891234567890',
            'sale_price' => '19.90',
            'total_sale_quantity' => '2.500',
            'total_sale_value' => '49.75',
        ]);

    $createResponse->assertRedirect(route('tenant.sales.index', ['subdomain' => 'tenant-sales-crud'], false));

    $sale = Sale::query()
        ->where('tenant_id', $tenant->id)
        ->where('codigo_erp', 'ERP-100')
        ->firstOrFail();

    expect($sale->store_id)->toBe($store->id);
    expect((string) $sale->total_sale_value)->toBe('49.75');

    $updateResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->put(route('tenant.sales.update', ['subdomain' => 'tenant-sales-crud', 'sale' => $sale->id], false), [
            'store_id' => $store->id,
            'codigo_erp' => 'ERP-100',
            'sale_date' => '2026-04-23',
            'promotion' => 'SEMANA-01',
            'sale_price' => '25.00',
            'total_sale_quantity' => '3.000',
            'total_sale_value' => '75.00',
        ]);

    $updateResponse->assertRedirect(route('tenant.sales.index', ['subdomain' => 'tenant-sales-crud'], false));

    $sale->refresh();
    expect((string) $sale->total_sale_value)->toBe('75.00');

    $deleteResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.sales.destroy', ['subdomain' => 'tenant-sales-crud', 'sale' => $sale->id], false));

    $deleteResponse->assertRedirect(route('tenant.sales.index', ['subdomain' => 'tenant-sales-crud'], false));
    expect($sale->fresh())->toBeNull();
});

test('tenant sales index is isolated by tenant_id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenant('tenant-sales-a');
    $tenantB = makeTenant('tenant-sales-b');
    assignTenantAdminRole($user, $tenantA->id);

    $storeA = Store::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Loja A',
        'status' => 'published',
    ]);

    $storeB = Store::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Loja B',
        'status' => 'published',
    ]);

    Sale::query()->create([
        'tenant_id' => $tenantA->id,
        'store_id' => $storeA->id,
        'codigo_erp' => 'SALE-A',
        'sale_date' => '2026-04-23',
        'promotion' => 'P-A',
        'total_sale_value' => '10.00',
    ]);

    Sale::query()->create([
        'tenant_id' => $tenantB->id,
        'store_id' => $storeB->id,
        'codigo_erp' => 'SALE-B',
        'sale_date' => '2026-04-23',
        'promotion' => 'P-B',
        'total_sale_value' => '20.00',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-sales-a.'.config('app.landlord_domain')])
        ->get(route('tenant.sales.index', ['subdomain' => 'tenant-sales-a'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/sales/Index')
            ->has('sales.data', 1)
            ->where('sales.data.0.codigo_erp', 'SALE-A'));
});

test('tenant sale store validates required fields', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-sale-validation');
    assignTenantAdminRole($user, $tenant->id);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-sale-validation.'.config('app.landlord_domain')])
        ->post(route('tenant.sales.store', ['subdomain' => 'tenant-sale-validation'], false), []);

    $response->assertSessionHasErrors(['store_id', 'codigo_erp', 'sale_date']);
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
