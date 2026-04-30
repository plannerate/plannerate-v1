<?php

use App\Services\Integrations\Support\SyncLayerProductIdsFromLegacyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'multitenancy.tenant_database_connection_name' => null,
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('service remapeia layers product_id inválido usando ean da base legada', function (): void {
    $tenantId = (string) str()->ulid();
    $now = now();

    $legacyPath = storage_path('framework/testing/legacy-layer-sync.sqlite');
    if (! is_dir(dirname($legacyPath))) {
        mkdir(dirname($legacyPath), 0777, true);
    }
    if (! file_exists($legacyPath)) {
        touch($legacyPath);
    }

    config([
        'database.connections.mysql_legacy' => [
            'driver' => 'sqlite',
            'database' => $legacyPath,
            'prefix' => '',
        ],
    ]);

    DB::purge('mysql_legacy');
    $legacy = DB::connection('mysql_legacy');
    $legacy->statement('DROP TABLE IF EXISTS products');
    $legacy->statement('CREATE TABLE products (id VARCHAR(26) PRIMARY KEY, ean VARCHAR(255) NULL)');

    $legacyProductId = '01LEGACYPRODUCT000000000001';
    $legacy->table('products')->insert([
        'id' => $legacyProductId,
        'ean' => '7891000000102',
    ]);

    $newProductId = (string) str()->ulid();
    DB::table('products')->insert([
        'id' => $newProductId,
        'tenant_id' => $tenantId,
        'name' => 'Produto novo',
        'slug' => 'produto-novo',
        'ean' => '7891000000102',
        'codigo_erp' => 'ERP-10',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $validProductId = (string) str()->ulid();
    DB::table('products')->insert([
        'id' => $validProductId,
        'tenant_id' => $tenantId,
        'name' => 'Produto válido',
        'slug' => 'produto-valido',
        'ean' => '7891000000997',
        'codigo_erp' => 'ERP-99',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('layers')->insert([
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'product_id' => $legacyProductId,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'product_id' => '01LEGACYINEXISTENTE000000001',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'product_id' => $validProductId,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $summary = app(SyncLayerProductIdsFromLegacyService::class)->sync(
        tenantConnectionName: (string) config('database.default'),
        legacyConnectionName: 'mysql_legacy',
        tenantId: $tenantId,
        preview: false,
    );

    $remappedLayer = DB::table('layers')
        ->where('tenant_id', $tenantId)
        ->where('product_id', $newProductId)
        ->first();

    $unresolvedLayer = DB::table('layers')
        ->where('tenant_id', $tenantId)
        ->where('product_id', '01LEGACYINEXISTENTE000000001')
        ->first();

    expect($summary)->toMatchArray([
        'invalid_layers' => 2,
        'restored_products' => 0,
        'legacy_matched' => 1,
        'tenant_matched' => 1,
        'updated' => 1,
        'unresolved_legacy' => 1,
        'unresolved_tenant' => 0,
    ])
        ->and($remappedLayer)->not->toBeNull()
        ->and($unresolvedLayer)->not->toBeNull();
});

test('service conta layers com product_id inválido sem depender do legado', function (): void {
    $tenantId = (string) str()->ulid();
    $otherTenantId = (string) str()->ulid();
    $now = now();

    $validProductId = (string) str()->ulid();

    DB::table('products')->insert([
        'id' => $validProductId,
        'tenant_id' => $tenantId,
        'name' => 'Produto válido',
        'slug' => 'produto-valido-contagem',
        'ean' => '7891000000553',
        'codigo_erp' => 'ERP-CONT',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('layers')->insert([
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'product_id' => '01INVALIDPRODUCT000000000001',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'product_id' => $validProductId,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $otherTenantId,
            'product_id' => '01INVALIDPRODUCT000000000002',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $count = app(SyncLayerProductIdsFromLegacyService::class)->countInvalidLayers(
        tenantConnectionName: (string) config('database.default'),
        tenantId: $tenantId,
    );

    expect($count)->toBe(1);
});

test('service restaura produto local soft deleted referenciado por layer', function (): void {
    $tenantId = (string) str()->ulid();
    $now = now();

    $softDeletedProductId = (string) str()->ulid();
    DB::table('products')->insert([
        'id' => $softDeletedProductId,
        'tenant_id' => $tenantId,
        'name' => 'Produto removido',
        'slug' => 'produto-removido',
        'ean' => '7891000000775',
        'codigo_erp' => 'ERP-DEL',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => $now,
    ]);

    DB::table('layers')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => $tenantId,
        'product_id' => $softDeletedProductId,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $legacyPath = storage_path('framework/testing/legacy-layer-sync.sqlite');
    if (! is_dir(dirname($legacyPath))) {
        mkdir(dirname($legacyPath), 0777, true);
    }
    if (! file_exists($legacyPath)) {
        touch($legacyPath);
    }

    config([
        'database.connections.mysql_legacy' => [
            'driver' => 'sqlite',
            'database' => $legacyPath,
            'prefix' => '',
        ],
    ]);

    DB::purge('mysql_legacy');
    $legacy = DB::connection('mysql_legacy');
    $legacy->statement('DROP TABLE IF EXISTS products');
    $legacy->statement('CREATE TABLE products (id VARCHAR(26) PRIMARY KEY, ean VARCHAR(255) NULL)');

    $summary = app(SyncLayerProductIdsFromLegacyService::class)->sync(
        tenantConnectionName: (string) config('database.default'),
        legacyConnectionName: 'mysql_legacy',
        tenantId: $tenantId,
        preview: false,
    );

    $product = DB::table('products')->where('id', $softDeletedProductId)->first();

    expect($summary)->toMatchArray([
        'invalid_layers' => 0,
        'restored_products' => 1,
        'legacy_matched' => 0,
        'tenant_matched' => 0,
        'updated' => 0,
        'unresolved_legacy' => 0,
        'unresolved_tenant' => 0,
    ])
        ->and($product?->deleted_at)->toBeNull();
});
