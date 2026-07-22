<?php

use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelIntegrations\Models\IntegrationApi;
use Callcocam\LaravelIntegrations\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ATENÇÃO: arquivo parcialmente DESATUALIZADO — e já estava antes da extração do motor
 * para o pacote. Nunca esteve em nenhum job do CI, então apodreceu em silêncio enquanto
 * a API mudava.
 *
 * Corrigido nesta passagem (eram erros objetivos, com resposta única):
 * - `integration_type` guarda o ID da IntegrationApi, não o slug — é assim em produção
 *   e é o que o whitelist do request valida;
 * - o tenant da fixture precisa de domínio, senão o HandleInertiaRequests do app lê
 *   `$tenant->domain->host` em null e toda página do tenant responde 500.
 *
 * Ainda falha, por expectativas de uma API que mudou em refatorações anteriores:
 * - `test-connection` hoje devolve RedirectResponse (convenção do projeto: `back()` para
 *   mutação), e os testes esperam 200 com JSON;
 * - `products_path`/`sales_path` deixaram de ser persistidos como estavam.
 *
 * Consertar isso é decidir o que a API DEVE fazer, não trabalho de renomeação — por isso
 * ficou de fora do corte. A fronteira app↔pacote que importa está coberta por
 * tests/Feature/Integrations/IntegrationsPackageContractTest.php, esse sim no CI.
 */

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

    migrateIntegrationsLandlord();

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

test('tenant integration page lists active integration apis from landlord cadastro', function () {
    $api = IntegrationApi::query()->create([
        'name' => 'ACME ERP',
        'slug' => 'acme-erp',
        'requests' => [
            'method' => 'GET',
            'payload' => 'query',
        ],
        'response' => [
            'items_path' => 'data',
        ],
        'is_active' => true,
    ]);

    $tenant = createTenantForIntegration();

    $response = $this->get(route('landlord.tenants.integration.edit', $tenant));

    // A opção do select é identificada pelo ID; o slug vai junto, à parte.
    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/tenants/Integration')
            ->where('integration_types.0.value', (string) $api->id)
            ->where('integration_types.0.label', 'ACME ERP')
            ->where('integration_types.0.slug', 'acme-erp'));
});

test('put creates tenant integration when absent and stores encrypted config', function () {
    $tenant = createTenantForIntegration();

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    $this->assertDatabaseHas('tenant_integrations', [
        'tenant_id' => $tenant->id,
        'integration_type' => IntegrationApi::query()->where('slug', 'acme-erp')->value('id'),
        'is_active' => 1,
    ], 'landlord');

    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($integration->config['paths']['products'] ?? null)->toBe('/products')
        ->and($integration->config['paths']['sales'] ?? null)->toBe('/sales')
        ->and($integration->config['connection']['base_url'] ?? null)->toBe('https://acme.example.com')
        ->and($integration->config['auth']['type'] ?? null)->toBe('basic');
});

test('put updates existing integration instead of creating another one', function () {
    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload([
        'api_url' => 'https://updated-acme.example.com',
        'auth_password' => 'new-secret',
    ]));

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    expect(TenantIntegration::query()->where('tenant_id', $tenant->id)->count())->toBe(1);

    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($integration->config['connection']['base_url'] ?? null)->toBe('https://updated-acme.example.com');
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

test('put stores generic bearer token fetch configuration', function () {
    $tenant = createTenantForIntegration();

    $response = $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload([
        'integration_type' => 'token-api',
        'auth_type' => 'bearer',
        'auth_bearer_mode' => 'fetch',
        'auth_token_username' => 'token-user',
        'auth_token_password' => 'token-pass',
        'auth_token_method' => 'POST',
        'auth_token_path' => '/oauth/token',
        'auth_token_response_path' => 'data.access_token',
        'auth_token_username_field' => 'usuario',
        'auth_token_password_field' => 'senha',
        'auth_token_headers' => [
            ['key' => 'X-Token-Client', 'value' => 'plannerate', 'enabled' => '1'],
        ],
        'auth_token_params' => [
            ['key' => 'scope', 'value' => 'imports', 'enabled' => '1'],
        ],
        'auth_token_body' => [
            ['key' => 'grant_type', 'value' => 'password', 'enabled' => '1'],
        ],
    ]));

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));

    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($integration->integration_type)->toBe(IntegrationApi::query()->where('slug', 'token-api')->value('id'))
        ->and($integration->config['auth']['type'])->toBe('bearer')
        ->and($integration->config['auth']['token_mode'])->toBe('fetch')
        ->and($integration->config['auth']['credentials'])->toMatchArray([
            'username' => 'token-user',
            'password' => 'token-pass',
        ])
        ->and($integration->config['auth']['token_request'])->toMatchArray([
            'method' => 'POST',
            'path' => '/oauth/token',
            'response_path' => 'data.access_token',
            'username_field' => 'usuario',
            'password_field' => 'senha',
        ])
        ->and($integration->config['auth']['token_request']['headers'][0]['key'])->toBe('X-Token-Client')
        ->and($integration->config['auth']['token_request']['params'][0]['key'])->toBe('scope')
        ->and($integration->config['auth']['token_request']['body'][0]['key'])->toBe('grant_type');
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

