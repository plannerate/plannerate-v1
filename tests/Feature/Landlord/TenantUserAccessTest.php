<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

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

test('authenticated user can view tenant access screen', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Alfa',
        'slug' => 'alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => 'alfa.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('landlord.tenants.access.edit', $tenant));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/tenants/Access')
            ->where('tenant.id', $tenant->id)
            ->has('roles')
            ->has('users'));
});

test('authenticated user can update tenant scoped user roles', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Alfa',
        'slug' => 'alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => 'alfa.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $targetUser = User::factory()->create();
    $tenantAdminRole = Role::query()->where('name', 'tenant-admin')->firstOrFail();

    $response = $this->put(route('landlord.tenants.access.update', $tenant), [
        'user_id' => $targetUser->id,
        'roles' => [$tenantAdminRole->name],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('model_has_roles', [
        'tenant_id' => $tenant->id,
        'role_id' => $tenantAdminRole->id,
        'model_type' => User::class,
        'model_id' => $targetUser->id,
    ], 'landlord');
});
