<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('it upserts ean references from legacy dimensions table', function (): void {
    $legacyPath = database_path('testing_mysql_legacy.sqlite');
    $tenantPath = database_path('testing_tenant.sqlite');

    if (! file_exists($legacyPath)) {
        touch($legacyPath);
    }

    if (! file_exists($tenantPath)) {
        touch($tenantPath);
    }

    Config::set('database.connections.mysql_legacy', [
        'driver' => 'sqlite',
        'database' => $legacyPath,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $tenantPath,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    DB::purge('mysql_legacy');
    DB::purge('tenant');

    $legacyConnection = DB::connection('mysql_legacy');
    $tenantConnection = DB::connection('tenant');

    $legacyConnection->getPdo();
    $tenantConnection->getPdo();

    Schema::connection('mysql_legacy')->dropIfExists('dimensions');
    Schema::connection('tenant')->dropIfExists('ean_references');

    Schema::connection('mysql_legacy')->create('dimensions', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('ean')->nullable();
        $table->decimal('width', 10, 2)->nullable();
        $table->decimal('height', 10, 2)->nullable();
        $table->decimal('depth', 10, 2)->nullable();
        $table->decimal('weight', 10, 2)->nullable();
        $table->string('unit')->nullable();
        $table->string('status')->nullable();
        $table->string('client_id')->nullable();
    });

    Schema::connection('tenant')->create('ean_references', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('tenant_id');
        $table->string('ean');
        $table->decimal('width', 10, 2)->nullable();
        $table->decimal('height', 10, 2)->nullable();
        $table->decimal('depth', 10, 2)->nullable();
        $table->decimal('weight', 10, 2)->nullable();
        $table->string('unit')->default('cm');
        $table->boolean('has_dimensions')->default(false);
        $table->string('dimension_status')->default('draft');
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
        $table->unique(['tenant_id', 'ean']);
    });

    $legacyConnection->table('dimensions')->insert([
        [
            'ean' => '31',
            'width' => 17.00,
            'height' => 21.00,
            'depth' => 3.50,
            'weight' => null,
            'unit' => 'CM',
            'status' => null,
            'client_id' => '10',
        ],
        [
            'ean' => '192',
            'width' => 8.50,
            'height' => 18.50,
            'depth' => 3.90,
            'weight' => null,
            'unit' => null,
            'status' => 'published',
            'client_id' => '10',
        ],
        [
            'ean' => '999',
            'width' => 1.00,
            'height' => 1.00,
            'depth' => 1.00,
            'weight' => 1.00,
            'unit' => 'cm',
            'status' => 'published',
            'client_id' => '20',
        ],
    ]);

    $tenantConnection->table('ean_references')->insert([
        'id' => 'existing-id',
        'tenant_id' => 'tenant-1',
        'ean' => '31',
        'width' => 1.00,
        'height' => 1.00,
        'depth' => 1.00,
        'weight' => 1.00,
        'unit' => 'cm',
        'has_dimensions' => false,
        'dimension_status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    $exitCode = Artisan::call('sync:import-legacy-dimensions-to-ean-references', [
        '--tenant-id' => 'tenant-1',
        '--chunk' => 100,
    ]);

    expect($exitCode)->toBe(0);

    $rows = $tenantConnection->table('ean_references')
        ->where('tenant_id', 'tenant-1')
        ->orderBy('ean')
        ->get();

    expect($rows)->toHaveCount(3);

    $updated = $rows->firstWhere('ean', '31');
    expect((float) $updated->width)->toBe(17.0)
        ->and((float) $updated->height)->toBe(21.0)
        ->and((float) $updated->depth)->toBe(3.5)
        ->and($updated->deleted_at)->toBeNull();

    $inserted = $rows->firstWhere('ean', '192');
    expect($inserted)->not->toBeNull()
        ->and((float) $inserted->width)->toBe(8.5)
        ->and($inserted->dimension_status)->toBe('published');

    $insertedFromAnotherClient = $rows->firstWhere('ean', '999');
    expect($insertedFromAnotherClient)->not->toBeNull()
        ->and((float) $insertedFromAnotherClient->width)->toBe(1.0);
});
