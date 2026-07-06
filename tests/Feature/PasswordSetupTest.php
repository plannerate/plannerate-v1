<?php

use App\Models\Tenant;
use App\Models\TenantPasswordSetupToken;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

const PASSWORD_SETUP_TEST_PASSWORD = 'Sup3rSenha!2026';

beforeEach(function (): void {
    $tenantPath = database_path('testing_password_setup.sqlite');

    if (file_exists($tenantPath)) {
        unlink($tenantPath);
    }

    touch($tenantPath);

    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => $tenantPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    DB::purge('tenant');

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
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
});

afterEach(function (): void {
    CurrentTenantModel::forgetCurrent();
});

test('creating a tenant user from the landlord panel issues a pending password setup token and no password field is required', function () {
    Notification::fake();

    $tenant = passwordSetupTenant();
    $issuer = User::factory()->create();

    $response = $this->actingAs($issuer)->post(route('landlord.tenants.access.users.store', $tenant), [
        'name' => 'Novo Usuario',
        'email' => 'novo@tenant.test',
        'is_active' => '1',
    ]);

    $response->assertRedirect();

    $tenant->makeCurrent();
    $tenantUser = User::query()->where('email', 'novo@tenant.test')->first();
    CurrentTenantModel::forgetCurrent();

    expect($tenantUser)->not()->toBeNull();

    $this->assertDatabaseHas('tenant_password_setup_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $tenantUser->id,
        'status' => 'pending',
    ], 'landlord');

    Notification::assertSentTo($tenantUser, SetPasswordNotification::class);
});

test('creating a user from tenant self-service issues a pending password setup token', function () {
    Notification::fake();

    $tenant = passwordSetupTenant();
    $tenantAdmin = passwordSetupTenantUser($tenant, 'Admin Tenant', 'admin@tenant.test');

    $tenant->makeCurrent();
    $response = $this->actingAs($tenantAdmin)->post(passwordSetupUrl($tenant, route('tenant.users.store', [], false)), [
        'name' => 'Novo Usuario Tenant',
        'email' => 'novo-tenant@tenant.test',
        'is_active' => '1',
    ]);

    $response->assertRedirect();

    $tenant->makeCurrent();
    $tenantUser = User::query()->where('email', 'novo-tenant@tenant.test')->first();
    CurrentTenantModel::forgetCurrent();

    expect($tenantUser)->not()->toBeNull();

    $this->assertDatabaseHas('tenant_password_setup_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $tenantUser->id,
        'status' => 'pending',
    ], 'landlord');
});

test('full issue and consume round trip sets the password and logs the user in', function () {
    Notification::fake();

    $tenant = passwordSetupTenant();
    $issuer = User::factory()->create();

    $this->actingAs($issuer)->post(route('landlord.tenants.access.users.store', $tenant), [
        'name' => 'Cliente Alvo',
        'email' => 'alvo@tenant.test',
        'is_active' => '1',
    ]);

    $tenant->makeCurrent();
    $targetUser = User::query()->where('email', 'alvo@tenant.test')->firstOrFail();
    CurrentTenantModel::forgetCurrent();

    $setupUrl = captureSetupUrl($targetUser);

    $tenant->makeCurrent();
    $editResponse = $this->get($setupUrl);
    $editResponse->assertOk();
    $editResponse->assertInertia(fn (Assert $page) => $page->component('auth/SetPassword'));

    $tenant->makeCurrent();
    $updateResponse = $this->post($setupUrl, [
        'password' => PASSWORD_SETUP_TEST_PASSWORD,
        'password_confirmation' => PASSWORD_SETUP_TEST_PASSWORD,
    ]);
    $updateResponse->assertRedirect(passwordSetupUrl($tenant, route('tenant.dashboard', [], false)));

    $this->assertAuthenticatedAs($targetUser);

    $this->assertDatabaseHas('tenant_password_setup_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $targetUser->id,
        'status' => 'used',
        'used_reason' => 'consumed',
    ], 'landlord');
});

test('consuming an already used password setup token fails', function () {
    Notification::fake();

    $tenant = passwordSetupTenant();
    $issuer = User::factory()->create();

    $this->actingAs($issuer)->post(route('landlord.tenants.access.users.store', $tenant), [
        'name' => 'Cliente Alvo',
        'email' => 'alvo2@tenant.test',
        'is_active' => '1',
    ]);

    $tenant->makeCurrent();
    $targetUser = User::query()->where('email', 'alvo2@tenant.test')->firstOrFail();
    CurrentTenantModel::forgetCurrent();

    $setupUrl = captureSetupUrl($targetUser);

    $tenant->makeCurrent();
    $this->post($setupUrl, [
        'password' => PASSWORD_SETUP_TEST_PASSWORD,
        'password_confirmation' => PASSWORD_SETUP_TEST_PASSWORD,
    ])->assertRedirect(passwordSetupUrl($tenant, route('tenant.dashboard', [], false)));

    $tenant->makeCurrent();
    $secondAttempt = $this->get($setupUrl);
    $secondAttempt->assertRedirect(route('login'));
    $secondAttempt->assertSessionHasErrors('email');
});

