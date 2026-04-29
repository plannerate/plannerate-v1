<?php

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncProductsFromEanReferencesService;
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

test('service fills product fields from ean references without overwriting existing values', function () {
    $tenantId = (string) str()->ulid();
    $otherTenantId = (string) str()->ulid();
    $categoryId = (string) str()->ulid();
    $otherCategoryId = (string) str()->ulid();
    $now = now();

    DB::table('categories')->insert([
        [
            'id' => $categoryId,
            'tenant_id' => $tenantId,
            'name' => 'Bebidas',
            'slug' => 'bebidas',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $otherCategoryId,
            'tenant_id' => $tenantId,
            'name' => 'Limpeza',
            'slug' => 'limpeza',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('ean_references')->insert([
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'ean' => '7891000000010',
            'category_id' => $categoryId,
            'reference_description' => 'Descrição referência',
            'brand' => 'Marca Ref',
            'subbrand' => 'Submarca Ref',
            'packaging_type' => 'Garrafa',
            'packaging_size' => '500ml',
            'measurement_unit' => 'UN',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'ean' => '7891000000027',
            'category_id' => $categoryId,
            'reference_description' => 'Referência preenchida',
            'brand' => 'Marca preenchida ref',
            'subbrand' => 'Submarca preenchida ref',
            'packaging_type' => 'Pacote',
            'packaging_size' => '2L',
            'measurement_unit' => 'UN',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $otherTenantId,
            'ean' => '7891000000034',
            'category_id' => $otherCategoryId,
            'reference_description' => 'Outro tenant',
            'brand' => 'Outra Marca',
            'subbrand' => null,
            'packaging_type' => null,
            'packaging_size' => null,
            'measurement_unit' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('products')->insert([
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'name' => 'Produto vazio',
            'slug' => 'produto-vazio',
            'ean' => '7891000000010',
            'codigo_erp' => '1001',
            'category_id' => null,
            'description' => null,
            'brand' => null,
            'subbrand' => null,
            'packaging_type' => null,
            'packaging_size' => null,
            'measurement_unit' => null,
            'status' => 'synced',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'name' => 'Produto preenchido',
            'slug' => 'produto-preenchido',
            'ean' => '7891000000027',
            'codigo_erp' => '1002',
            'category_id' => $otherCategoryId,
            'description' => 'Descrição existente',
            'brand' => 'Marca existente',
            'subbrand' => 'Submarca existente',
            'packaging_type' => 'Caixa',
            'packaging_size' => '1L',
            'measurement_unit' => 'CX',
            'status' => 'synced',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $otherTenantId,
            'name' => 'Produto outro tenant',
            'slug' => 'produto-outro-tenant',
            'ean' => '7891000000034',
            'codigo_erp' => '1003',
            'category_id' => null,
            'description' => null,
            'brand' => null,
            'subbrand' => null,
            'packaging_type' => null,
            'packaging_size' => null,
            'measurement_unit' => null,
            'status' => 'synced',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $summary = app(SyncProductsFromEanReferencesService::class)->sync(
        tenantConnectionName: (string) config('database.default'),
        tenantId: $tenantId,
        preview: false,
    );

    $emptyProduct = DB::table('products')
        ->where('tenant_id', $tenantId)
        ->where('codigo_erp', '1001')
        ->first();

    $filledProduct = DB::table('products')
        ->where('tenant_id', $tenantId)
        ->where('codigo_erp', '1002')
        ->first();

    $otherTenantProduct = DB::table('products')
        ->where('tenant_id', $otherTenantId)
        ->where('codigo_erp', '1003')
        ->first();

    expect($summary)->toMatchArray([
        'matched' => 2,
        'updated' => 1,
        'remaining' => 0,
    ])
        ->and($emptyProduct?->category_id)->toBe($categoryId)
        ->and($emptyProduct?->description)->toBe('Descrição referência')
        ->and($emptyProduct?->brand)->toBe('Marca Ref')
        ->and($emptyProduct?->subbrand)->toBe('Submarca Ref')
        ->and($emptyProduct?->packaging_type)->toBe('Garrafa')
        ->and($emptyProduct?->packaging_size)->toBe('500ml')
        ->and($emptyProduct?->measurement_unit)->toBe('UN')
        ->and($filledProduct?->category_id)->toBe($otherCategoryId)
        ->and($filledProduct?->description)->toBe('Descrição existente')
        ->and($filledProduct?->brand)->toBe('Marca existente')
        ->and($otherTenantProduct?->category_id)->toBeNull()
        ->and($otherTenantProduct?->brand)->toBeNull();
});

test('command previews product updates for a tenant without mutating data', function () {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant EAN Preview',
        'slug' => 'tenant-ean-preview-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_ean_preview',
        'status' => 'active',
    ]));
    $categoryId = (string) str()->ulid();
    $now = now();

    DB::table('categories')->insert([
        'id' => $categoryId,
        'tenant_id' => (string) $tenant->id,
        'name' => 'Categoria Preview',
        'slug' => 'categoria-preview',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('ean_references')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => (string) $tenant->id,
        'ean' => '7891000000041',
        'category_id' => $categoryId,
        'brand' => 'Marca Preview',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('products')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => (string) $tenant->id,
        'name' => 'Produto Preview',
        'slug' => 'produto-preview',
        'ean' => '7891000000041',
        'codigo_erp' => '1004',
        'category_id' => null,
        'brand' => null,
        'status' => 'synced',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan(sprintf('sync:products-from-ean-references --tenant=%s --preview', $tenant->id))
        ->assertSuccessful();

    $product = DB::table('products')
        ->where('tenant_id', (string) $tenant->id)
        ->where('codigo_erp', '1004')
        ->first();

    expect($product?->category_id)->toBeNull()
        ->and($product?->brand)->toBeNull();
});
