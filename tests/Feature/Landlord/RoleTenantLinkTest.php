<?php

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
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

    $this->actingAs(User::factory()->create());
});

function makeTenantForLink(string $slug): Tenant
{
    $plan = Plan::factory()->create(['user_limit' => 5]);

    return Tenant::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'database' => 'tenant_'.str_replace('-', '_', $slug),
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);
}

function makeGlobalTenantRole(string $name): Role
{
    return Role::query()->create([
        'type' => 'tenant',
        'system_name' => str($name)->slug()->value(),
        'name' => $name,
        'guard_name' => 'web',
        'tenant_id' => null,
    ]);
}

test('the role_tenant pivot navigates both directions', function (): void {
    $tenant = makeTenantForLink('link-both');
    $role = makeGlobalTenantRole('Perfil Vinculo');

    $tenant->roles()->attach($role->id);

    expect($tenant->roles->pluck('id'))->toContain($role->id);
    expect($role->fresh()->tenants->pluck('id'))->toContain($tenant->id);
});

test('creating a tenant syncs the selected roles into the pivot', function (): void {
    $roleA = makeGlobalTenantRole('Perfil Loja A');
    $roleB = makeGlobalTenantRole('Perfil Loja B');

    $this->post(route('landlord.tenants.store'), [
        'name' => 'Tenant Roles',
        'slug' => 'tenant-roles',
        'database' => 'tenant_roles',
        'status' => 'active',
        'role_ids' => [$roleA->id, $roleB->id],
        'host' => 'roles.plannerate-v1.test',
        'domain_is_active' => '1',
    ]);

    $tenant = Tenant::query()->where('slug', 'tenant-roles')->firstOrFail();

    expect($tenant->roles->pluck('id')->sort()->values()->all())
        ->toBe(collect([$roleA->id, $roleB->id])->sort()->values()->all());
});

test('updating a tenant replaces its linked roles', function (): void {
    $tenant = makeTenantForLink('link-update');
    $tenant->domains()->create([
        'tenant_id' => $tenant->id,
        'host' => 'link-update.plannerate-v1.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $roleA = makeGlobalTenantRole('Perfil Update A');
    $roleB = makeGlobalTenantRole('Perfil Update B');
    $tenant->roles()->attach($roleA->id);

    $this->put(route('landlord.tenants.update', $tenant), [
        'name' => $tenant->name,
        'slug' => $tenant->slug,
        'database' => $tenant->database,
        'status' => $tenant->status,
        'role_ids' => [$roleB->id],
        'host' => 'link-update.plannerate-v1.test',
        'domain_is_active' => '1',
    ]);

    expect($tenant->fresh()->roles->pluck('id')->all())->toBe([$roleB->id]);
});

test('creating a tenant role syncs the selected tenants into the pivot', function (): void {
    $tenant = makeTenantForLink('role-tenants');

    $this->post(route('landlord.roles.store'), [
        'type' => 'tenant',
        'name' => 'Operador Loja',
        'tenant_ids' => [$tenant->id],
    ]);

    $role = Role::query()->where('name', 'Operador Loja')->firstOrFail();

    expect($role->tenants->pluck('id')->all())->toBe([$tenant->id]);
});

test('a landlord role never links to tenants even if tenant_ids is sent', function (): void {
    $tenant = makeTenantForLink('landlord-role');

    $this->post(route('landlord.roles.store'), [
        'type' => 'landlord',
        'name' => 'Gestor Landlord',
        'tenant_ids' => [$tenant->id],
    ]);

    $role = Role::query()->where('name', 'Gestor Landlord')->firstOrFail();

    expect($role->tenants)->toHaveCount(0);
});
