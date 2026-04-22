<?php

use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionName;
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

test('authenticated user can list roles', function () {
    $response = $this->get(route('landlord.roles.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/roles/Index')
            ->has('roles.data'));
});

test('authenticated user can create update and delete a role', function () {
    $createResponse = $this->post(route('landlord.roles.store'), [
        'name' => 'manager',
        'permissions' => [PermissionName::LANDLORD_TENANTS_VIEW_ANY],
    ]);

    $createResponse->assertRedirect(route('landlord.roles.index'));

    $role = Role::query()
        ->whereNull('tenant_id')
        ->where('name', 'manager')
        ->firstOrFail();

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'manager',
        'guard_name' => 'web',
        'tenant_id' => null,
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.roles.update', $role), [
        'name' => 'operator',
        'permissions' => [PermissionName::LANDLORD_TENANTS_UPDATE],
    ]);

    $updateResponse->assertRedirect(route('landlord.roles.index'));

    $role->refresh();

    expect($role->name)->toBe('operator');
    expect($role->permissions->pluck('name')->all())->toContain(PermissionName::LANDLORD_TENANTS_UPDATE);

    $deleteResponse = $this->delete(route('landlord.roles.destroy', $role));

    $deleteResponse->assertRedirect(route('landlord.roles.index'));

    $this->assertDatabaseMissing('roles', [
        'id' => $role->id,
    ], 'landlord');
});

test('role cannot be deleted when role is assigned to users', function () {
    $role = Role::query()->where('name', 'tenant-admin')->firstOrFail();

    $targetUser = User::factory()->create();
    setPermissionsTeamId(null);
    $targetUser->assignRole($role);

    $response = $this->delete(route('landlord.roles.destroy', $role));

    $response->assertRedirect();

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
    ], 'landlord');
});
