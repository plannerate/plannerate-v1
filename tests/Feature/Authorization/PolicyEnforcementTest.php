<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

test('landlord route is forbidden when gate denies policy checks', function () {
    $this->actingAs(User::factory()->create());

    Gate::before(function (): bool {
        return false;
    });

    $response = $this->get(route('landlord.plans.index'));

    $response->assertForbidden();
});

test('navigation items are filtered out when gate denies policy checks', function () {
    $this->actingAs(User::factory()->create());

    Gate::before(function (): bool {
        return false;
    });

    $response = $this->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('navigation.context')
            ->has('navigation.main', 0));
});

test('tenant dashboard route is forbidden when gate denies policy checks', function () {
    $this->actingAs(User::factory()->create());

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

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

    Gate::before(function (): bool {
        return false;
    });

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'alfa.'.config('app.landlord_domain')])
        ->get(route('tenant.dashboard', ['subdomain' => 'alfa'], false));

    $response->assertForbidden();
});
