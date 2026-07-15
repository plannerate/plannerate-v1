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

test('roles index exposes create update and delete abilities for button gating', function () {
    $response = $this->get(route('landlord.roles.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/roles/Index')
            ->where('can.create', fn ($value) => is_bool($value))
            ->where('can.update', fn ($value) => is_bool($value))
            ->where('can.delete', fn ($value) => is_bool($value)));
});

test('authenticated user can create update and delete a role', function () {
    $createResponse = $this->post(route('landlord.roles.store'), [
        'type' => 'landlord',
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
        'type' => 'landlord',
        'name' => 'manager',
        'guard_name' => 'web',
        'tenant_id' => null,
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.roles.update', $role), [
        'type' => 'landlord',
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

test('is_administrative flag persists on create and update for a tenant role', function () {
    $createResponse = $this->post(route('landlord.roles.store'), [
        'type' => 'tenant',
        'name' => 'Revisor Custom',
        'is_administrative' => '1',
    ]);

    $createResponse->assertRedirect(route('landlord.roles.index'));

    $role = Role::query()
        ->whereNull('tenant_id')
        ->where('name', 'Revisor Custom')
        ->firstOrFail();

    expect($role->is_administrative)->toBeTrue();

    $updateResponse = $this->put(route('landlord.roles.update', $role), [
        'type' => 'tenant',
        'name' => 'Revisor Custom',
        'is_administrative' => '0',
    ]);

    $updateResponse->assertRedirect(route('landlord.roles.index'));

    expect($role->fresh()->is_administrative)->toBeFalse();
});

test('protected role can update its display name without changing slug or permissions', function () {
    setPermissionsTeamId(null);

    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();
    $originalPermissions = $role->permissions->pluck('name')->sort()->values()->all();

    $response = $this->put(route('landlord.roles.update', $role), [
        'type' => $role->type,
        'name' => 'Administrador do Tenant',
        // slug (system_name) não é enviado e permissões não devem ser tocadas
    ]);

    $response->assertRedirect(route('landlord.roles.index'));

    $role->refresh();

    expect($role->name)->toBe('Administrador do Tenant');
    expect($role->system_name)->toBe('tenant-admin');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe($originalPermissions);
});

test('role slug is immutable on update', function () {
    $this->post(route('landlord.roles.store'), [
        'type' => 'landlord',
        'name' => 'supervisor',
    ])->assertRedirect(route('landlord.roles.index'));

    $role = Role::query()->whereNull('tenant_id')->where('name', 'supervisor')->firstOrFail();
    $originalSlug = $role->system_name;

    $this->put(route('landlord.roles.update', $role), [
        'type' => 'landlord',
        'name' => 'supervisor-geral',
        'system_name' => 'slug-alterado', // deve ser ignorado
    ])->assertRedirect(route('landlord.roles.index'));

    $role->refresh();

    expect($role->name)->toBe('supervisor-geral');
    expect($role->system_name)->toBe($originalSlug);
});

test('role cannot be deleted when role is assigned to users', function () {
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    $targetUser = User::factory()->create();
    setPermissionsTeamId(null);
    $targetUser->assignRole($role);

    $response = $this->delete(route('landlord.roles.destroy', $role));

    $response->assertRedirect();

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
    ], 'landlord');
});

test('role cannot receive permissions from another type', function () {
    $response = $this->post(route('landlord.roles.store'), [
        'type' => 'tenant',
        'name' => 'tenant-operator',
        'permissions' => [PermissionName::LANDLORD_TENANTS_VIEW_ANY],
    ]);

    $response->assertSessionHasErrors(['permissions.0']);

    $this->assertDatabaseMissing('roles', [
        'name' => 'tenant-operator',
        'type' => 'tenant',
    ], 'landlord');
});
