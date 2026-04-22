<?php

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

test('landlord dashboard shares landlord navigation context', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('navigation.context', 'landlord')
            ->where('navigation.main.0.type', 'item')
            ->where('navigation.main.0.href', route('dashboard', absolute: false))
            ->where('navigation.main.1.type', 'submenu')
            ->where('navigation.main.1.children.0.href', route('landlord.plans.index', absolute: false))
            ->where('navigation.main.1.children.0.subject', Plan::class)
            ->where('navigation.main.1.children.1.href', route('landlord.tenants.index', absolute: false))
            ->where('navigation.main.1.children.2.href', route('landlord.roles.index', absolute: false))
            ->where('navigation.main.1.children.2.subject', Role::class)
            ->where('navigation.main.1.children.3.href', route('landlord.users.index', absolute: false))
            ->where('navigation.main.1.children.3.subject', User::class)
            ->where('navigation.main.1.children.4.href', route('landlord.permissions.index', absolute: false))
            ->where('navigation.main.1.children.4.subject', Permission::class)
        );
});

test('tenant dashboard shares tenant navigation context', function () {
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

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'alfa.'.config('app.landlord_domain')])
        ->get(route('tenant.dashboard', ['subdomain' => 'alfa'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('navigation.context', 'tenant')
            ->has('navigation.main', 1)
            ->where('navigation.main.0.type', 'item')
            ->where('navigation.main.0.href', route('tenant.dashboard', ['subdomain' => 'alfa'], false))
        );
});
