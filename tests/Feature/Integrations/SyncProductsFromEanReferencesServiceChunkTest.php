<?php

use App\Services\Integrations\Support\SyncProductsFromEanReferencesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * ATENÇÃO: este arquivo deve ter UM único teste. O beforeEach troca
 * database.default e purga conexões, o que quebra o setUp do RefreshDatabase
 * a partir do segundo teste do mesmo arquivo (ver memória do projeto).
 */
beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'database.default' => 'tenant',
        'database.connections.landlord' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'multitenancy.tenant_database_connection_name' => null,
    ]);

    DB::purge('landlord');
    DB::purge('tenant');

    Schema::connection('landlord')->create('ean_references', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('ean', 32)->unique();
        $table->ulid('category_id')->nullable();
        $table->string('category_name')->nullable();
        $table->string('category_slug')->nullable();
        $table->text('reference_description')->nullable();
        $table->string('brand')->nullable();
        $table->string('subbrand')->nullable();
        $table->string('packaging_type')->nullable();
        $table->string('packaging_size')->nullable();
        $table->string('measurement_unit')->nullable();
        $table->string('unit')->nullable();
        $table->string('dimension_publish_status')->nullable();
        $table->string('dimension_status')->nullable();
        $table->decimal('width', 10, 2)->nullable();
        $table->decimal('height', 10, 2)->nullable();
        $table->decimal('depth', 10, 2)->nullable();
        $table->decimal('weight', 10, 3)->nullable();
        $table->boolean('has_dimensions')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('categories', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->string('name')->nullable();
        $table->string('slug')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('products', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->ulid('category_id')->nullable();
        $table->string('name')->nullable();
        $table->string('ean')->nullable();
        $table->text('description')->nullable();
        $table->string('brand')->nullable();
        $table->string('subbrand')->nullable();
        $table->string('packaging_type')->nullable();
        $table->string('packaging_size')->nullable();
        $table->string('measurement_unit')->nullable();
        $table->string('unit')->nullable();
        $table->string('status')->nullable();
        $table->string('dimension_status')->nullable();
        $table->string('dimension_publish_status')->nullable();
        $table->decimal('width', 10, 2)->nullable();
        $table->decimal('height', 10, 2)->nullable();
        $table->decimal('depth', 10, 2)->nullable();
        $table->decimal('weight', 10, 3)->nullable();
        $table->boolean('has_dimensions')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });
});

test('sincroniza dimensões das ean_references em lote, dentro de transação por chunk', function (): void {
    $tenantId = (string) Str::ulid();

    $productWithReference = (string) Str::ulid();
    $productWithoutReference = (string) Str::ulid();

    DB::connection('tenant')->table('products')->insert([
        [
            'id' => $productWithReference,
            'tenant_id' => $tenantId,
            'name' => 'Com Referência',
            'ean' => '7891000000001',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $productWithoutReference,
            'tenant_id' => $tenantId,
            'name' => 'Sem Referência',
            'ean' => '7891000000099',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::connection('landlord')->table('ean_references')->insert([
        'id' => (string) Str::ulid(),
        'ean' => '7891000000001',
        'brand' => 'Marca X',
        'width' => 10.5,
        'height' => 20.0,
        'depth' => 5.25,
        'has_dimensions' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = (new SyncProductsFromEanReferencesService)->sync('tenant', $tenantId);

    expect($result['matched'])->toBe(1)
        ->and($result['updated'])->toBe(1)
        ->and($result['remaining'])->toBe(0);

    $updated = DB::connection('tenant')->table('products')->where('id', $productWithReference)->first();
    $untouched = DB::connection('tenant')->table('products')->where('id', $productWithoutReference)->first();

    expect((float) $updated->width)->toBe(10.5)
        ->and((float) $updated->height)->toBe(20.0)
        ->and((float) $updated->depth)->toBe(5.25)
        ->and($updated->brand)->toBe('Marca X')
        ->and($updated->status)->toBe('published')
        ->and($untouched->width)->toBeNull()
        ->and($untouched->status)->toBe('draft');
});
