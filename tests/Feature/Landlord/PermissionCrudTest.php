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
        'name' => 'tenant.projects.view', // slug imutável: deve ser ignorado
        'short_name' => 'Ver Projetos',
        'description' => 'Permite ver projetos.',
    ]);

    $updateResponse->assertRedirect(route('landlord.permissions.index'));

    $permission->refresh();
    // O slug (name) não pode ser alterado na edição.
    expect($permission->name)->toBe('tenant.projects.viewAny');
    expect($permission->short_name)->toBe('Ver Projetos');
    expect($permission->description)->toBe('Permite ver projetos.');

    $deleteResponse = $this->delete(route('landlord.permissions.destroy', $permission));

    $deleteResponse->assertRedirect(route('landlord.permissions.index'));

    $this->assertDatabaseMissing('permissions', [
        'id' => $permission->id,
    ], 'landlord');
});

test('store persists provided short name and description', function () {
    $this->post(route('landlord.permissions.store'), [
        'type' => 'tenant',
        'name' => 'tenant.projects.viewAny',
        'short_name' => 'Listar Projetos',
        'description' => 'Permite listar os projetos.',
    ])->assertRedirect(route('landlord.permissions.index'));

    $this->assertDatabaseHas('permissions', [
        'name' => 'tenant.projects.viewAny',
        'short_name' => 'Listar Projetos',
        'description' => 'Permite listar os projetos.',
    ], 'landlord');
});

test('sync backfills short name and description for existing permissions', function () {
    // O seeder cria as permissões sem nome curto/descrição.
    $permission = Permission::query()
        ->where('name', 'landlord.plans.create')
        ->firstOrFail();

    expect($permission->short_name)->toBeNull();
    expect($permission->description)->toBeNull();

    $this->post(route('landlord.permissions.sync'))
        ->assertRedirect(route('landlord.permissions.index'));

    $permission->refresh();

    expect($permission->short_name)->toBe('Criar Planos');
    expect($permission->description)->toBe('Permite cadastrar um plano de assinatura.');
});
