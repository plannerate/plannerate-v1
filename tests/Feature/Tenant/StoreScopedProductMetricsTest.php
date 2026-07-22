<?php

use App\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Product as PlannerateProduct;
use Illuminate\Support\Facades\DB;

/*
 * Leitura das métricas POR LOJA (`current_stock`, `last_purchase_date`), que vivem
 * em `product_store` desde que a importação parou de gravá-las em `products`.
 *
 * O caso que o código antigo errava está no primeiro teste: o MESMO produto tem
 * estoque diferente em cada loja (na RP Info isso vale para 2 em cada 3 produtos) e
 * a coluna de `products` só conseguia guardar um dos dois.
 *
 * A conexão tenant é um sqlite :memory: montado aqui — os testes não migram o banco
 * do tenant.
 */

const STORE_MATRIZ = 'loja-matriz';
const STORE_FILIAL = 'loja-filial';
const PRODUCT_ID = 'produto-ulid';

beforeEach(function (): void {
    $schema = DB::connection('tenant')->getSchemaBuilder();

    $schema->dropIfExists('products');
    $schema->create('products', function ($table): void {
        $table->string('id')->primary();
        $table->string('tenant_id');
        $table->string('name');
        $table->string('ean')->nullable();
        $table->string('category_id')->nullable();
        $table->string('status')->nullable();
        $table->double('current_stock')->nullable();
        $table->date('last_purchase_date')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    $schema->dropIfExists('product_store');
    $schema->create('product_store', function ($table): void {
        $table->string('id')->primary();
        $table->string('tenant_id');
        $table->string('product_id');
        $table->string('store_id');
        $table->double('current_stock')->nullable();
        $table->date('last_purchase_date')->nullable();
        $table->timestamps();
        $table->unique(['tenant_id', 'product_id', 'store_id']);
    });
});

/**
 * Cria o produto com o valor LEGADO nas colunas de `products` — o número congelado
 * de uma loja só que ficou lá antes da migração. Nenhum teste pode devolvê-lo.
 */
function seedProductWithLegacyMetrics(?float $legacyStock = 999.0, ?string $legacyDate = '2020-01-01'): void
{
    DB::connection('tenant')->table('products')->insert([
        'id' => PRODUCT_ID,
        'tenant_id' => 'tenant-ulid',
        'name' => 'Batata Doce Grl Kg',
        'ean' => '7891010886547',
        'status' => 'published',
        'current_stock' => $legacyStock,
        'last_purchase_date' => $legacyDate,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedStoreMetrics(string $storeId, ?float $stock, ?string $purchaseDate): void
{
    DB::connection('tenant')->table('product_store')->insert([
        'id' => 'pivot-'.$storeId,
        'tenant_id' => 'tenant-ulid',
        'product_id' => PRODUCT_ID,
        'store_id' => $storeId,
        'current_stock' => $stock,
        'last_purchase_date' => $purchaseDate,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('forStore devolve o estoque da loja pedida, não o da outra', function (): void {
    seedProductWithLegacyMetrics();
    seedStoreMetrics(STORE_MATRIZ, 42.698, '2026-07-10');
    seedStoreMetrics(STORE_FILIAL, 19.405, '2026-07-18');

    $matriz = Product::query()->forStore(STORE_MATRIZ)->firstOrFail();
    $filial = Product::query()->forStore(STORE_FILIAL)->firstOrFail();

    expect((float) $matriz->current_stock)->toBe(42.698)
        ->and((float) $filial->current_stock)->toBe(19.405);
});

test('forStore traz também a última compra daquela filial', function (): void {
    seedProductWithLegacyMetrics();
    seedStoreMetrics(STORE_MATRIZ, 42.698, '2026-07-10');
    seedStoreMetrics(STORE_FILIAL, 19.405, '2026-07-18');

    $matriz = Product::query()->forStore(STORE_MATRIZ)->firstOrFail();

    expect($matriz->last_purchase_date)->not->toBeNull()
        ->and($matriz->last_purchase_date->format('Y-m-d'))->toBe('2026-07-10');
});

test('sem linha na pivot o valor é nulo — não cai para a coluna congelada de products', function (): void {
    seedProductWithLegacyMetrics(legacyStock: 999.0, legacyDate: '2020-01-01');

    $product = Product::query()->forStore(STORE_MATRIZ)->firstOrFail();

    expect($product->current_stock)->toBeNull()
        ->and($product->last_purchase_date)->toBeNull();
});

test('withStoreMetrics consolida: soma o estoque e pega a compra mais recente', function (): void {
    seedProductWithLegacyMetrics();
    seedStoreMetrics(STORE_MATRIZ, 42.698, '2026-07-10');
    seedStoreMetrics(STORE_FILIAL, 19.405, '2026-07-18');

    $product = Product::query()->withStoreMetrics()->firstOrFail();

    expect((float) $product->current_stock)->toBe(62.103)
        ->and($product->last_purchase_date->format('Y-m-d'))->toBe('2026-07-18');
});

test('forStore(null) consolida as lojas em vez de escolher uma arbitrária', function (): void {
    seedProductWithLegacyMetrics();
    seedStoreMetrics(STORE_MATRIZ, 42.698, '2026-07-10');
    seedStoreMetrics(STORE_FILIAL, 19.405, '2026-07-18');

    $product = Product::query()->forStore(null)->firstOrFail();

    expect((float) $product->current_stock)->toBe(62.103);
});

test('o scope sobrepõe a coluna mesmo quando a query já lista colunas explicitamente', function (): void {
    seedProductWithLegacyMetrics();
    seedStoreMetrics(STORE_FILIAL, 19.405, '2026-07-18');

    // Formato do eager-load do editor (GondolaController) e do SlotReviewAnalysisService:
    // select enxuto para não trazer a tabela inteira, scope aplicado DEPOIS.
    $product = Product::query()
        ->select('id', 'name', 'ean')
        ->forStore(STORE_FILIAL)
        ->firstOrFail();

    expect((float) $product->current_stock)->toBe(19.405);
});

test('o Product do pacote plannerate também recorta por loja', function (): void {
    // É este model que o editor (GondolaPayloadService) e o PDF (GondolaPrintService)
    // carregam — os dois entregam `current_stock` ao front.
    seedProductWithLegacyMetrics();
    seedStoreMetrics(STORE_MATRIZ, 42.698, '2026-07-10');
    seedStoreMetrics(STORE_FILIAL, 19.405, '2026-07-18');

    $matriz = PlannerateProduct::query()->forStore(STORE_MATRIZ)->firstOrFail();
    $filial = PlannerateProduct::query()->forStore(STORE_FILIAL)->firstOrFail();

    expect((float) $matriz->current_stock)->toBe(42.698)
        ->and((float) $filial->current_stock)->toBe(19.405);
});

test('pluck por ean lê o estoque da loja — formato do TargetStockService', function (): void {
    seedProductWithLegacyMetrics();
    seedStoreMetrics(STORE_MATRIZ, 42.698, '2026-07-10');

    $byEan = Product::query()
        ->forStore(STORE_MATRIZ)
        ->whereIn('ean', ['7891010886547'])
        ->pluck('current_stock', 'ean');

    expect((float) $byEan['7891010886547'])->toBe(42.698);
});
