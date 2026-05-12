<?php

use App\Models\IntegrationApi;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

    $api = IntegrationApi::query()->where('slug', 'acme')->firstOrFail();

    $createResponse->assertRedirect(route('landlord.integration-apis.edit', $api));

    expect($api->requests['method'] ?? null)->toBe('GET')
        ->and($api->requests)->not->toHaveKey('payload')
        ->and($api->requests['paths']['products']['field_map'][0]['target'] ?? null)->toBe('codigo_erp')
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

test('integration api moves legacy top level resources into paths', function (): void {
    $response = $this->post(route('landlord.integration-apis.store'), integrationApiPayload([
        'requests_json' => json_encode([
            'method' => 'POST',
            'payload' => 'body',
            'products' => [
                'target_table' => 'products',
                'fallback_path' => '/hubprodutos.listar_produtos',
                'field_map' => [
                    [
                        'target' => 'codigo_erp',
                        'source' => 'produto',
                        'transforms' => ['string'],
                    ],
                ],
            ],
            'sales' => [
                'target_table' => 'sales',
                'fallback_path' => '/hubvendas.vendas_produtos',
            ],
        ], JSON_THROW_ON_ERROR),
    ]));

    $api = IntegrationApi::query()->where('slug', 'acme')->firstOrFail();

    $response->assertRedirect(route('landlord.integration-apis.edit', $api));

    expect($api->requests)
        ->toHaveKey('paths')
        ->not->toHaveKey('products')
        ->not->toHaveKey('sales')
        ->and($api->requests['paths'])->toHaveKey('products')
        ->and($api->requests['paths'])->toHaveKey('sales')
        ->and($api->requests['paths']['products']['field_map'][0]['target'])->toBe('codigo_erp');
});

test('authenticated user can export integration api configurations', function (): void {
    IntegrationApi::query()->create([
        'name' => 'ACME',
        'slug' => 'acme',
        'description' => 'API ACME',
        'requests' => ['method' => 'GET', 'paths' => ['products' => ['target_table' => 'products']]],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    $response = $this->get(route('landlord.integration-apis.export'));

    $response->assertOk();

    $payload = json_decode($response->streamedContent(), true);

    expect($payload)
        ->toBeArray()
        ->toHaveKey('integration_apis')
        ->and($payload['integration_apis'])->toBeArray()
        ->and($payload['integration_apis'][0]['slug'] ?? null)->toBe('acme');
});

test('authenticated user can import integration api configurations', function (): void {
    IntegrationApi::query()->create([
        'name' => 'Legacy ACME',
        'slug' => 'acme',
        'description' => 'Legacy',
        'requests' => ['method' => 'POST'],
        'response' => ['items_path' => 'legacy'],
        'is_active' => false,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'integration-apis.json',
        json_encode([
            'version' => 1,
            'integration_apis' => [
                [
                    'name' => 'ACME Atualizada',
                    'slug' => 'acme',
                    'description' => 'Nova descricao',
                    'requests' => ['method' => 'GET', 'paths' => ['products' => ['target_table' => 'products']]],
                    'response' => ['items_path' => 'data'],
                    'is_active' => true,
                ],
                [
                    'name' => 'Nova API',
                    'slug' => 'new-api',
                    'description' => 'Importada',
                    'requests' => ['method' => 'GET'],
                    'response' => ['items_path' => 'rows'],
                    'is_active' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR)
    );

    $response = $this->post(route('landlord.integration-apis.import'), [
        'spreadsheet' => $file,
    ]);

    $response->assertRedirect(route('landlord.integration-apis.index'));

    $this->assertDatabaseHas('integration_apis', [
        'slug' => 'acme',
        'name' => 'ACME Atualizada',
        'is_active' => 1,
    ], 'landlord');

    $this->assertDatabaseHas('integration_apis', [
        'slug' => 'new-api',
        'name' => 'Nova API',
        'is_active' => 1,
    ], 'landlord');
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
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/products',
                    'field_map' => [
                        [
                            'target' => 'codigo_erp',
                            'source' => 'id',
                            'transforms' => ['string'],
                        ],
                    ],
                ],
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
