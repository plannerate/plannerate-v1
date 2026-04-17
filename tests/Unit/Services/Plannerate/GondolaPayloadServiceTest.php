<?php

use App\Models\Editor\Gondola;
use App\Services\Plannerate\GondolaPayloadService;

uses(Tests\TestCase::class);

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $relations
 */
function fakeRelationNode(array $attributes, array $relations = []): object
{
    return new class($attributes, $relations)
    {
        /** @param  array<string, mixed>  $attributes */
        /** @param  array<string, mixed>  $relations */
        public function __construct(private array $attributes, private array $relations) {}

        public function relationLoaded(string $relation): bool
        {
            return array_key_exists($relation, $this->relations);
        }

        public function __get(string $name): mixed
        {
            if (array_key_exists($name, $this->relations)) {
                return $this->relations[$name];
            }

            return $this->attributes[$name] ?? null;
        }
    };
}

it('builds editor payload with planogram category and computed section dimensions', function () {
    $service = app(GondolaPayloadService::class);

    $product = fakeRelationNode([
        'id' => 'prod-1',
        'name' => 'Detergente',
        'code' => 'DET-1',
        'ean' => '789123',
        'barcode' => '789123',
        'image' => 'products/detergente.png',
        'image_url' => '/storage/products/detergente.png',
        'width' => 10,
        'height' => 20,
        'depth' => 5,
        'weight' => 0.5,
        'brand' => 'Marca X',
        'price' => 9.99,
        'status' => 'published',
        'has_dimensions' => true,
    ]);

    $layer = fakeRelationNode([
        'id' => 'layer-1',
        'segment_id' => 'segment-1',
        'product_id' => 'prod-1',
        'quantity' => 1,
        'height' => 20,
        'alignment' => 'left',
        'spacing' => 0,
        'deleted_at' => null,
    ], [
        'product' => $product,
    ]);

    $segment = fakeRelationNode([
        'id' => 'segment-1',
        'shelf_id' => 'shelf-1',
        'layer_id' => 'layer-1',
        'width' => 100,
        'height' => 20,
        'depth' => 40,
        'position_x' => 0,
        'position_y' => 0,
        'facings' => 1,
        'quantity' => 1,
        'ordering' => 1,
        'position' => 1,
        'deleted_at' => null,
    ], [
        'layer' => $layer,
    ]);

    $shelf = fakeRelationNode([
        'id' => 'shelf-1',
        'section_id' => 'section-1',
        'code' => 'SHELF-1',
        'shelf_width' => 130,
        'shelf_height' => 4,
        'shelf_depth' => 40,
        'shelf_position' => 80,
        'ordering' => 1,
        'alignment' => 'justify',
        'product_type' => 'normal',
        'deleted_at' => null,
    ], [
        'segments' => collect([$segment]),
    ]);

    $section = fakeRelationNode([
        'id' => 'section-1',
        'gondola_id' => 'gondola-1',
        'name' => '1# Sessao',
        'code' => 'SEC-1',
        'width' => 130,
        'height' => 170,
        'num_shelves' => 1,
        'base_height' => 12,
        'base_depth' => 40,
        'base_width' => 130,
        'cremalheira_width' => 3,
        'ordering' => 1,
        'hole_height' => 1.8,
        'hole_spacing' => 0.5,
        'hole_width' => 1.5,
        'settings' => ['holes' => []],
        'alignment' => null,
        'deleted_at' => null,
    ], [
        'shelves' => collect([$shelf]),
    ]);

    $category = fakeRelationNode([
        'id' => 'cat-1',
        'tenant_id' => 'tenant-1',
        'category_id' => 'parent-1',
        'name' => 'LIMPEZA',
        'slug' => 'limpeza',
        'level_name' => 'departamento',
        'codigo' => 2,
        'status' => 'published',
        'nivel' => '2',
        'hierarchy_position' => 2,
        'full_path' => 'SUPERMERCADO > LIMPEZA',
        'hierarchy_path' => 'SUPERMERCADO',
        'is_placeholder' => false,
    ]);

    $relatedGondola = fakeRelationNode([
        'id' => 'gondola-related',
        'name' => 'Lado B',
        'route_gondolas' => '/gondolas/gondola-related/edit',
    ]);

    $planogram = fakeRelationNode([
        'id' => 'plan-1',
        'tenant_id' => 'tenant-1',
        'client_id' => 'client-1',
        'store_id' => null,
        'cluster_id' => null,
        'name' => 'Planograma Limpeza',
        'slug' => 'planograma-limpeza',
        'type' => 'planograma',
        'category_id' => 'cat-1',
        'status' => 'published',
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'created_at' => now(),
        'updated_at' => now(),
        'getStartMonthInput' => '2026-03',
        'getEndMonthInput' => '2026-04',
    ], [
        'category' => $category,
        'gondolas' => collect([$relatedGondola]),
    ]);

    $planogram = new class($planogram)
    {
        public function __construct(private object $payload) {}

        public function relationLoaded(string $relation): bool
        {
            return $this->payload->relationLoaded($relation);
        }

        public function __get(string $name): mixed
        {
            return $this->payload->{$name};
        }

        public function getStartMonthInput(): ?string
        {
            return $this->payload->getStartMonthInput;
        }

        public function getEndMonthInput(): ?string
        {
            return $this->payload->getEndMonthInput;
        }
    };

    $gondola = new Gondola;
    $gondola->setRawAttributes([
        'id' => 'gondola-1',
        'name' => 'Lado A',
        'slug' => 'lado-a',
        'scale_factor' => 3,
        'status' => 'published',
        'num_modulos' => 1,
        'side' => 'A',
        'alignment' => 'justify',
        'location' => 'Center',
        'flow' => 'left_to_right',
        'height' => 170,
        'width' => 130,
        'depth' => 40,
        'planogram_id' => 'plan-1',
        'linked_map_gondola_id' => null,
        'linked_map_gondola_category' => null,
    ], true);

    $gondola->setRelation('planogram', $planogram);
    $gondola->setRelation('sections', collect([$section]));

    $payload = $service->buildEditorPayload($gondola);

    expect($payload)
        ->toHaveKey('planogram.category.name', 'LIMPEZA')
        ->and($payload['planogram']['category'])
        ->toHaveKeys(['id', 'slug', 'full_path', 'hierarchy_path'])
        ->and($payload['sections'][0]['section_width'])
        ->toEqual(141.0)
        ->and($payload['sections'][0]['section_height'])
        ->toEqual(510.0)
        ->and($payload['sections'][0]['shelves'][0]['segments'][0]['layer']['product']['id'])
        ->toBe('prod-1')
        ->and($payload['tenant'])
        ->toHaveKeys(['id', 'name', 'settings']);
});
