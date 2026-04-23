<?php

use App\Models\Cluster;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Store;
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

test('tenant admin can execute stores clusters and providers crud in tenant context', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForRegistry('tenant-crud-stores');
    assignTenantAdminRoleForRegistry($user, $tenant->id);

    $host = 'tenant-crud-stores.'.config('app.landlord_domain');
    $subdomain = 'tenant-crud-stores';

    $storeCreate = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.stores.store', ['subdomain' => $subdomain], false), [
            'name' => 'Loja Central',
            'slug' => 'loja-central',
            'code' => 'STORE-001',
            'document' => '123',
            'status' => 'draft',
        ]);

    $storeCreate->assertRedirect(route('tenant.stores.index', ['subdomain' => $subdomain], false));

    $store = Store::query()->where('tenant_id', $tenant->id)->where('slug', 'loja-central')->firstOrFail();

    $storeUpdate = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->put(route('tenant.stores.update', ['subdomain' => $subdomain, 'store' => $store->id], false), [
            'name' => 'Loja Atualizada',
            'slug' => 'loja-atualizada',
            'code' => 'STORE-002',
            'document' => '456',
            'status' => 'published',
        ]);

    $storeUpdate->assertRedirect(route('tenant.stores.index', ['subdomain' => $subdomain], false));

    $store->refresh();
    expect($store->name)->toBe('Loja Atualizada');

    $clusterCreate = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.clusters.store', ['subdomain' => $subdomain], false), [
            'store_id' => $store->id,
            'name' => 'Cluster A',
            'slug' => 'cluster-a',
            'status' => 'draft',
        ]);

    $clusterCreate->assertRedirect(route('tenant.clusters.index', ['subdomain' => $subdomain], false));

    $cluster = Cluster::query()->where('tenant_id', $tenant->id)->where('slug', 'cluster-a')->firstOrFail();

    $clusterUpdate = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->put(route('tenant.clusters.update', ['subdomain' => $subdomain, 'cluster' => $cluster->id], false), [
            'store_id' => $store->id,
            'name' => 'Cluster B',
            'slug' => 'cluster-b',
            'status' => 'published',
        ]);

    $clusterUpdate->assertRedirect(route('tenant.clusters.index', ['subdomain' => $subdomain], false));

    $cluster->refresh();
    expect($cluster->name)->toBe('Cluster B');

    $providerCreate = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.providers.store', ['subdomain' => $subdomain], false), [
            'name' => 'Provider A',
            'code' => 'P-001',
            'is_default' => '1',
        ]);

    $providerCreate->assertRedirect(route('tenant.providers.index', ['subdomain' => $subdomain], false));

    $provider = Provider::query()->where('tenant_id', $tenant->id)->where('code', 'P-001')->firstOrFail();

    $providerUpdate = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->put(route('tenant.providers.update', ['subdomain' => $subdomain, 'provider' => $provider->id], false), [
            'name' => 'Provider B',
            'code' => 'P-002',
            'is_default' => '0',
        ]);

    $providerUpdate->assertRedirect(route('tenant.providers.index', ['subdomain' => $subdomain], false));

    $provider->refresh();
    expect($provider->name)->toBe('Provider B');
    expect($provider->is_default)->toBeFalse();

    $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.clusters.destroy', ['subdomain' => $subdomain, 'cluster' => $cluster->id], false))
        ->assertRedirect(route('tenant.clusters.index', ['subdomain' => $subdomain], false));

    $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.providers.destroy', ['subdomain' => $subdomain, 'provider' => $provider->id], false))
        ->assertRedirect(route('tenant.providers.index', ['subdomain' => $subdomain], false));

    $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.stores.destroy', ['subdomain' => $subdomain, 'store' => $store->id], false))
        ->assertRedirect(route('tenant.stores.index', ['subdomain' => $subdomain], false));

    expect($cluster->fresh())->toBeNull();
    expect($provider->fresh())->toBeNull();
    expect($store->fresh())->toBeNull();
});

