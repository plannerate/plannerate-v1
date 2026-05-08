<?php

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());
});

test('authenticated user can view tenant integration page', function () {
    $tenant = createTenantForIntegration();

    $response = $this->get(route('landlord.tenants.integration.edit', $tenant));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/tenants/Integration')
            ->where('tenant.id', $tenant->id)
            ->where('integration', null));
});

test('put creates tenant integration when absent and stores encrypted config', function () {
    $tenant = createTenantForIntegration();

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    $this->assertDatabaseHas('tenant_integrations', [
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'is_active' => 1,
    ], 'landlord');

    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($integration->config['processing']['sales_initial_days'] ?? null)->toBe(120)
        ->and($integration->config['processing']['products_initial_days'] ?? null)->toBe(120)
        ->and($integration->config['connection']['base_url'] ?? null)->toBe('https://sysmo.example.com')
        ->and($integration->config['auth']['type'] ?? null)->toBe('basic');
});

test('put updates existing integration instead of creating another one', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload([
        'api_url' => 'https://updated-sysmo.example.com',
        'auth_password' => 'new-secret',
    ]));

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    expect(TenantIntegration::query()->where('tenant_id', $tenant->id)->count())->toBe(1);

    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($integration->config['connection']['base_url'] ?? null)->toBe('https://updated-sysmo.example.com');
});

test('validation blocks invalid integration type and invalid url', function () {
    $tenant = createTenantForIntegration();

    $response = $this->from(route('landlord.tenants.integration.edit', $tenant))
        ->put(route('landlord.tenants.integration.update', $tenant), [
            'integration_type' => 'erp_x',
            'api_url' => 'not-an-url',
        ]);

    $response
        ->assertRedirect(route('landlord.tenants.integration.edit', $tenant))
        ->assertSessionHasErrors([
            'integration_type',
            'api_url',
        ]);
});

test('update keeps existing password when auth_password is blank', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload([
        'auth_password' => '',
        'api_url' => 'https://keep-password.example.com',
    ]));

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($integration->config['auth']['credentials']['password'] ?? null)->toBe('planner-pass')
        ->and($integration->config['connection']['base_url'] ?? null)->toBe('https://keep-password.example.com');
});

test('can test connection successfully', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    Http::fake([
        'https://sysmo.example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $response = $this
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('landlord.tenants.integration.test-connection', $tenant), [
            'test_path' => '/',
            'test_method' => 'GET',
        ]);

    $response->assertOk()->assertJsonPath('ok', true);

    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($integration->last_sync)->not->toBeNull();
});

test('test connection returns error feedback when request fails', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());
    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();

    Http::fake([
        'https://sysmo.example.com/*' => Http::response(['message' => 'not authorized'], 401),
    ]);

    $response = $this->post(route('landlord.tenants.integration.test-connection', $tenant));

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    $integration->refresh();
    expect($integration->last_sync)->toBeNull();
});

test('test connection sends custom test_body to the api', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    Http::fake([
        'https://sysmo.example.com/*' => Http::response(['items' => [['id' => 10]]], 200),
    ]);

    $response = $this
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('landlord.tenants.integration.test-connection', $tenant), [
            'test_path' => '/custom/path',
            'test_method' => 'POST',
            'test_body' => '{"filtro":"abc"}',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('meta.method', 'POST')
        ->assertJsonPath('meta.path', '/custom/path')
        ->assertJsonPath('data.items.0.id', 10);

    Http::assertSent(function (Request $request): bool {
        return ($request->data()['filtro'] ?? null) === 'abc';
    });
});

function createTenantForIntegration(): Tenant
{
    /** @var Tenant $tenant */
    $tenant = Tenant::withoutEvents(function (): Tenant {
        return Tenant::query()->create([
            'name' => 'Tenant Integracao',
            'slug' => 'tenant-integracao-'.fake()->numberBetween(100, 999),
            'database' => (string) config('database.connections.landlord.database'),
            'status' => 'active',
        ]);
    });

    return $tenant;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function integrationPayload(array $overrides = []): array
{
    return array_merge([
        'integration_type' => 'sysmo',
        'api_url' => 'https://sysmo.example.com',
        'auth_type' => 'basic',
        'auth_username' => 'planner-user',
        'auth_password' => 'planner-pass',
        'sales_initial_days' => 120,
        'products_initial_days' => 120,
        'processing_time' => '02:00',
        'is_active' => true,
    ], $overrides);
}
