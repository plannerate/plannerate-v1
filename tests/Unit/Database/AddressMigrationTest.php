<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('tenant and landlord addresses tables are created with expected columns', function (): void {
    Artisan::call('migrate:fresh', [
        '--path' => 'database/migrations/2026_04_23_180000_create_addresses_table.php',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord/2026_04_23_180001_create_addresses_table.php',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    expect(Schema::hasTable('addresses'))->toBeTrue();
    expect(Schema::connection('landlord')->hasTable('addresses'))->toBeTrue();

    foreach ([
        'id',
        'type',
        'tenant_id',
        'user_id',
        'addressable_type',
        'addressable_id',
        'name',
        'zip_code',
        'street',
        'number',
        'complement',
        'reference',
        'additional_information',
        'district',
        'city',
        'country',
        'state',
        'is_default',
        'status',
        'created_at',
        'updated_at',
        'deleted_at',
    ] as $column) {
        expect(Schema::hasColumn('addresses', $column))->toBeTrue();
        expect(Schema::connection('landlord')->hasColumn('addresses', $column))->toBeTrue();
    }
});