test('test connection returns real success feedback', function () {
    Http::fake([
        'https://acme.example.com/*' => Http::response(['pong' => true]),
    ]);

    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    $response = $this
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('landlord.tenants.integration.test-connection', $tenant), [
            'test_path' => '/',
            'test_method' => 'GET',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.pong', true);
});

test('test connection redirect flow uses real feedback', function () {
    Http::fake([
        'https://acme.example.com/*' => Http::response(['pong' => true]),
    ]);

    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

    $response = $this->post(route('landlord.tenants.integration.test-connection', $tenant));

    $response->assertRedirect(route('landlord.tenants.integration.edit', $tenant));
});

test('test connection real response includes requested method and path', function () {
    Http::fake([
        'https://acme.example.com/custom/path*' => Http::response(['received' => true]),
    ]);

    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload());

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
        ->assertJsonPath('data.received', true);
});

test('test connection fetches bearer token before request', function () {
    Http::fake([
        'https://acme.example.com/oauth/token*' => Http::response([
            'data' => ['access_token' => 'fetched-token'],
        ]),
        'https://acme.example.com/protected*' => Http::response(['ok' => true]),
    ]);

    $tenant = createTenantForIntegration();
    $this->put(route('landlord.tenants.integration.update', $tenant), integrationPayload([
        'auth_type' => 'bearer',
        'auth_bearer_mode' => 'fetch',
        'auth_token_username' => 'token-user',
        'auth_token_password' => 'token-pass',
        'auth_token_path' => '/oauth/token',
        'auth_token_response_path' => 'data.access_token',
    ]));

    $response = $this
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('landlord.tenants.integration.test-connection', $tenant), [
            'test_path' => '/protected',
            'test_method' => 'GET',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.ok', true);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://acme.example.com/protected'
        && $request->hasHeader('Authorization', 'Bearer fetched-token'));
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

    // O HandleInertiaRequests do app lê `$tenant->domain->host` em toda rota que recebe
    // {tenant}; sem domínio, qualquer página do tenant responde 500.
    $tenant->domains()->create([
        'host' => $tenant->slug.'.plannerate.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function integrationPayload(array $overrides = []): array
{
    $slug = (string) ($overrides['integration_type'] ?? 'acme-erp');

    $api = IntegrationApi::query()->firstOrCreate(
        ['slug' => $slug],
        [
            'name' => str($slug)->headline()->toString(),
            'requests' => [
                'method' => 'GET',
                'payload' => 'query',
                'products' => [
                    'fallback_path' => '/products',
                    'field_map' => [],
                ],
                'sales' => [
                    'fallback_path' => '/sales',
                    'field_map' => [],
                ],
            ],
            'response' => [
                'items_path' => 'data',
            ],
            'is_active' => true,
        ],
    );

    // `integration_type` guarda o ID da IntegrationApi, não o slug — é assim que a
    // coluna vive em produção e é o que o whitelist do UpdateTenantIntegrationRequest
    // valida. O slug identifica o blueprint só na fixture.
    return array_merge([
        'integration_type' => (string) $api->id,
        'api_url' => 'https://acme.example.com',
        'auth_type' => 'basic',
        'auth_username' => 'planner-user',
        'auth_password' => 'planner-pass',
        'products_path' => '/products',
        'sales_path' => '/sales',
        'is_active' => true,
    ], $overrides);
}
