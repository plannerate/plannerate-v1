<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('landlord ean references table includes hybrid catalog columns', function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    expect(Schema::connection('landlord')->hasTable('ean_references'))->toBeTrue();

    foreach ([
        'ean',
        'category_id',
        'category_name',
        'category_slug',
        'reference_description',
        'brand',
        'subbrand',
        'packaging_type',
        'packaging_size',
        'measurement_unit',
        'width',
        'height',
        'depth',
        'weight',
        'unit',
        'has_dimensions',
        'dimension_status',
        'image_front_url',
        'image_side_url',
        'image_top_url',
        'metadata',
    ] as $column) {
        expect(Schema::connection('landlord')->hasColumn('ean_references', $column))->toBeTrue();
    }
});
