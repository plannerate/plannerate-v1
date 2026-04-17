<?php

use App\Models\Client;
use App\Models\Editor\Gondola;
use App\Models\Planogram;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plannerate\GondolaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Cria tenant e client para o teste
    $this->tenant = Tenant::factory()->create();
    $this->client = Client::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Cria um usuário autenticado
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Seta o client no config
    config(['app.current_client_id' => $this->client->id]);

    // Cria uma store com mapa
    $this->store = Store::factory()->create([
        'tenant_id' => $this->tenant->id,
        'client_id' => $this->client->id,
        'map_image_path' => 'store-maps/test/map-test.webp',
        'map_regions' => [
            [
                'id' => 'region-1',
                'label' => 'Entrada',
                'type' => 'rect',
                'color' => '#FF0000',
                'x' => 100,
                'y' => 100,
                'width' => 200,
                'height' => 150,
            ],
            [
                'id' => 'region-2',
                'label' => 'Corredor 1',
                'type' => 'rect',
                'color' => '#00FF00',
                'x' => 300,
                'y' => 100,
                'width' => 200,
                'height' => 150,
            ],
        ],
    ]);

    // Cria um planograma associado à store
    $this->planogram = Planogram::factory()->create([
        'tenant_id' => $this->tenant->id,
        'client_id' => $this->client->id,
        'store_id' => $this->store->id,
    ]);

    // Cria uma gôndola
    $this->gondola = Gondola::factory()->create([
        'tenant_id' => $this->tenant->id,
        'planogram_id' => $this->planogram->id,
    ]);
});

it('can link a gondola to a map region', function () {
    $service = app(GondolaService::class);

    $result = $service->update($this->gondola->id, [
        'linked_map_gondola_id' => 'region-1',
        'linked_map_gondola_category' => 'rect',
    ]);

    expect($result)->toBeTrue();

    $this->gondola->refresh();

    expect($this->gondola->linked_map_gondola_id)->toBe('region-1')
        ->and($this->gondola->linked_map_gondola_category)->toBe('rect');
});

it('can unlink a gondola from a map region', function () {
    // Primeiro vincula
    $this->gondola->update([
        'linked_map_gondola_id' => 'region-1',
        'linked_map_gondola_category' => 'rect',
    ]);

    $service = app(GondolaService::class);

    // Desvincula
    $result = $service->update($this->gondola->id, [
        'linked_map_gondola_id' => null,
        'linked_map_gondola_category' => null,
    ]);

    expect($result)->toBeTrue();

    $this->gondola->refresh();

    expect($this->gondola->linked_map_gondola_id)->toBeNull()
        ->and($this->gondola->linked_map_gondola_category)->toBeNull();
});

it('processes gondola_update change type correctly', function () {
    $service = app(GondolaService::class);

    $change = [
        'type' => 'gondola_update',
        'entityType' => 'gondola',
        'entityId' => $this->gondola->id,
        'data' => [
            'linked_map_gondola_id' => 'region-2',
            'linked_map_gondola_category' => 'rect',
        ],
    ];

    $result = $service->createOrUpdate($change);

    expect($result)->toBeTrue();

    $this->gondola->refresh();

    expect($this->gondola->linked_map_gondola_id)->toBe('region-2')
        ->and($this->gondola->linked_map_gondola_category)->toBe('rect');
});

it('loads store map data when rendering planogram editor', function () {
    $this->actingAs($this->user);

    $response = $this->get("/plannograma/{$this->planogram->id}/editor");

    $response->assertStatus(200);

    // Verifica se os dados da store estão presentes
    $response->assertInertia(fn ($page) => $page
        ->has('record.store')
        ->has('record.store.map_image_path')
        ->has('record.store.map_regions')
    );
});
