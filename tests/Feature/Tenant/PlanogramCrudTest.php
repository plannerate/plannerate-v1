<?php

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Planogram;
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

test('tenant admin can execute planogram crud in tenant context', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForPlanograms('tenant-planograms-crud');
    assignTenantAdminRoleForPlanograms($user, $tenant->id);

    $host = 'tenant-planograms-crud.'.config('app.landlord_domain');
    $subdomain = 'tenant-planograms-crud';

    $store = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja A',
        'slug' => 'loja-a',
        'status' => 'published',
    ]);

    $cluster = Cluster::query()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Cluster A',
        'slug' => 'cluster-a',
        'status' => 'published',
    ]);

    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Categoria A',
        'slug' => 'categoria-a',
        'status' => 'published',
    ]);

    $createResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.catalog.planograms.store', ['subdomain' => $subdomain], false), [
            'name' => 'Planograma 1',
            'slug' => 'planograma-1',
            'type' => 'planograma',
            'store_id' => $store->id,
            'cluster_id' => $cluster->id,
            'category_id' => $category->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'order' => 1,
            'status' => 'draft',
        ]);

    $createResponse->assertRedirect(route('tenant.catalog.planograms.index', ['subdomain' => $subdomain], false));

    $planogram = Planogram::query()
        ->where('tenant_id', $tenant->id)
        ->where('slug', 'planograma-1')
        ->firstOrFail();

    $updateResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->put(route('tenant.catalog.planograms.update', ['subdomain' => $subdomain, 'planogram' => $planogram->id], false), [
            'name' => 'Planograma 2',
            'slug' => 'planograma-2',
            'type' => 'realograma',
            'store_id' => $store->id,
            'cluster_id' => $cluster->id,
            'category_id' => $category->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'order' => 2,
            'status' => 'published',
        ]);

    $updateResponse->assertRedirect(route('tenant.catalog.planograms.index', ['subdomain' => $subdomain], false));

    $planogram->refresh();
    expect($planogram->name)->toBe('Planograma 2');
    expect($planogram->type)->toBe('realograma');
    expect($planogram->status)->toBe('published');

    $deleteResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.catalog.planograms.destroy', ['subdomain' => $subdomain, 'planogram' => $planogram->id], false));

    $deleteResponse->assertRedirect(route('tenant.catalog.planograms.index', ['subdomain' => $subdomain], false));
    expect($planogram->fresh())->toBeNull();
});

test('tenant planograms index is isolated by tenant_id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenantForPlanograms('tenant-planograms-a');
    $tenantB = makeTenantForPlanograms('tenant-planograms-b');
    assignTenantAdminRoleForPlanograms($user, $tenantA->id);

    Planogram::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Planograma A',
        'slug' => 'planograma-a',
        'type' => 'planograma',
        'status' => 'published',
    ]);

    Planogram::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Planograma B',
        'slug' => 'planograma-b',
        'type' => 'realograma',
        'status' => 'published',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-a.'.config('app.landlord_domain')])
        ->get(route('tenant.catalog.planograms.index', ['subdomain' => 'tenant-planograms-a'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Index')
            ->has('planograms.data', 1)
            ->where('planograms.data.0.slug', 'planograma-a'));
});

test('planogram store validates related records ownership by tenant', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenantForPlanograms('tenant-planograms-owner-a');
    $tenantB = makeTenantForPlanograms('tenant-planograms-owner-b');
    assignTenantAdminRoleForPlanograms($user, $tenantA->id);

    $storeFromTenantB = Store::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Loja Externa',
        'slug' => 'loja-externa',
        'status' => 'published',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-owner-a.'.config('app.landlord_domain')])
        ->post(route('tenant.catalog.planograms.store', ['subdomain' => 'tenant-planograms-owner-a'], false), [
            'name' => 'Planograma invalido',
            'type' => 'planograma',
            'store_id' => $storeFromTenantB->id,
            'status' => 'draft',
        ]);

    $response->assertSessionHasErrors(['store_id']);
});

test('tenant planogram routes are forbidden without permissions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    makeTenantForPlanograms('tenant-planograms-no-role');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-no-role.'.config('app.landlord_domain')])
        ->get(route('tenant.catalog.planograms.index', ['subdomain' => 'tenant-planograms-no-role'], false));

    $response->assertForbidden();
});

function makeTenantForPlanograms(string $subdomain): Tenant
{
    $databaseAttributes = tenantDatabaseAttributesForPlanograms();

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

function assignTenantAdminRoleForPlanograms(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}

/**
 * @return array<string, mixed>
 */
function tenantDatabaseAttributesForPlanograms(): array
{
    $defaultConnection = (string) config('database.default');

    return (array) config("database.connections.{$defaultConnection}");
}
