<?php

use App\Models\IntegrationApi;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $this->actingAs(User::factory()->create());
});

test('authenticated user can list tenants', function () {
    $response = $this->get(route('landlord.tenants.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/tenants/Index')
            ->has('tenants.data'));
});

test('authenticated user can create and update tenant with primary domain', function () {
    $plan = Plan::query()->create([
        'name' => 'Plano Inicial',
        'slug' => 'plano-inicial',
        'price_cents' => 1000,
        'is_active' => true,
    ]);
    $moduleA = Module::query()->create([
        'name' => 'Modulo A',
        'slug' => 'modulo-a',
        'is_active' => true,
    ]);
    $moduleB = Module::query()->create([
        'name' => 'Modulo B',
        'slug' => 'modulo-b',
        'is_active' => true,
    ]);

    $createResponse = $this->post(route('landlord.tenants.store'), [
        'name' => 'Tenant Alfa',
        'slug' => 'tenant-alfa',
        'database' => 'tenant_alfa',
        'status' => 'active',
        'plan_id' => $plan->id,
        'module_ids' => [$moduleA->id],
        'user_limit' => 20,
        'host' => 'alfa.plannerate-v1.test',
        'domain_is_active' => '1',
    ]);

    $tenant = Tenant::query()->where('slug', 'tenant-alfa')->firstOrFail();
    $createResponse->assertRedirect(route('landlord.tenants.setup', $tenant));

    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'database' => 'tenant_alfa',
        'status' => 'active',
        'plan_id' => $plan->id,
    ], 'landlord');

    $this->assertDatabaseHas('tenant_domains', [
        'tenant_id' => $tenant->id,
        'host' => 'alfa.plannerate-v1.test',
        'is_primary' => 1,
        'is_active' => 1,
    ], 'landlord');
    $this->assertDatabaseHas('tenant_modules', [
        'tenant_id' => $tenant->id,
        'module_id' => $moduleA->id,
    ], 'landlord');

    $updateResponse = $this->put(route('landlord.tenants.update', $tenant), [
        'name' => 'Tenant Alfa Editado',
        'slug' => 'tenant-alfa-editado',
        'database' => 'tenant_alfa_editado',
        'status' => 'suspended',
        'plan_id' => $plan->id,
        'module_ids' => [$moduleB->id],
        'user_limit' => 30,
        'host' => 'alfa-novo.plannerate-v1.test',
        'domain_is_active' => '0',
    ]);

    $updateResponse->assertRedirect(route('landlord.tenants.index'));

    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'slug' => 'tenant-alfa-editado',
        'database' => 'tenant_alfa_editado',
        'status' => 'suspended',
    ], 'landlord');

    $this->assertDatabaseHas('tenant_domains', [
        'tenant_id' => $tenant->id,
        'host' => 'alfa-novo.plannerate-v1.test',
        'is_primary' => 1,
        'is_active' => 0,
    ], 'landlord');
    $this->assertDatabaseHas('tenant_modules', [
        'tenant_id' => $tenant->id,
        'module_id' => $moduleB->id,
    ], 'landlord');
    $this->assertDatabaseMissing('tenant_modules', [
        'tenant_id' => $tenant->id,
        'module_id' => $moduleA->id,
    ], 'landlord');
});

