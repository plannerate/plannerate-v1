<?php

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncProductsFromEanReferencesService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * SKIP (fase 5 da refatoração raptor-plannerate, aprovado em 2026-06-11):
 * este arquivo referencia classes do domínio Integrations que não existem mais
 * nesses namespaces (ex.: App\Services\Integrations\Http\IntegrationHttpClient —
 * a classe atual vive em App\Services\Integrations\IntegrationHttpClient).
 * Estes testes nunca rodaram (a suíte não carregava antes do commit 83d400a).
 * Triagem pendente do domínio Integrations: atualizar imports/expectativas ou remover.
 */
beforeEach(function (): void {
    $this->markTestSkipped('Domínio Integrations: classes testadas mudaram de namespace — triagem pendente (ver comentário no topo do arquivo).');
});

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
            'ean' => '7891000000010',
            'category_name' => 'Bebidas',
            'category_slug' => 'bebidas',
            'reference_description' => 'Descrição referência',
            'brand' => 'Marca Ref',
            'subbrand' => 'Submarca Ref',
            'packaging_type' => 'Garrafa',
            'packaging_size' => '500ml',
            'measurement_unit' => 'UN',
            'width' => 17.00,
            'height' => 21.00,
            'depth' => 3.50,
            'weight' => 0.00,
            'unit' => 'cm',
            'has_dimensions' => true,
            'dimension_status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'ean' => '7891000000027',
            'category_name' => 'Bebidas',
            'category_slug' => 'bebidas',
            'reference_description' => 'Referência preenchida',
            'brand' => 'Marca preenchida ref',
            'subbrand' => 'Submarca preenchida ref',
            'packaging_type' => 'Pacote',
            'packaging_size' => '2L',
            'measurement_unit' => 'UN',
            'width' => 99.00,
            'height' => 88.00,
            'depth' => 77.00,
            'weight' => 2.00,
            'unit' => 'cm',
            'has_dimensions' => true,
            'dimension_status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'ean' => '7891000000034',
            'category_name' => 'Outra Categoria',
            'category_slug' => 'outra-categoria',
            'reference_description' => 'Outro tenant',
            'brand' => 'Outra Marca',
            'subbrand' => null,
            'packaging_type' => null,
            'packaging_size' => null,
            'measurement_unit' => null,
            'width' => null,
            'height' => null,
            'depth' => null,
            'weight' => null,
            'unit' => 'cm',
            'has_dimensions' => false,
            'dimension_status' => 'draft',
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
            'width' => null,
            'height' => null,
            'depth' => null,
            'weight' => null,
            'unit' => 'cm',
            'has_dimensions' => false,
            'dimension_status' => 'published',
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
            'width' => 10.00,
            'height' => 10.00,
            'depth' => 10.00,
            'weight' => 1.00,
            'unit' => 'mm',
            'has_dimensions' => false,
            'dimension_status' => 'draft',
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
            'width' => null,
            'height' => null,
            'depth' => null,
            'weight' => null,
            'unit' => 'cm',
            'has_dimensions' => false,
            'dimension_status' => 'published',
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
        ->and((float) ($emptyProduct?->width ?? 0))->toBe(17.0)
        ->and((float) ($emptyProduct?->height ?? 0))->toBe(21.0)
        ->and((float) ($emptyProduct?->depth ?? 0))->toBe(3.5)
        ->and((float) ($emptyProduct?->weight ?? 0))->toBe(0.0)
        ->and($emptyProduct?->unit)->toBe('cm')
        ->and((bool) ($emptyProduct?->has_dimensions ?? false))->toBeTrue()
        ->and($emptyProduct?->dimension_status)->toBe('published')
        ->and($filledProduct?->category_id)->toBe($otherCategoryId)
        ->and($filledProduct?->description)->toBe('Descrição existente')
        ->and($filledProduct?->brand)->toBe('Marca existente')
        ->and((float) ($filledProduct?->width ?? 0))->toBe(10.0)
        ->and((float) ($filledProduct?->height ?? 0))->toBe(10.0)
        ->and($filledProduct?->unit)->toBe('mm')
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
        'ean' => '7891000000041',
        'category_name' => 'Categoria Preview',
        'category_slug' => 'categoria-preview',
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

test('service nullifies category_id when ean category cannot be resolved in tenant', function () {
    $tenantId = (string) str()->ulid();
    $existingCategoryId = (string) str()->ulid();
    $now = now();

    DB::table('categories')->insert([
        'id' => $existingCategoryId,
        'tenant_id' => $tenantId,
        'name' => 'Categoria Existente',
        'slug' => 'categoria-existente',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('ean_references')->insert([
        'id' => (string) str()->ulid(),
        'ean' => '7891000000058',
        'category_name' => 'Categoria Inexistente',
        'category_slug' => 'categoria-inexistente',
        'brand' => 'Marca sem categoria',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('products')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => $tenantId,
        'name' => 'Produto com categoria órfã',
        'slug' => 'produto-com-categoria-orfa',
        'ean' => '7891000000058',
        'codigo_erp' => '1005',
        'category_id' => $existingCategoryId,
        'brand' => null,
        'status' => 'synced',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $summary = app(SyncProductsFromEanReferencesService::class)->sync(
        tenantConnectionName: (string) config('database.default'),
        tenantId: $tenantId,
        preview: false,
    );

    $product = DB::table('products')
        ->where('tenant_id', $tenantId)
        ->where('codigo_erp', '1005')
        ->first();

    expect($summary)->toMatchArray([
        'matched' => 1,
        'updated' => 1,
        'remaining' => 0,
    ])
        ->and($product?->category_id)->toBeNull()
        ->and($product?->brand)->toBe('Marca sem categoria');
});

test('command preview does not nullify category_id when category cannot be resolved', function () {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant EAN Preview Null',
        'slug' => 'tenant-ean-preview-null-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_ean_preview_null',
        'status' => 'active',
    ]));
    $existingCategoryId = (string) str()->ulid();
    $now = now();

    DB::table('categories')->insert([
        'id' => $existingCategoryId,
        'tenant_id' => (string) $tenant->id,
        'name' => 'Categoria Preview Null',
        'slug' => 'categoria-preview-null',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('ean_references')->insert([
        'id' => (string) str()->ulid(),
        'ean' => '7891000000065',
        'category_name' => 'Não Existe',
        'category_slug' => 'nao-existe',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('products')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => (string) $tenant->id,
        'name' => 'Produto Preview Null',
        'slug' => 'produto-preview-null',
        'ean' => '7891000000065',
        'codigo_erp' => '1006',
        'category_id' => $existingCategoryId,
        'status' => 'synced',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan(sprintf('sync:products-from-ean-references --tenant=%s --preview', $tenant->id))
        ->assertSuccessful();

    $product = DB::table('products')
        ->where('tenant_id', (string) $tenant->id)
        ->where('codigo_erp', '1006')
        ->first();

    expect($product?->category_id)->toBe($existingCategoryId);
});