test('tenant list routes are isolated by tenant_id for stores clusters and providers', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenantForRegistry('tenant-a-reg');
    $tenantB = makeTenantForRegistry('tenant-b-reg');
    assignTenantAdminRoleForRegistry($user, $tenantA->id);

    $storeA = Store::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Loja A',
        'slug' => 'loja-a',
        'code' => 'A',
        'status' => 'published',
    ]);

    $storeB = Store::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Loja B',
        'slug' => 'loja-b',
        'code' => 'B',
        'status' => 'published',
    ]);

    Cluster::query()->create([
        'tenant_id' => $tenantA->id,
        'store_id' => $storeA->id,
        'name' => 'Cluster A',
        'slug' => 'cluster-a',
        'status' => 'published',
    ]);

    Cluster::query()->create([
        'tenant_id' => $tenantB->id,
        'store_id' => $storeB->id,
        'name' => 'Cluster B',
        'slug' => 'cluster-b',
        'status' => 'published',
    ]);

    Provider::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Provider A',
        'code' => 'PA',
        'is_default' => true,
    ]);

    Provider::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Provider B',
        'code' => 'PB',
        'is_default' => false,
    ]);

    $storesResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-a-reg.'.config('app.landlord_domain')])
        ->get(route('tenant.stores.index', ['subdomain' => 'tenant-a-reg'], false));

    $storesResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/stores/Index')
            ->has('stores.data', 1)
            ->where('stores.data.0.slug', 'loja-a'));

    $clustersResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-a-reg.'.config('app.landlord_domain')])
        ->get(route('tenant.clusters.index', ['subdomain' => 'tenant-a-reg'], false));

    $clustersResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/clusters/Index')
            ->has('clusters.data', 1)
            ->where('clusters.data.0.slug', 'cluster-a'));

    $providersResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-a-reg.'.config('app.landlord_domain')])
        ->get(route('tenant.providers.index', ['subdomain' => 'tenant-a-reg'], false));

    $providersResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/providers/Index')
            ->has('providers.data', 1)
            ->where('providers.data.0.code', 'PA'));
});

test('cluster store validates store ownership and unique fields are tenant scoped', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenantForRegistry('tenant-unique-a');
    $tenantB = makeTenantForRegistry('tenant-unique-b');
    assignTenantAdminRoleForRegistry($user, $tenantA->id);

    $storeA = Store::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Store A',
        'slug' => 'same-slug',
        'code' => 'same-code',
        'status' => 'draft',
    ]);

    $storeB = Store::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Store B',
        'slug' => 'same-slug',
        'code' => 'same-code',
        'status' => 'draft',
    ]);

    expect($storeA)->not->toBeNull();
    expect($storeB)->not->toBeNull();

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-unique-a.'.config('app.landlord_domain')])
        ->post(route('tenant.clusters.store', ['subdomain' => 'tenant-unique-a'], false), [
            'store_id' => $storeB->id,
            'name' => 'Invalid Cluster',
            'status' => 'draft',
        ]);

    $response->assertSessionHasErrors(['store_id']);
});

test('tenant routes for new registries are forbidden without role permissions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForRegistry('tenant-no-role');

    $storeResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-no-role.'.config('app.landlord_domain')])
        ->get(route('tenant.stores.index', ['subdomain' => 'tenant-no-role'], false));

    $clusterResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-no-role.'.config('app.landlord_domain')])
        ->get(route('tenant.clusters.index', ['subdomain' => 'tenant-no-role'], false));

    $providerResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-no-role.'.config('app.landlord_domain')])
        ->get(route('tenant.providers.index', ['subdomain' => 'tenant-no-role'], false));

    $storeResponse->assertForbidden();
    $clusterResponse->assertForbidden();
    $providerResponse->assertForbidden();

    expect($tenant)->not->toBeNull();
});

function makeTenantForRegistry(string $subdomain): Tenant
{
    $databaseAttributes = tenantDatabaseAttributesForRegistry();

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

function assignTenantAdminRoleForRegistry(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}

/**
 * @return array<string, mixed>
 */
function tenantDatabaseAttributesForRegistry(): array
{
    $defaultConnection = (string) config('database.default');

    return (array) config("database.connections.{$defaultConnection}");
}