test('tenant validation enforces unique slug database and host', function () {
    $first = Tenant::query()->create([
        'name' => 'Tenant A',
        'slug' => 'tenant-a',
        'database' => 'tenant_a',
        'status' => 'active',
    ]);

    $first->domains()->create([
        'host' => 'tenant-a.plannerate-v1.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $second = Tenant::query()->create([
        'name' => 'Tenant B',
        'slug' => 'tenant-b',
        'database' => 'tenant_b',
        'status' => 'active',
    ]);

    $second->domains()->create([
        'host' => 'tenant-b.plannerate-v1.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $response = $this->from(route('landlord.tenants.edit', $second))
        ->put(route('landlord.tenants.update', $second), [
            'name' => 'Tenant B',
            'slug' => 'tenant-a',
            'database' => 'tenant_a',
            'status' => 'active',
            'plan_id' => '',
            'user_limit' => '',
            'host' => 'tenant-a.plannerate-v1.test',
            'domain_is_active' => '1',
        ]);

    $response
        ->assertRedirect(route('landlord.tenants.edit', $second))
        ->assertSessionHasErrors(['slug', 'database', 'host']);
});

test('authenticated user can export filtered tenant configurations', function () {
    IntegrationApi::query()->create([
        'name' => 'API ACME',
        'slug' => 'acme',
        'requests' => ['method' => 'GET'],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    $tenantA = Tenant::query()->create([
        'name' => 'Tenant Alpha',
        'slug' => 'tenant-alpha',
        'database' => 'tenant_alpha',
        'status' => 'active',
    ]);
    $tenantA->domains()->create([
        'host' => 'alpha.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);
    TenantIntegration::query()->create([
        'tenant_id' => $tenantA->id,
        'integration_type' => 'acme',
        'config' => ['connection' => ['base_url' => 'https://alpha.api']],
        'is_active' => true,
    ]);

    $tenantB = Tenant::query()->create([
        'name' => 'Tenant Beta',
        'slug' => 'tenant-beta',
        'database' => 'tenant_beta',
        'status' => 'inactive',
    ]);
    $tenantB->domains()->create([
        'host' => 'beta.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('landlord.tenants.export', [
        'search' => 'alpha',
        'status' => 'active',
    ]));

    $response->assertOk();

    $payload = json_decode($response->streamedContent(), true);

    expect($payload)
        ->toBeArray()
        ->toHaveKey('tenants')
        ->toHaveKey('integration_apis')
        ->and($payload['tenants'])->toHaveCount(1)
        ->and($payload['tenants'][0]['slug'] ?? null)->toBe('tenant-alpha')
        ->and($payload['integration_apis'][0]['slug'] ?? null)->toBe('acme');
});

test('authenticated user can import tenant configurations in batch', function () {
    $existing = Tenant::query()->create([
        'name' => 'Tenant Legacy',
        'slug' => 'tenant-alpha',
        'database' => 'tenant_alpha_old',
        'status' => 'inactive',
    ]);
    $existing->domains()->create([
        'host' => 'legacy-alpha.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => false,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'tenant-configs.json',
        json_encode([
            'version' => 1,
            'integration_apis' => [
                [
                    'name' => 'API ACME',
                    'slug' => 'acme',
                    'requests' => ['method' => 'GET'],
                    'response' => ['items_path' => 'data'],
                    'is_active' => true,
                ],
            ],
            'tenants' => [
                [
                    'name' => 'Tenant Alpha Atualizado',
                    'slug' => 'tenant-alpha',
                    'database' => 'tenant_alpha_new',
                    'status' => 'active',
                    'primary_domain' => [
                        'host' => 'alpha.test',
                        'type' => 'subdomain',
                        'is_primary' => true,
                        'is_active' => true,
                    ],
                    'integration' => [
                        'integration_type' => 'acme',
                        'identifier' => '123',
                        'config' => ['paths' => ['products' => '/products']],
                        'is_active' => true,
                    ],
                ],
                [
                    'name' => 'Tenant Novo',
                    'slug' => 'tenant-new',
                    'database' => 'tenant_new',
                    'status' => 'provisioning',
                    'primary_domain' => [
                        'host' => 'new.test',
                        'type' => 'subdomain',
                        'is_primary' => true,
                        'is_active' => true,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    );

    $response = $this->post(route('landlord.tenants.import'), [
        'spreadsheet' => $file,
    ]);

    $response->assertRedirect(route('landlord.tenants.index'));

    $this->assertDatabaseHas('tenants', [
        'slug' => 'tenant-alpha',
        'name' => 'Tenant Alpha Atualizado',
        'database' => 'tenant_alpha_new',
        'status' => 'active',
    ], 'landlord');

    $this->assertDatabaseHas('tenant_domains', [
        'tenant_id' => $existing->id,
        'host' => 'alpha.test',
        'is_active' => 1,
    ], 'landlord');

    $this->assertDatabaseHas('tenant_integrations', [
        'tenant_id' => $existing->id,
        'integration_type' => 'acme',
        'identifier' => '123',
        'is_active' => 1,
    ], 'landlord');

    $this->assertDatabaseHas('tenants', [
        'slug' => 'tenant-new',
        'name' => 'Tenant Novo',
        'database' => 'tenant_new',
    ], 'landlord');

    $this->assertDatabaseHas('integration_apis', [
        'slug' => 'acme',
        'name' => 'API ACME',
        'is_active' => 1,
    ], 'landlord');
});
