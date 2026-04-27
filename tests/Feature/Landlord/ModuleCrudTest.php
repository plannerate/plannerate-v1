<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());
});

test('authenticated user can list modules', function () {
    $response = $this->get(route('landlord.modules.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/modules/Index')
            ->has('modules.data'));
});

test('authenticated user can create update and delete module', function () {
    $createResponse = $this->post(route('landlord.modules.store'), [
        'name' => 'Modulo BI',
        'slug' => 'modulo-bi',
        'description' => 'Modulo para analytics',
        'is_active' => '1',
    ]);

    $createResponse->assertRedirect(route('landlord.modules.index'));

    $module = Module::query()->where('slug', 'modulo-bi')->firstOrFail();

    $this->assertDatabaseHas('modules', [
        'id' => $module->id,
        'name' => 'Modulo BI',
        'is_active' => 1,
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.modules.update', $module), [
        'name' => 'Modulo Insights',
        'slug' => 'modulo-insights',
        'description' => 'Modulo atualizado',
        'is_active' => '0',
    ]);

    $updateResponse->assertRedirect(route('landlord.modules.index'));

    $this->assertDatabaseHas('modules', [
        'id' => $module->id,
        'name' => 'Modulo Insights',
        'slug' => 'modulo-insights',
        'is_active' => 0,
    ], 'landlord');

    $deleteResponse = $this->delete(route('landlord.modules.destroy', $module));

    $deleteResponse->assertRedirect(route('landlord.modules.index'));

    $this->assertDatabaseMissing('modules', [
        'id' => $module->id,
    ], 'landlord');
});

test('module cannot be deleted when in use by a tenant', function () {
    $module = Module::query()->create([
        'name' => 'Modulo em Uso',
        'slug' => 'modulo-em-uso',
        'is_active' => true,
    ]);

    $tenant = Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'tenant-alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => 'alfa.plannerate-v1.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $tenant->modules()->attach($module->id);

    $response = $this->delete(route('landlord.modules.destroy', $module));

    $response->assertRedirect();

    $this->assertDatabaseHas('modules', [
        'id' => $module->id,
    ], 'landlord');
});
