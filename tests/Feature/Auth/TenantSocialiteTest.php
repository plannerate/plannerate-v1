<?php

use App\Models\Tenant;
use App\Models\TenantSocialiteProvider;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('redirect returns 404 when tenant has no active provider', function () {
    $tenant = makeSocialiteTenant('sso-no-provider');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'sso-no-provider.'.config('app.landlord_domain')])
        ->get(route('tenant.auth.socialite.redirect', ['subdomain' => 'sso-no-provider', 'provider' => 'google'], false));

    $response->assertNotFound();
});

test('redirect returns 404 when provider is inactive', function () {
    $tenant = makeSocialiteTenant('sso-inactive');

    TenantSocialiteProvider::query()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'google',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'is_active' => false,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'sso-inactive.'.config('app.landlord_domain')])
        ->get(route('tenant.auth.socialite.redirect', ['subdomain' => 'sso-inactive', 'provider' => 'google'], false));

    $response->assertNotFound();
});

test('redirect redirects to OAuth provider when active provider exists', function () {
    Socialite::fake();

    $tenant = makeSocialiteTenant('sso-redirect');

    TenantSocialiteProvider::query()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'google',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'is_active' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'sso-redirect.'.config('app.landlord_domain')])
        ->get(route('tenant.auth.socialite.redirect', ['subdomain' => 'sso-redirect', 'provider' => 'google'], false));

    $response->assertRedirect();
});

test('callback logs in existing user by email', function () {
    $tenant = makeSocialiteTenant('sso-callback');

    TenantSocialiteProvider::query()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'google',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'is_active' => true,
    ]);

    $user = User::factory()->create(['email' => 'test@example.com']);

    Socialite::fake()->assertNothingCalled();

    Socialite::fake([
        'google' => [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'id' => 'google-uid-123',
        ],
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'sso-callback.'.config('app.landlord_domain')])
        ->get(route('tenant.auth.socialite.callback', ['subdomain' => 'sso-callback', 'provider' => 'google'], false));

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('callback redirects to login with error when user email is not found', function () {
    $tenant = makeSocialiteTenant('sso-not-found');

    TenantSocialiteProvider::query()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'google',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'is_active' => true,
    ]);

    Socialite::fake([
        'google' => [
            'email' => 'unknown@example.com',
            'name' => 'Unknown User',
            'id' => 'google-uid-999',
        ],
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'sso-not-found.'.config('app.landlord_domain')])
        ->get(route('tenant.auth.socialite.callback', ['subdomain' => 'sso-not-found', 'provider' => 'google'], false));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

function makeSocialiteTenant(string $subdomain): Tenant
{
    $defaultConnection = (string) config('database.default');
    $dbConfig = (array) config("database.connections.{$defaultConnection}");

    $tenant = Tenant::query()->create([
        'name' => strtoupper($subdomain),
        'slug' => $subdomain,
        'database' => (string) ($dbConfig['database'] ?? 'database.sqlite'),
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => $subdomain.'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}
