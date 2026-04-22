<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

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
});

test('tenant route access is scoped by tenant_id role assignment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = Tenant::query()->create([
        'name' => 'Alfa',
        'slug' => 'alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
    ]);

    $tenantA->domains()->create([
        'host' => 'alfa.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $tenantB = Tenant::query()->create([
        'name' => 'Beta',
        'slug' => 'beta',
        'database' => 'tenant_beta',
        'status' => 'active',
    ]);

    $tenantB->domains()->create([
        'host' => 'beta.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    setPermissionsTeamId($tenantA->id);
    $tenantAdminRole = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();
    $user->assignRole($tenantAdminRole);

    $tenantAResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'alfa.'.config('app.landlord_domain')])
        ->get(route('tenant.dashboard', ['subdomain' => 'alfa'], false));

    $tenantAResponse->assertOk();

    $tenantBResponse = $this
        ->withServerVariables(['HTTP_HOST' => 'beta.'.config('app.landlord_domain')])
        ->get(route('tenant.dashboard', ['subdomain' => 'beta'], false));

    $tenantBResponse->assertForbidden();
});

test('landlord context remains allowed while rbac is enabled', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
});
