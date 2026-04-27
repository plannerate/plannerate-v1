<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Files\Imports\Categories\CategoryHierarchyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    static $landlordMigrated = false;

    if (! $landlordMigrated) {
        Artisan::call('migrate', [
            '--database' => 'landlord',
            '--path' => 'database/migrations/landlord',
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $landlordMigrated = true;
    }

    config()->set('permission.rbac_enabled', false);
});

test('monta hierarquia e vincula produto pelo ean na categoria folha', function (): void {
    $tenantId = (string) Str::ulid();
    $user = User::factory()->create();

    $product = Product::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'name' => 'Produto EAN',
        'slug' => 'produto-ean',
        'status' => 'draft',
        'dimensions_status' => 'draft',
        'ean' => '7890000000001',
        'category_id' => null,
    ]);

    $service = app(CategoryHierarchyImportService::class);
    $service->importRows(
        tenantId: $tenantId,
        userId: $user->id,
        rows: [[
            'segmento_varejista' => 'Mercearia',
            'departamento' => 'Alimentos',
            'subdepartamento' => 'Secos',
            'categoria' => 'Massas',
            'subcategoria' => 'Espaguete',
            'segmento' => 'Trigo',
            'subsegmento' => 'Tradicional',
            'atributo' => 'Premium',
            'ean' => '7890000000001',
        ]]
    );

    $product->refresh();
    $leafCategory = Category::query()->where('tenant_id', $tenantId)->where('name', 'Premium')->first();

    expect($leafCategory)->not->toBeNull();
    expect($product->category_id)->toBe($leafCategory?->id);
});

test('ean inexistente gera aviso e ainda cria categorias', function (): void {
    $tenantId = (string) Str::ulid();
    $user = User::factory()->create();

    $service = app(CategoryHierarchyImportService::class);
    $result = $service->importRows(
        tenantId: $tenantId,
        userId: $user->id,
        rows: [[
            'segmento_varejista' => 'Mercearia',
            'departamento' => 'Alimentos',
            'ean' => '9990000000001',
        ]]
    );

    expect($result->warnings)->not->toBeEmpty();
    expect(Category::query()->where('tenant_id', $tenantId)->count())->toBeGreaterThan(0);
});

test('nivel intermediario vazio gera erro na linha', function (): void {
    $tenantId = (string) Str::ulid();
    $user = User::factory()->create();

    $service = app(CategoryHierarchyImportService::class);
    $result = $service->importRows(
        tenantId: $tenantId,
        userId: $user->id,
        rows: [[
            'segmento_varejista' => 'Mercearia',
            'departamento' => '',
            'subdepartamento' => 'Secos',
            'ean' => '7890000000002',
        ]]
    );

    expect($result->errors)->not->toBeEmpty();
});

test('reimportacao idempotente para produto e categorias', function (): void {
    $tenantId = (string) Str::ulid();
    $user = User::factory()->create();

    Product::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'name' => 'Produto EAN',
        'slug' => 'produto-ean',
        'status' => 'draft',
        'dimensions_status' => 'draft',
        'ean' => '7890000000003',
        'category_id' => null,
    ]);

    $row = [
        'segmento_varejista' => 'Mercearia',
        'departamento' => 'Alimentos',
        'ean' => '7890000000003',
    ];

    $service = app(CategoryHierarchyImportService::class);
    $first = $service->importRows($tenantId, $user->id, [$row]);
    $second = $service->importRows($tenantId, $user->id, [$row]);

    $categoriesCount = Category::query()->where('tenant_id', $tenantId)->count();
    expect($categoriesCount)->toBe(2);

    $product = Product::query()->where('tenant_id', $tenantId)->where('ean', '7890000000003')->firstOrFail();
    expect($product->category_id)->not->toBeNull();
    expect($second->categoriesCreated)->toBe(0);
    expect($first->productsLinked + $second->productsLinked)->toBeGreaterThanOrEqual(1);
});
