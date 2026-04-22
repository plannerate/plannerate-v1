<?php

use App\Models\Plan;
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

test('authenticated user can list plans', function () {
    $response = $this->get(route('landlord.plans.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/plans/Index')
            ->has('plans.data'));
});

test('authenticated user can create update and delete plan', function () {
    $createResponse = $this->post(route('landlord.plans.store'), [
        'name' => 'Plano Basico',
        'slug' => 'plano-basico',
        'description' => 'Plano inicial',
        'price_cents' => 9900,
        'user_limit' => 10,
        'is_active' => '1',
    ]);

    $createResponse->assertRedirect(route('landlord.plans.index'));

    $plan = Plan::query()->where('slug', 'plano-basico')->firstOrFail();

    $this->assertDatabaseHas('plans', [
        'id' => $plan->id,
        'name' => 'Plano Basico',
        'price_cents' => 9900,
        'is_active' => 1,
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.plans.update', $plan), [
        'name' => 'Plano Pro',
        'slug' => 'plano-pro',
        'description' => 'Plano atualizado',
        'price_cents' => 19900,
        'user_limit' => 25,
        'is_active' => '0',
    ]);

    $updateResponse->assertRedirect(route('landlord.plans.index'));

    $this->assertDatabaseHas('plans', [
        'id' => $plan->id,
        'name' => 'Plano Pro',
        'slug' => 'plano-pro',
        'price_cents' => 19900,
        'is_active' => 0,
    ], 'landlord');

    $deleteResponse = $this->delete(route('landlord.plans.destroy', $plan));

    $deleteResponse->assertRedirect(route('landlord.plans.index'));

    $this->assertDatabaseMissing('plans', [
        'id' => $plan->id,
    ], 'landlord');
});

test('plan cannot be deleted when in use by a tenant', function () {
    $plan = Plan::query()->create([
        'name' => 'Plano em Uso',
        'slug' => 'plano-em-uso',
        'price_cents' => 5000,
        'is_active' => true,
    ]);

    $tenant = Tenant::query()->create([
        'name' => 'Tenant Alfa',
        'slug' => 'tenant-alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $tenant->domains()->create([
        'host' => 'alfa.plannerate-v1.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $response = $this->delete(route('landlord.plans.destroy', $plan));

    $response->assertRedirect();

    $this->assertDatabaseHas('plans', [
        'id' => $plan->id,
    ], 'landlord');
});
