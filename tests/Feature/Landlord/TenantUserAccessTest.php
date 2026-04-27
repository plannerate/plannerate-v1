<?php

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'mysql',
        '--path' => 'database/migrations',
        '--realpath' => false,
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

afterEach(function (): void {
    CurrentTenantModel::forgetCurrent();
});

test('authenticated user can view tenant access screen with quota metadata', function () {
    $tenant = createTenantWithPlan(limit: 2);
    createTenantUser($tenant, 'Ana');

    $response = $this->get(route('landlord.tenants.access.edit', $tenant));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/tenants/Access')
            ->where('tenant.id', $tenant->id)
            ->where('tenant.plan_user_limit', 2)
            ->where('tenant.users_count', 1)
            ->where('tenant.can_create_users', true)
            ->has('users.data', 1)
            ->has('roles')
            ->has('status_options'));
});

test('tenant access prioritizes plan item user_limit over plans user_limit', function () {
    $tenant = createTenantWithPlan(limit: 5);
    $plan = Plan::query()->findOrFail($tenant->plan_id);

    $plan->items()->create([
        'key' => 'user_limit',
        'label' => 'Maximo de usuarios',
        'value' => '1',
        'type' => 'integer',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    createTenantUser($tenant, 'Ana');

    $response = $this->get(route('landlord.tenants.access.edit', $tenant));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.plan_user_limit', 1)
            ->where('tenant.can_create_users', false));
});

test('can create tenant user with tenant roles', function () {
    $tenant = createTenantWithPlan(limit: 2);
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    $response = $this->post(route('landlord.tenants.access.users.store', $tenant), [
        'name' => 'Novo Usuario',
        'email' => 'novo@tenant.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_active' => '1',
        'role_names' => [$role->name],
    ]);

    $response->assertRedirect();

    $tenant->makeCurrent();
    $tenantUser = TenantUser::query()->where('email', 'novo@tenant.test')->first();
    CurrentTenantModel::forgetCurrent();

    expect($tenantUser)->not()->toBeNull();

    $this->assertDatabaseHas('model_has_roles', [
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'model_type' => TenantUser::class,
        'model_id' => $tenantUser->id,
    ], 'landlord');
});

test('create redirects to tenant access base route without stale filters or pagination', function () {
    $tenant = createTenantWithPlan(limit: 3);

    $response = $this
        ->from(route('landlord.tenants.access.edit', [
            'tenant' => $tenant,
            'search' => 'ana',
            'status' => 'inactive',
            'page' => 2,
        ]))
        ->post(route('landlord.tenants.access.users.store', $tenant), [
            'name' => 'Novo Usuario',
            'email' => 'novo-redirecionamento@tenant.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ]);

    $response->assertRedirect(route('landlord.tenants.access.edit', $tenant));
});

test('blocks creation when tenant has no plan limit', function () {
    $tenant = createTenantWithoutPlan();

    $response = $this->from(route('landlord.tenants.access.edit', $tenant))
        ->post(route('landlord.tenants.access.users.store', $tenant), [
            'name' => 'Sem Limite',
            'email' => 'sem-limite@tenant.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response
        ->assertRedirect(route('landlord.tenants.access.edit', $tenant))
        ->assertSessionHasErrors('limit');
});

test('blocks creation when plan limit is reached', function () {
    $tenant = createTenantWithPlan(limit: 1);
    createTenantUser($tenant, 'Primeiro', 'primeiro@tenant.test');

    $response = $this->from(route('landlord.tenants.access.edit', $tenant))
        ->post(route('landlord.tenants.access.users.store', $tenant), [
            'name' => 'Segundo',
            'email' => 'segundo@tenant.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response
        ->assertRedirect(route('landlord.tenants.access.edit', $tenant))
        ->assertSessionHasErrors('limit');
});

test('can update tenant user profile and roles', function () {
    $tenant = createTenantWithPlan(limit: 2);
    $tenantUser = createTenantUser($tenant, 'Original', 'original@tenant.test');
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    $response = $this->put(route('landlord.tenants.access.users.update', [
        'tenant' => $tenant,
        'userId' => $tenantUser->id,
    ]), [
        'name' => 'Atualizado',
        'email' => 'atualizado@tenant.test',
        'is_active' => '0',
        'role_names' => [$role->name],
    ]);

    $response->assertRedirect();

    $tenant->makeCurrent();
    $tenantUser->refresh();
    CurrentTenantModel::forgetCurrent();

    expect($tenantUser->name)->toBe('Atualizado')
        ->and($tenantUser->email)->toBe('atualizado@tenant.test')
        ->and($tenantUser->is_active)->toBeFalse();
});

test('can disable and enable tenant user', function () {
    $tenant = createTenantWithPlan(limit: 2);
    $tenantUser = createTenantUser($tenant);

    $disableResponse = $this->patch(route('landlord.tenants.access.users.toggle-active', [
        'tenant' => $tenant,
        'userId' => $tenantUser->id,
    ]), [
        'is_active' => 0,
    ]);

    $disableResponse->assertRedirect();

    $tenant->makeCurrent();
    expect((bool) TenantUser::query()->findOrFail($tenantUser->id)->is_active)->toBeFalse();
    CurrentTenantModel::forgetCurrent();

    $enableResponse = $this->patch(route('landlord.tenants.access.users.toggle-active', [
        'tenant' => $tenant,
        'userId' => $tenantUser->id,
    ]), [
        'is_active' => 1,
    ]);

    $enableResponse->assertRedirect();

    $tenant->makeCurrent();
    expect((bool) TenantUser::query()->findOrFail($tenantUser->id)->is_active)->toBeTrue();
    CurrentTenantModel::forgetCurrent();
});

test('can soft delete filter deleted and restore tenant user', function () {
    $tenant = createTenantWithPlan(limit: 2);
    $tenantUser = createTenantUser($tenant);

    $deleteResponse = $this->delete(route('landlord.tenants.access.users.destroy', [
        'tenant' => $tenant,
        'userId' => $tenantUser->id,
    ]));

    $deleteResponse->assertRedirect();

    $tenant->makeCurrent();
    expect(TenantUser::query()->find($tenantUser->id))->toBeNull();
    expect(TenantUser::query()->withTrashed()->find($tenantUser->id)?->deleted_at)->not()->toBeNull();
    CurrentTenantModel::forgetCurrent();

    $deletedListResponse = $this->get(route('landlord.tenants.access.edit', [
        'tenant' => $tenant,
        'status' => 'deleted',
    ]));

    $deletedListResponse->assertInertia(fn (Assert $page) => $page
        ->where('filters.status', 'deleted')
        ->has('users.data', 1));

    $restoreResponse = $this->patch(route('landlord.tenants.access.users.restore', [
        'tenant' => $tenant,
        'userId' => $tenantUser->id,
    ]));

    $restoreResponse->assertRedirect();

    $tenant->makeCurrent();
    expect(TenantUser::query()->find($tenantUser->id))->not()->toBeNull();
    CurrentTenantModel::forgetCurrent();
});

test('tenant access management does not affect landlord users module', function () {
    $tenant = createTenantWithPlan(limit: 2);
    $landlordUserCountBefore = User::query()->count();

    $this->post(route('landlord.tenants.access.users.store', $tenant), [
        'name' => 'Tenant Only',
        'email' => 'tenant-only@tenant.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect();

    expect(User::query()->count())->toBe($landlordUserCountBefore);
});

function createTenantWithPlan(int $limit): Tenant
{
    $plan = Plan::query()->create([
        'name' => 'Plano Teste',
        'slug' => 'plano-teste-'.$limit.'-'.fake()->numberBetween(100, 999),
        'description' => null,
        'price_cents' => 0,
        'user_limit' => $limit,
        'is_active' => true,
    ]);

    $tenant = Tenant::query()->create([
        'name' => 'Tenant Teste',
        'slug' => 'tenant-teste-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $tenant->domains()->create([
        'host' => 'tenant-'.fake()->numberBetween(100, 999).'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

function createTenantWithoutPlan(): Tenant
{
    $tenant = Tenant::query()->create([
        'name' => 'Tenant Sem Plano',
        'slug' => 'tenant-sem-plano-'.fake()->numberBetween(100, 999),
        'database' => (string) config('database.connections.mysql.database'),
        'status' => 'active',
        'plan_id' => null,
    ]);

    $tenant->domains()->create([
        'host' => 'sem-plano-'.fake()->numberBetween(100, 999).'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

function createTenantUser(Tenant $tenant, string $name = 'Usuario Tenant', ?string $email = null): TenantUser
{
    $tenant->makeCurrent();

    $tenantUser = TenantUser::query()->create([
        'name' => $name,
        'email' => $email ?? fake()->unique()->safeEmail(),
        'password' => 'password123',
        'is_active' => true,
    ]);

    CurrentTenantModel::forgetCurrent();

    return $tenantUser;
}