test('consuming an expired password setup token fails', function () {
    Notification::fake();

    $tenant = passwordSetupTenant();
    $issuer = User::factory()->create();

    $this->actingAs($issuer)->post(route('landlord.tenants.access.users.store', $tenant), [
        'name' => 'Cliente Alvo',
        'email' => 'alvo3@tenant.test',
        'is_active' => '1',
    ]);

    $tenant->makeCurrent();
    $targetUser = User::query()->where('email', 'alvo3@tenant.test')->firstOrFail();
    CurrentTenantModel::forgetCurrent();

    $setupUrl = captureSetupUrl($targetUser);

    TenantPasswordSetupToken::query()->update(['expires_at' => now()->subMinute()]);

    $tenant->makeCurrent();
    $response = $this->get($setupUrl);
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');

    $this->assertDatabaseHas('tenant_password_setup_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $targetUser->id,
        'status' => 'pending',
    ], 'landlord');
});

test('resending the password setup link invalidates the previous token', function () {
    Notification::fake();

    $tenant = passwordSetupTenant();
    $issuer = User::factory()->create();

    $this->actingAs($issuer)->post(route('landlord.tenants.access.users.store', $tenant), [
        'name' => 'Cliente Alvo',
        'email' => 'alvo4@tenant.test',
        'is_active' => '1',
    ]);

    $tenant->makeCurrent();
    $targetUser = User::query()->where('email', 'alvo4@tenant.test')->firstOrFail();
    CurrentTenantModel::forgetCurrent();

    $firstUrl = captureSetupUrl($targetUser);

    $this->actingAs($issuer)->post(route('landlord.tenants.access.users.password-setup.resend', [
        'tenant' => $tenant,
        'userId' => $targetUser->id,
    ]));

    Notification::assertSentTimes(SetPasswordNotification::class, 2);
    $secondUrl = Notification::sent($targetUser, SetPasswordNotification::class)->last()->setupUrl;

    expect($secondUrl)->not()->toBe($firstUrl);

    $tenant->makeCurrent();
    $firstAttempt = $this->get($firstUrl);
    $firstAttempt->assertRedirect(route('login'));
    $firstAttempt->assertSessionHasErrors('email');

    $tenant->makeCurrent();
    $secondAttempt = $this->post($secondUrl, [
        'password' => PASSWORD_SETUP_TEST_PASSWORD,
        'password_confirmation' => PASSWORD_SETUP_TEST_PASSWORD,
    ]);
    $secondAttempt->assertRedirect(passwordSetupUrl($tenant, route('tenant.dashboard', [], false)));

    $this->assertDatabaseHas('tenant_password_setup_tokens', [
        'tenant_id' => $tenant->id,
        'target_user_id' => $targetUser->id,
        'status' => 'used',
        'used_reason' => 'superseded',
    ], 'landlord');
});

function passwordSetupTenant(): Tenant
{
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Password Setup',
        'slug' => 'tenant-password-setup-'.fake()->unique()->numberBetween(1000, 999999),
        'database' => (string) config('database.connections.tenant.database'),
        'status' => 'active',
        'plan_id' => null,
    ]));

    $tenant->domains()->create([
        'host' => 'password-setup-'.fake()->unique()->numberBetween(1000, 999999).'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant->fresh('primaryDomain');
}

function passwordSetupTenantUser(Tenant $tenant, string $name = 'Usuario Tenant', ?string $email = null): User
{
    $tenant->makeCurrent();

    $tenantUser = User::query()->create([
        'name' => $name,
        'email' => $email ?? fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => 'password123',
        'is_active' => true,
    ]);

    CurrentTenantModel::forgetCurrent();

    return $tenantUser;
}

function passwordSetupUrl(Tenant $tenant, string $path): string
{
    $path = ltrim($path, '/');

    return $path === ''
        ? sprintf('http://%s', $tenant->primaryDomain->host)
        : sprintf('http://%s/%s', $tenant->primaryDomain->host, $path);
}

function captureSetupUrl(User $targetUser): string
{
    $setupUrl = null;

    Notification::assertSentTo($targetUser, SetPasswordNotification::class, function (SetPasswordNotification $notification) use (&$setupUrl): bool {
        $setupUrl = $notification->setupUrl;

        return true;
    });

    expect($setupUrl)->not()->toBeNull();

    return $setupUrl;
}
