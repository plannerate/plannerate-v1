<?php

use App\Services\Integrations\Support\PersistImportedSalesService;

test('sales rows are deduplicated by deterministic id before upsert', function (): void {
    $service = (new ReflectionClass(PersistImportedSalesService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($service, 'deduplicateRowsById');

    $rows = $method->invoke($service, [
        [
            'id' => 'sale-1',
            'total_sale_quantity' => 1,
        ],
        [
            'id' => 'sale-2',
            'total_sale_quantity' => 5,
        ],
        [
            'id' => 'sale-1',
            'total_sale_quantity' => 3,
        ],
    ]);

    expect($rows)->toHaveCount(2)
        ->and($rows)->sequence(
            fn ($row) => $row
                ->toMatchArray([
                    'id' => 'sale-1',
                    'total_sale_quantity' => 3,
                ]),
            fn ($row) => $row
                ->toMatchArray([
                    'id' => 'sale-2',
                    'total_sale_quantity' => 5,
                ]),
        );
});
