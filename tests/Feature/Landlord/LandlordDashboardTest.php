<?php

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

test('landlord dashboard returns metrics and tenant lists', function () {
    Tenant::factory()->create([
        'name' => 'Tenant Ativo',
        'slug' => 'tenant-ativo',
        'database' => 'tenant_ativo',
        'status' => 'active',
    ]);

    Tenant::factory()->create([
        'name' => 'Tenant Provisionando',
        'slug' => 'tenant-provisionando',
        'database' => 'tenant_provisionando',
        'status' => 'provisioning',
    ]);

    Tenant::factory()->create([
        'name' => 'Tenant Suspenso',
        'slug' => 'tenant-suspenso',
        'database' => 'tenant_suspenso',
        'status' => 'suspended',
    ]);

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/Dashboard')
            ->where('totals.all', 3)
            ->where('totals.active', 1)
            ->where('totals.provisioning', 1)
            ->where('totals.inactive', 1)
            ->has('status_chart', 4)
            ->has('tenants_by_month', 6)
            ->has('recent_tenants')
        );
});
