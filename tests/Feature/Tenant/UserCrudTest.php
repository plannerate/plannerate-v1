<?php

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate', [
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

test('tenant user create and store are blocked when plan user limit is reached', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = Plan::factory()->create([
        'user_limit' => 1,
    ]);

    $tenant = makeTenantWithPlan('tenant-users-limit', $plan->id);
    assignTenantAdminRoleForUsers($user, $tenant->id);

    $tenant->makeCurrent();
    User::query()->create([
        'name' => 'Limite Atingido',
        'email' => 'limit@tenant.test',
        'password' => 'password',
        'is_active' => true,
    ]);

    $host = 'tenant-users-limit.'.config('app.landlord_domain');

    $createResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.users.create', ['subdomain' => 'tenant-users-limit'], false));

    $createResponse->assertSessionHasErrors(['limit']);

    $storeResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.users.store', ['subdomain' => 'tenant-users-limit'], false), [
            'name' => 'Novo Usuario',
            'email' => 'new-user@tenant.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ]);

    $storeResponse->assertSessionHasErrors(['limit']);
});

test('tenant admin can create and remove user within plan limit', function (): void {
    $authUser = User::factory()->create();
    $this->actingAs($authUser);

    $plan = Plan::factory()->create([
        'user_limit' => 3,
    ]);

    $tenant = makeTenantWithPlan('tenant-users-crud', $plan->id);
    assignTenantAdminRoleForUsers($authUser, $tenant->id);

    $host = 'tenant-users-crud.'.config('app.landlord_domain');

    $storeResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.users.store', ['subdomain' => 'tenant-users-crud'], false), [
            'name' => 'Usuario Tenant',
            'email' => 'user-crud@tenant.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ]);

    $storeResponse->assertRedirect(route('tenant.users.index', ['subdomain' => 'tenant-users-crud'], false));

    $tenant->makeCurrent();
    $createdUser = User::query()->where('email', 'user-crud@tenant.test')->firstOrFail();

    $deleteResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.users.destroy', [
            'subdomain' => 'tenant-users-crud',
            'user' => $createdUser->id,
        ], false));

    $deleteResponse->assertRedirect(route('tenant.users.index', ['subdomain' => 'tenant-users-crud'], false));
    expect($createdUser->fresh())->toBeNull();
});

/**
 * @return array<string, mixed>
 */
function tenantDatabaseAttributesForUsers(): array
{
    $defaultConnection = (string) config('database.default');

    return (array) config("database.connections.{$defaultConnection}");
}

function makeTenantWithPlan(string $subdomain, string $planId): Tenant
{
    $databaseAttributes = tenantDatabaseAttributesForUsers();

    $tenant = Tenant::query()->create([
        'name' => strtoupper($subdomain),
        'slug' => $subdomain,
        'database' => (string) ($databaseAttributes['database'] ?? 'database.sqlite'),
        'status' => 'active',
        'plan_id' => $planId,
    ]);

    $tenant->domains()->create([
        'host' => $subdomain.'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

function assignTenantAdminRoleForUsers(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}
