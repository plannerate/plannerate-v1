<?php

use App\Models\Module;
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
            ->where('navigation.main.1.type', 'item')
            ->where('navigation.main.1.href', route('landlord.plans.index', absolute: false))
            ->where('navigation.main.1.subject', Plan::class)
            ->where('navigation.main.2.type', 'item')
            ->where('navigation.main.2.href', route('landlord.tenants.index', absolute: false))
            ->where('navigation.main.2.subject', Tenant::class)
            ->where('navigation.main.3.type', 'item')
            ->where('navigation.main.3.href', route('landlord.modules.index', absolute: false))
            ->where('navigation.main.3.subject', Module::class)
            ->where('navigation.main.4.type', 'item')
            ->where('navigation.main.4.href', route('landlord.roles.index', absolute: false))
            ->where('navigation.main.4.subject', Role::class)
            ->where('navigation.main.5.type', 'item')
            ->where('navigation.main.5.href', route('landlord.users.index', absolute: false))
            ->where('navigation.main.5.subject', User::class)
            ->where('navigation.main.6.type', 'item')
            ->where('navigation.main.6.href', route('landlord.permissions.index', absolute: false))
            ->where('navigation.main.6.subject', Permission::class)
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
            ->where('tenant.name', 'Alfa')
            ->has('navigation.main', 1)
            ->where('navigation.main.0.type', 'item')
            ->where('navigation.main.0.href', route('tenant.dashboard', ['subdomain' => 'alfa'], false))
        );
});
