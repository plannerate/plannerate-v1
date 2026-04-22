<?php

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
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

test('plans index supports search and active status filters', function () {
    Plan::query()->create([
        'name' => 'Plano Ativo Pro',
        'slug' => 'plano-ativo-pro',
        'description' => null,
        'price_cents' => 1000,
        'user_limit' => 5,
        'is_active' => true,
    ]);

    Plan::query()->create([
        'name' => 'Plano Inativo',
        'slug' => 'plano-inativo',
        'description' => null,
        'price_cents' => 1500,
        'user_limit' => 5,
        'is_active' => false,
    ]);

    $response = $this->get(route('landlord.plans.index', [
        'search' => 'Pro',
        'is_active' => '1',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/plans/Index')
            ->has('plans.data', 1)
            ->where('plans.data.0.slug', 'plano-ativo-pro')
            ->where('filters.search', 'Pro')
            ->where('filters.is_active', '1'));
});

test('users index supports active and role filters', function () {
    $role = Role::query()
        ->whereNull('tenant_id')
        ->where('guard_name', 'web')
        ->where('system_name', 'super-admin')
        ->firstOrFail();

    $activeUser = User::factory()->create([
        'name' => 'User Active Filter',
        'email' => 'active-filter@example.com',
        'is_active' => true,
    ]);

    $inactiveUser = User::factory()->create([
        'name' => 'User Inactive Filter',
        'email' => 'inactive-filter@example.com',
        'is_active' => false,
    ]);

    $currentTeamId = getPermissionsTeamId();
    setPermissionsTeamId(null);
    $activeUser->assignRole($role);
    setPermissionsTeamId($currentTeamId);

    $response = $this->get(route('landlord.users.index', [
        'is_active' => '1',
        'role_id' => $role->id,
        'search' => 'Filter',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.email', 'active-filter@example.com')
            ->where('filters.is_active', '1')
            ->where('filters.role_id', $role->id));

    expect($inactiveUser->id)->not->toBe($activeUser->id);
});

test('permissions index supports type and search filters', function () {
    Permission::query()->create([
        'name' => 'tenant.custom.reports.viewAny',
        'guard_name' => 'web',
        'type' => 'tenant',
    ]);

    Permission::query()->create([
        'name' => 'landlord.custom.audit.viewAny',
        'guard_name' => 'web',
        'type' => 'landlord',
    ]);

    $response = $this->get(route('landlord.permissions.index', [
        'type' => 'tenant',
        'search' => 'custom.reports',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/permissions/Index')
            ->has('permissions.data', 1)
            ->where('permissions.data.0.name', 'tenant.custom.reports.viewAny')
            ->where('filters.type', 'tenant')
            ->where('filters.search', 'custom.reports'));
});
