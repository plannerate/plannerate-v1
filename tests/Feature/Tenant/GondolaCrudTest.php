<?php

use App\Models\Gondola;
use App\Models\Planogram;
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

test('gondolas index requires planogram_id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForGondolas('tenant-gondolas-required');
    assignTenantAdminRoleForGondolas($user, $tenant->id);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-gondolas-required.'.config('app.landlord_domain')])
        ->get(route('tenant.catalog.gondolas.index', ['subdomain' => 'tenant-gondolas-required'], false));

    $response->assertNotFound();
});

test('tenant admin can execute gondola crud within a planogram context', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForGondolas('tenant-gondolas-crud');
    assignTenantAdminRoleForGondolas($user, $tenant->id);

    $host = 'tenant-gondolas-crud.'.config('app.landlord_domain');
    $subdomain = 'tenant-gondolas-crud';

    $planogram = Planogram::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Planograma A',
        'slug' => 'planograma-a',
        'type' => 'planograma',
        'status' => 'published',
    ]);

    $createResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.catalog.gondolas.store', ['subdomain' => $subdomain], false), [
            'planogram_id' => $planogram->id,
            'name' => 'Gondola A',
            'slug' => 'gondola-a',
            'num_modulos' => 2,
            'flow' => 'left_to_right',
            'alignment' => 'justify',
            'scale_factor' => 1,
            'status' => 'draft',
        ]);

    $createResponse->assertRedirect(route('tenant.catalog.gondolas.index', [
        'subdomain' => $subdomain,
        'planogram_id' => $planogram->id,
    ], false));

    $gondola = Gondola::query()->where('tenant_id', $tenant->id)->where('slug', 'gondola-a')->firstOrFail();

    $updateResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->put(route('tenant.catalog.gondolas.update', ['subdomain' => $subdomain, 'gondola' => $gondola->id], false), [
            'planogram_id' => $planogram->id,
            'name' => 'Gondola B',
            'slug' => 'gondola-b',
            'num_modulos' => 3,
            'flow' => 'right_to_left',
            'alignment' => 'center',
            'scale_factor' => 1.2,
            'status' => 'published',
        ]);

    $updateResponse->assertRedirect(route('tenant.catalog.gondolas.index', [
        'subdomain' => $subdomain,
        'planogram_id' => $planogram->id,
    ], false));

    $gondola->refresh();
    expect($gondola->name)->toBe('Gondola B');
    expect($gondola->flow)->toBe('right_to_left');

    $indexResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.catalog.gondolas.index', [
            'subdomain' => $subdomain,
            'planogram_id' => $planogram->id,
        ], false));

    $indexResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/gondolas/Index')
            ->has('gondolas.data', 1)
            ->where('gondolas.data.0.slug', 'gondola-b'));

    $deleteResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.catalog.gondolas.destroy', ['subdomain' => $subdomain, 'gondola' => $gondola->id], false));

    $deleteResponse->assertRedirect(route('tenant.catalog.gondolas.index', [
        'subdomain' => $subdomain,
        'planogram_id' => $planogram->id,
    ], false));

    expect($gondola->fresh())->toBeNull();
});

test('tenant gondolas list is isolated by planogram_id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForGondolas('tenant-gondolas-filter');
    assignTenantAdminRoleForGondolas($user, $tenant->id);

    $planogramA = Planogram::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Planograma A',
        'slug' => 'planograma-filter-a',
        'type' => 'planograma',
        'status' => 'published',
    ]);

    $planogramB = Planogram::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Planograma B',
        'slug' => 'planograma-filter-b',
        'type' => 'planograma',
        'status' => 'published',
    ]);

    Gondola::query()->create([
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogramA->id,
        'name' => 'Gondola A',
        'slug' => 'gondola-filter-a',
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'published',
    ]);

    Gondola::query()->create([
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogramB->id,
        'name' => 'Gondola B',
        'slug' => 'gondola-filter-b',
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'published',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-gondolas-filter.'.config('app.landlord_domain')])
        ->get(route('tenant.catalog.gondolas.index', [
            'subdomain' => 'tenant-gondolas-filter',
            'planogram_id' => $planogramA->id,
        ], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/gondolas/Index')
            ->has('gondolas.data', 1)
            ->where('gondolas.data.0.slug', 'gondola-filter-a'));
});

function makeTenantForGondolas(string $subdomain): Tenant
{
    $databaseAttributes = tenantDatabaseAttributesForGondolas();

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

function assignTenantAdminRoleForGondolas(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}

/**
 * @return array<string, mixed>
 */
function tenantDatabaseAttributesForGondolas(): array
{
    $defaultConnection = (string) config('database.default');

    return (array) config("database.connections.{$defaultConnection}");
}
