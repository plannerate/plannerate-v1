<?php

use App\Services\Plannerate\IAGenerate\IAResponseParserService;

uses(Tests\TestCase::class);

it('adds warning when repeated sku is dispersed across non-adjacent shelves', function () {
    $service = app(IAResponseParserService::class);

    $response = json_encode([
        'reasoning' => 'Teste de dispersao',
        'confidence' => 0.9,
        'allocation' => [
            [
                'shelf_id' => 'shelf-1',
                'products' => [
                    ['product_id' => 'prod-A', 'facings' => 1, 'position_x' => 0],
                    ['product_id' => 'prod-B', 'facings' => 1, 'position_x' => 10],
                ],
            ],
            [
                'shelf_id' => 'shelf-2',
                'products' => [
                    ['product_id' => 'prod-C', 'facings' => 1, 'position_x' => 0],
                ],
            ],
            [
                'shelf_id' => 'shelf-3',
                'products' => [
                    ['product_id' => 'prod-A', 'facings' => 1, 'position_x' => 0],
                ],
            ],
        ],
        'summary' => [
            'total_allocated' => 4,
            'total_unallocated' => 0,
            'shelves_used' => 3,
            'avg_occupancy' => 80,
            'warnings' => [],
            'recommendations' => [],
        ],
    ]);

    $result = $service->parseResponse($response, 1.0, 100);

    $hasDispersionWarning = collect($result->metadata['warnings'] ?? [])
        ->contains(fn (string $warning) => str_contains($warning, 'SKU repetidos com') && str_contains($warning, 'detectada'));

    expect($hasDispersionWarning)->toBeTrue();

    expect($result->metadata['quality_score'])->toBeLessThan(100);
});

it('does not add dispersion warning when repeated sku stays adjacent', function () {
    $service = app(IAResponseParserService::class);

    $response = json_encode([
        'reasoning' => 'Teste de bloco vertical',
        'confidence' => 0.9,
        'allocation' => [
            [
                'shelf_id' => 'shelf-1',
                'products' => [
                    ['product_id' => 'prod-A', 'facings' => 2, 'position_x' => 0],
                ],
            ],
            [
                'shelf_id' => 'shelf-2',
                'products' => [
                    ['product_id' => 'prod-A', 'facings' => 2, 'position_x' => 0],
                ],
            ],
            [
                'shelf_id' => 'shelf-3',
                'products' => [
                    ['product_id' => 'prod-B', 'facings' => 1, 'position_x' => 0],
                ],
            ],
        ],
        'summary' => [
            'total_allocated' => 3,
            'total_unallocated' => 0,
            'shelves_used' => 3,
            'avg_occupancy' => 80,
            'warnings' => [],
            'recommendations' => [],
        ],
    ]);

    $result = $service->parseResponse($response, 1.0, 100);

    $hasDispersionWarning = collect($result->metadata['warnings'] ?? [])
        ->contains(fn (string $warning) => str_contains($warning, 'SKU repetidos com') && str_contains($warning, 'detectada'));

    expect($hasDispersionWarning)->toBeFalse();
});

it('detects non-adjacent repeated sku by physical shelf order metadata', function () {
    $service = app(IAResponseParserService::class);

    $response = json_encode([
        'reasoning' => 'Teste de adjacencia fisica',
        'confidence' => 0.9,
        'allocation' => [
            [
                'shelf_id' => 'shelf-A1',
                'products' => [
                    ['product_id' => 'prod-A', 'facings' => 1, 'position_x' => 0],
                ],
            ],
            [
                'shelf_id' => 'shelf-A3',
                'products' => [
                    ['product_id' => 'prod-A', 'facings' => 1, 'position_x' => 0],
                ],
            ],
        ],
        'summary' => [
            'total_allocated' => 2,
            'total_unallocated' => 0,
            'shelves_used' => 2,
            'avg_occupancy' => 80,
            'warnings' => [],
            'recommendations' => [],
        ],
    ]);

    $shelfMetadata = [
        'shelf-A1' => ['section_id' => 'section-A', 'section_order' => 0, 'shelf_order' => 0, 'shelf_position' => 10],
        'shelf-A3' => ['section_id' => 'section-A', 'section_order' => 0, 'shelf_order' => 2, 'shelf_position' => 30],
    ];

    $result = $service->parseResponse($response, 1.0, 100, $shelfMetadata);

    $hasDispersionWarning = collect($result->metadata['warnings'] ?? [])
        ->contains(fn (string $warning) => str_contains($warning, 'SKU repetidos com') && str_contains($warning, 'detectada'));

    expect($hasDispersionWarning)->toBeTrue();
});

it('recovers json when control character appears in payload', function () {
    $service = app(IAResponseParserService::class);

    $response = '{"reasoning":"ok","confidence":0.9,"allocation":[],'
        .chr(1)
        .'"summary":{"total_allocated":0,"total_unallocated":0,"shelves_used":0,"avg_occupancy":0,"warnings":[],"recommendations":[]}}';

    $result = $service->parseResponse($response, 1.0, 100);

    expect($result->confidence)->toBe(0.9);
    expect($result->totalAllocated)->toBe(0);
});
