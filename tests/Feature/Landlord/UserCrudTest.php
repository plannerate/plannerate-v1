<?php

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

test('authenticated user can list users', function () {
    $response = $this->get(route('landlord.users.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/users/Index')
            ->has('users.data'));
});

test('authenticated user can create update and delete landlord user', function () {
    $superAdminRole = Role::query()->where('system_name', 'super-admin')->firstOrFail();

    $createResponse = $this->post(route('landlord.users.store'), [
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'is_active' => '1',
        'role_ids' => [$superAdminRole->id],
    ]);

    $createResponse->assertRedirect(route('landlord.users.index'));

    $targetUser = User::query()->where('email', 'maria@example.com')->firstOrFail();

    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'is_active' => 1,
    ], 'landlord');

    $this->assertDatabaseHas('model_has_roles', [
        'tenant_id' => null,
        'role_id' => $superAdminRole->id,
        'model_type' => User::class,
        'model_id' => $targetUser->id,
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.users.update', $targetUser), [
        'name' => 'Maria Souza',
        'email' => 'maria.souza@example.com',
        'password' => '',
        'password_confirmation' => '',
        'is_active' => '0',
        'role_ids' => [],
    ]);

    $updateResponse->assertRedirect(route('landlord.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'name' => 'Maria Souza',
        'email' => 'maria.souza@example.com',
        'is_active' => 0,
    ], 'landlord');

    $this->assertDatabaseMissing('model_has_roles', [
        'tenant_id' => null,
        'role_id' => $superAdminRole->id,
        'model_type' => User::class,
        'model_id' => $targetUser->id,
    ], 'landlord');

    $deleteResponse = $this->delete(route('landlord.users.destroy', $targetUser));

    $deleteResponse->assertRedirect(route('landlord.users.index'));

    $this->assertDatabaseMissing('users', [
        'id' => $targetUser->id,
    ], 'landlord');
});
