<?php

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

test('put creates tenant integration when absent and stores encrypted payload', function () {
    $tenant = createTenantForIntegration();

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), sysmoPayload());

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    $this->assertDatabaseHas('tenant_integrations', [
        'tenant_id' => $tenant->id,
        'integration_type' => 'sysmo',
        'identifier' => '72316342000126',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'is_active' => 1,
    ], 'landlord');

    $record = DB::connection('landlord')
        ->table('tenant_integrations')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($record)->not()->toBeNull()
        ->and((string) $record->authentication_headers)->not->toContain('planner-user')
        ->and((string) $record->authentication_headers)->not->toContain('planner-pass')
        ->and((string) $record->authentication_body)->not->toContain('partner-123');
});

test('put updates existing integration instead of creating another one', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), sysmoPayload());

    $secondPayload = sysmoPayload([
        'identifier' => '05318772000190',
        'api_url' => 'https://updated-sysmo.example.com',
        'auth_password' => 'new-secret',
    ]);

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), $secondPayload);

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    expect(DB::connection('landlord')->table('tenant_integrations')->count())->toBe(1);

    $this->assertDatabaseHas('tenant_integrations', [
        'tenant_id' => $tenant->id,
        'identifier' => '05318772000190',
        'api_url' => 'https://updated-sysmo.example.com',
    ], 'landlord');
});

test('validation blocks invalid integration type and missing sysmo required fields', function () {
    $tenant = createTenantForIntegration();

    $response = $this->from(route('landlord.tenants.integration.edit', $tenant))
        ->put(route('landlord.tenants.integration.update', $tenant), [
            'integration_type' => 'erp_x',
            'identifier' => '',
            'external_name' => '',
            'http_method' => 'DELETE',
            'api_url' => 'not-an-url',
            'auth_username' => '',
            'auth_password' => '',
            'partner_key' => '',
        ]);

    $response
        ->assertRedirect(route('landlord.tenants.integration.edit', $tenant))
        ->assertSessionHasErrors([
            'integration_type',
            'identifier',
            'external_name',
            'http_method',
            'api_url',
            'auth_username',
            'auth_password',
            'partner_key',
        ]);
});

test('update keeps existing password when auth_password is blank', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), sysmoPayload());

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), sysmoPayload([
        'auth_password' => '',
        'api_url' => 'https://keep-password.example.com',
    ]));

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $headers = $integration->authentication_headers;

    expect($headers['auth_password'])->toBe('planner-pass')
        ->and($integration->api_url)->toBe('https://keep-password.example.com');
});

test('can test connection successfully', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), sysmoPayload());

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
    $this->put(route('landlord.tenants.integration.update', $tenant), sysmoPayload());
    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();

    Http::fake([
        'https://sysmo.example.com/*' => Http::response(['message' => 'not authorized'], 401),
    ]);

    $response = $this->post(route('landlord.tenants.integration.test-connection', $tenant));

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    $integration->refresh();
    expect($integration->last_sync)->toBeNull();
});

test('test connection returns structured json response', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), sysmoPayload());

    Http::fake([
        'https://sysmo.example.com/*' => Http::response(['items' => [['id' => 10]]], 200),
    ]);

    $response = $this
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('landlord.tenants.integration.test-connection', $tenant), [
            'test_path' => '/custom/path',
            'test_method' => 'GET',
            'test_body' => '{"filtro":"abc"}',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('meta.method', 'GET')
        ->assertJsonPath('meta.path', '/custom/path')
        ->assertJsonPath('data.items.0.id', 10);
});

function createTenantForIntegration(): Tenant
{
    /** @var Tenant $tenant */
    $tenant = Tenant::withoutEvents(function (): Tenant {
        return Tenant::query()->create([
            'name' => 'Tenant Integracao',
            'slug' => 'tenant-integracao-'.fake()->numberBetween(100, 999),
            'database' => (string) config('database.connections.mysql.database'),
            'status' => 'active',
        ]);
    });

    return $tenant;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sysmoPayload(array $overrides = []): array
{
    return array_merge([
        'integration_type' => 'sysmo',
        'identifier' => '72316342000126',
        'external_name' => 'produto',
        'external_name_ean' => 'ean_code',
        'external_name_status' => 'status',
        'external_name_sale_date' => 'sale_date',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.example.com',
        'auth_username' => 'planner-user',
        'auth_password' => 'planner-pass',
        'partner_key' => 'partner-123',
        'empresa' => '72316342000126',
        'days_to_maintain' => 120,
        'auto_processing_enabled' => true,
        'processing_time' => '02:00',
        'initial_setup_date' => '2026-04-27',
        'is_active' => true,
    ], $overrides);
}
