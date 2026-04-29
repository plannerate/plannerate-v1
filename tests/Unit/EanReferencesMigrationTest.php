<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('ean references table includes product dimension columns', function (): void {
    $dimensionColumns = [
        'width',
        'height',
        'depth',
        'weight',
        'unit',
        'has_dimensions',
        'dimension_status',
    ];

    Artisan::call('migrate:fresh', [
        '--path' => 'database/migrations/2026_04_27_223000_create_ean_references_table.php',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    foreach ($dimensionColumns as $column) {
        expect(Schema::hasColumn('ean_references', $column))->toBeFalse();
    }

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_04_29_121122_add_dimension_fields_to_ean_references_table.php',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    foreach ($dimensionColumns as $column) {
        expect(Schema::hasColumn('ean_references', $column))->toBeTrue();
    }

    Artisan::call('migrate:rollback', [
        '--path' => 'database/migrations/2026_04_29_121122_add_dimension_fields_to_ean_references_table.php',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    foreach ($dimensionColumns as $column) {
        expect(Schema::hasColumn('ean_references', $column))->toBeFalse();
    }
});
