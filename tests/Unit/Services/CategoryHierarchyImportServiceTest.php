<?php

use App\Models\Category;
use App\Models\EanReference;
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
            'descricao_atual' => 'Macarrao Premium',
            'marca_obrigatorio' => 'Marca A',
            'tipo_de_embalagem_obrigatorio' => 'Pacote',
            'tamanho_ou_quantidade_da_embalagem_obrigatorio' => '500',
            'unidade_de_medida_obrigatorio' => 'g',
        ]]
    );

    $product->refresh();
    $leafCategory = Category::query()->where('tenant_id', $tenantId)->where('name', 'Premium')->first();

    expect($leafCategory)->not->toBeNull();
    expect($product->category_id)->toBe($leafCategory?->id);

    $eanReference = EanReference::query()
        ->where('ean', '7890000000001')
        ->first();

    expect($eanReference)->not->toBeNull();
    expect($eanReference?->category_id)->toBe($leafCategory?->id);
    expect($eanReference?->category_name)->toBe($leafCategory?->name);
    expect($eanReference?->category_slug)->toBe($leafCategory?->slug);
    expect($eanReference?->reference_description)->toBe('Macarrao Premium');
    expect($eanReference?->brand)->toBe('Marca A');
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

test('mapeia colunas extras com cabecalho original da planilha', function (): void {
    $tenantId = (string) Str::ulid();
    $user = User::factory()->create();

    $service = app(CategoryHierarchyImportService::class);
    $service->importRows(
        tenantId: $tenantId,
        userId: $user->id,
        rows: [[
            'EAN' => '7890000000999',
            'Segmento varejista' => 'Mercearia',
            'Departamento' => 'Alimentos',
            'Descrição atual' => 'Produto Teste',
            'Marca (obrigatório)' => 'JUREIA',
            'Submarca' => 'Linha A',
            'Tipo de embalagem (obrigatório)' => 'PACOTE',
            'Tamanho ou quantidade da embalagem (obrigatório)' => '500',
            'Unidade de medida (obrigatório)' => 'G',
        ]]
    );

    $reference = EanReference::query()
        ->where('ean', '7890000000999')
        ->first();

    expect($reference)->not->toBeNull();
    expect($reference?->reference_description)->toBe('Produto Teste');
    expect($reference?->brand)->toBe('JUREIA');
    expect($reference?->subbrand)->toBe('Linha A');
    expect($reference?->packaging_type)->toBe('PACOTE');
    expect($reference?->packaging_size)->toBe('500');
    expect($reference?->measurement_unit)->toBe('G');
});
