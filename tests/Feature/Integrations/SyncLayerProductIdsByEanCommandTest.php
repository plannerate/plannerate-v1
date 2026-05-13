<?php

use App\Models\Tenant;
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

test('atualiza product_id das layers por ean incluindo layers soft deleted', function (): void {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Sync Layer',
        'slug' => 'tenant-sync-layer-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_sync_layer',
        'status' => 'active',
    ]));

    $tenantId = (string) $tenant->id;
    $now = now();

    $activeProductByEan = (string) str()->ulid();
    $deletedProductByEan = (string) str()->ulid();

    DB::table('products')->insert([
        [
            'id' => $activeProductByEan,
            'tenant_id' => $tenantId,
            'name' => 'Produto ativo por EAN',
            'slug' => 'produto-ativo-por-ean',
            'ean' => '7891111111111',
            'codigo_erp' => 'ERP-ATIVO-EAN',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $deletedProductByEan,
            'tenant_id' => $tenantId,
            'name' => 'Produto deletado por EAN',
            'slug' => 'produto-deletado-por-ean',
            'ean' => '7892222222222',
            'codigo_erp' => 'ERP-DEL-EAN',
            'status' => 'published',
            'deleted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('layers')->insert([
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'segment_id' => (string) str()->ulid(),
            'product_id' => (string) str()->ulid(),
            'ean' => '7891111111111',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'segment_id' => (string) str()->ulid(),
            'product_id' => (string) str()->ulid(),
            'ean' => '7892222222222',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'segment_id' => (string) str()->ulid(),
            'product_id' => (string) str()->ulid(),
            'ean' => '7892222222222',
            'status' => 'published',
            'deleted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $this->artisan(sprintf('sync:layers-product-ids-by-ean --tenant=%s', $tenantId))
        ->expectsOutputToContain('layer(s) atualizada(s)')
        ->expectsOutputToContain('produto(s) reativado(s)')
        ->assertSuccessful();

    $layers = DB::table('layers')
        ->where('tenant_id', $tenantId)
        ->orderBy('created_at')
        ->get(['ean', 'product_id', 'deleted_at']);

    expect($layers)->toHaveCount(3);

    expect($layers[0]->ean)->toBe('7891111111111')
        ->and($layers[0]->product_id)->toBe($activeProductByEan);

    expect($layers[1]->ean)->toBe('7892222222222')
        ->and($layers[1]->deleted_at)->toBeNull()
        ->and($layers[1]->product_id)->toBe($deletedProductByEan);

    expect($layers[2]->ean)->toBe('7892222222222')
        ->and($layers[2]->deleted_at)->not->toBeNull()
        ->and($layers[2]->product_id)->toBe($deletedProductByEan);

    $deletedProduct = DB::table('products')->where('id', $deletedProductByEan)->first(['deleted_at']);
    expect($deletedProduct)->not->toBeNull()
        ->and($deletedProduct->deleted_at)->toBeNull();
});

test('nao reativa produto quando layer e produto estao soft deleted', function (): void {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Regra Delete',
        'slug' => 'tenant-regra-delete-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_regra_delete',
        'status' => 'active',
    ]));

    $tenantId = (string) $tenant->id;
    $now = now();

    $deletedProductByEan = (string) str()->ulid();

    DB::table('products')->insert([
        'id' => $deletedProductByEan,
        'tenant_id' => $tenantId,
        'name' => 'Produto deletado alvo',
        'slug' => 'produto-deletado-alvo',
        'ean' => '7893333333333',
        'codigo_erp' => 'ERP-DEL-ALVO',
        'status' => 'published',
        'deleted_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('layers')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => $tenantId,
        'segment_id' => (string) str()->ulid(),
        'product_id' => (string) str()->ulid(),
        'ean' => '7893333333333',
        'status' => 'published',
        'deleted_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan(sprintf('sync:layers-product-ids-by-ean --tenant=%s', $tenantId))
        ->assertSuccessful();

    $layer = DB::table('layers')->where('tenant_id', $tenantId)->first(['product_id', 'deleted_at']);
    expect($layer)->not->toBeNull()
        ->and($layer->product_id)->toBe($deletedProductByEan)
        ->and($layer->deleted_at)->not->toBeNull();

    $product = DB::table('products')->where('id', $deletedProductByEan)->first(['deleted_at']);
    expect($product)->not->toBeNull()
        ->and($product->deleted_at)->not->toBeNull();
});
