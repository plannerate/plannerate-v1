<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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

test('edit renders the gondola defaults page with the plannerate system default when tenant has none', function () {
    $tenant = createTenantForGondolaDefaults();

    $response = $this->get(route('landlord.tenants.gondola-defaults.edit', $tenant));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/tenants/GondolaDefaults')
            ->where('tenant.id', $tenant->id)
            ->where('defaults.height', config('plannerate.defaults.gondola.height'))
            ->where('defaults.numShelves', config('plannerate.defaults.gondola.numShelves'))
            ->where('system_defaults.height', config('plannerate.defaults.gondola.height')));
});

test('edit merges the tenant saved standard over the system default', function () {
    $tenant = createTenantForGondolaDefaults([
        'settings' => ['gondola' => ['height' => 240, 'numShelves' => 6]],
    ]);

    $response = $this->get(route('landlord.tenants.gondola-defaults.edit', $tenant));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaults.height', 240)
            ->where('defaults.numShelves', 6)
            // Campos não sobrescritos continuam vindo do padrão do sistema.
            ->where('defaults.width', config('plannerate.defaults.gondola.width'))
            ->where('system_defaults.height', config('plannerate.defaults.gondola.height')));
});

test('update persists the standard into settings.gondola and preserves other settings keys', function () {
    $tenant = createTenantForGondolaDefaults([
        'settings' => ['integration_flag' => true],
    ]);

    $response = $this->put(route('landlord.tenants.gondola-defaults.update', $tenant), gondolaDefaultsPayload([
        'height' => 220,
        'numShelves' => 5,
    ]));

    $response->assertRedirect();

    $tenant->refresh();

    expect($tenant->settings['gondola']['height'] ?? null)->toBe(220)
        ->and($tenant->settings['gondola']['numShelves'] ?? null)->toBe(5)
        ->and($tenant->settings['gondola']['productType'] ?? null)->toBe('normal')
        // Outras chaves de settings permanecem intactas.
        ->and($tenant->settings['integration_flag'] ?? null)->toBeTrue();
});

test('validation rejects invalid gondola standard values', function () {
    $tenant = createTenantForGondolaDefaults();

    $response = $this->from(route('landlord.tenants.gondola-defaults.edit', $tenant))
        ->put(route('landlord.tenants.gondola-defaults.update', $tenant), gondolaDefaultsPayload([
            'numShelves' => -1,
            'productType' => 'invalid',
            'flow' => 'sideways',
        ]));

    $response
        ->assertRedirect(route('landlord.tenants.gondola-defaults.edit', $tenant))
        ->assertSessionHasErrors(['numShelves', 'productType', 'flow']);
});

function createTenantForGondolaDefaults(array $attributes = []): Tenant
{
    $slug = 'tenant-gondola-'.fake()->unique()->numberBetween(100, 9999);

    /** @var Tenant $tenant */
    $tenant = Tenant::withoutEvents(function () use ($attributes, $slug): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'name' => 'Tenant Gondola',
            'slug' => $slug,
            'database' => (string) config('database.connections.landlord.database'),
            'status' => 'active',
        ], $attributes));

        // Domínio primário — exigido pelos shared props do Inertia
        // (HandleInertiaRequests resolve app.url a partir de tenant->domain->host).
        $tenant->domains()->create([
            'host' => $slug.'.plannerate.localhost',
            'type' => 'subdomain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        return $tenant;
    });

    return $tenant;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gondolaDefaultsPayload(array $overrides = []): array
{
    return array_merge([
        'location' => 'Corredor 1',
        'side' => 'A',
        'scaleFactor' => 3,
        'flow' => 'left_to_right',
        'height' => 200,
        'width' => 100,
        'numModules' => 4,
        'baseHeight' => 20,
        'baseWidth' => 100,
        'baseDepth' => 50,
        'rackWidth' => 4,
        'holeHeight' => 3,
        'holeWidth' => 2,
        'holeSpacing' => 2,
        'shelfHeight' => 4,
        'shelfWidth' => 100,
        'shelfDepth' => 40,
        'numShelves' => 4,
        'productType' => 'normal',
    ], $overrides);
}
