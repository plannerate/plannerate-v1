<?php

use App\Models\IntegrationApi;
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

test('authenticated user can list integration apis', function (): void {
    $response = $this->get(route('landlord.integration-apis.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/integration-apis/Index')
            ->has('filters'));
});

test('authenticated user can create update and delete integration api', function (): void {
    $createResponse = $this->post(route('landlord.integration-apis.store'), integrationApiPayload());

    $createResponse->assertRedirect(route('landlord.integration-apis.index'));

    $api = IntegrationApi::query()->where('slug', 'acme')->firstOrFail();

    expect($api->requests['method'] ?? null)->toBe('GET')
        ->and($api->response['items_path'] ?? null)->toBe('data');

    $updateResponse = $this->put(route('landlord.integration-apis.update', $api), integrationApiPayload([
        'name' => 'ACME ERP',
        'slug' => 'acme-erp',
        'is_active' => '0',
    ]));

    $updateResponse->assertRedirect(route('landlord.integration-apis.index'));

    $this->assertDatabaseHas('integration_apis', [
        'id' => $api->id,
        'name' => 'ACME ERP',
        'slug' => 'acme-erp',
        'is_active' => 0,
    ], 'landlord');

    $deleteResponse = $this->delete(route('landlord.integration-apis.destroy', $api));

    $deleteResponse->assertRedirect(route('landlord.integration-apis.index'));

    $this->assertSoftDeleted('integration_apis', [
        'id' => $api->id,
    ], 'landlord');
});

test('integration api requires valid json configs', function (): void {
    $response = $this
        ->from(route('landlord.integration-apis.create'))
        ->post(route('landlord.integration-apis.store'), integrationApiPayload([
            'requests_json' => '{oops',
            'response_json' => '{oops',
        ]));

    $response
        ->assertRedirect(route('landlord.integration-apis.create'))
        ->assertSessionHasErrors(['requests_json', 'response_json']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function integrationApiPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'ACME',
        'slug' => 'acme',
        'description' => 'API ACME para importacao',
        'requests_json' => json_encode([
            'method' => 'GET',
            'payload' => 'query',
            'products' => [
                'fallback_path' => '/products',
            ],
        ], JSON_THROW_ON_ERROR),
        'response_json' => json_encode([
            'items_path' => 'data',
            'pagination' => [
                'last_page_path' => 'pagination.last_page',
            ],
        ], JSON_THROW_ON_ERROR),
        'is_active' => '1',
    ], $overrides);
}
