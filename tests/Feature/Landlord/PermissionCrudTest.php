<?php

use App\Models\Permission;
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

test('authenticated user can list permissions', function () {
    $response = $this->get(route('landlord.permissions.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/permissions/Index')
            ->has('permissions.data'));
});

test('authenticated user can create update and delete permission', function () {
    $createResponse = $this->post(route('landlord.permissions.store'), [
        'type' => 'tenant',
        'name' => 'tenant.projects.viewAny',
    ]);

    $createResponse->assertRedirect(route('landlord.permissions.index'));

    $permission = Permission::query()
        ->where('name', 'tenant.projects.viewAny')
        ->where('type', 'tenant')
        ->firstOrFail();

    $this->assertDatabaseHas('permissions', [
        'id' => $permission->id,
        'name' => 'tenant.projects.viewAny',
        'type' => 'tenant',
        'guard_name' => 'web',
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.permissions.update', $permission), [
        'type' => 'tenant',
        'name' => 'tenant.projects.view',
    ]);

    $updateResponse->assertRedirect(route('landlord.permissions.index'));

    $permission->refresh();
    expect($permission->name)->toBe('tenant.projects.view');

    $deleteResponse = $this->delete(route('landlord.permissions.destroy', $permission));

    $deleteResponse->assertRedirect(route('landlord.permissions.index'));

    $this->assertDatabaseMissing('permissions', [
        'id' => $permission->id,
    ], 'landlord');
});
