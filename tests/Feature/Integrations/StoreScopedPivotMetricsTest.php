<?php

use App\Services\Integrations\TenantPivotRecordPersister;
use App\Services\Integrations\TenantUpsertRecordPreparer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Métricas POR LOJA (estoque, última compra) vivem em `product_store`, não em
 * `products`.
 *
 * O id do produto deriva de tenant+ean — sem loja —, então as duas cadeias de
 * importação gravam na MESMA linha de `products`: sem separar, o valor final é o
 * da última cadeia a terminar. Medido na RP Info: 67 de 100 produtos da primeira
 * página têm estoque diferente entre a Matriz e a Filial.
 *
 * A conexão tenant é um sqlite :memory: recriado a cada teste.
 */

beforeEach(function (): void {
    $schema = DB::connection('tenant')->getSchemaBuilder();

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

/** @return array<string, mixed> */
function pivotConfigForStoreMetrics(array $overrides = []): array
{
    return [
        'table' => 'product_store',
        'local_key' => 'id',
        'foreign_key' => 'product_id',
        'related_key' => 'store_id',
        'unique_by' => ['tenant_id', 'product_id', 'store_id'],
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function storeMetricRecord(string $storeId, float $stock, string $purchaseDate): array
{
    return [
        'id' => 'produto-ulid',
        'tenant_id' => 'tenant-ulid',
        'store_id' => $storeId,
        'ean' => '7891010886547',
        'name' => 'Produto',
        'current_stock' => $stock,
        'last_purchase_date' => $purchaseDate,
    ];
}

test('pivot_only_targets tira a métrica do upsert da tabela principal', function (): void {
    $prepared = TenantUpsertRecordPreparer::prepare(
        [storeMetricRecord('loja-1', 42.0, '2026-03-13')],
        ['id', 'tenant_id', 'ean', 'name', 'current_stock', 'last_purchase_date'],
        'products',
        ['current_stock', 'last_purchase_date'],
    );

    expect($prepared[0])->toHaveKeys(['id', 'tenant_id', 'ean', 'name'])
        ->and($prepared[0])->not->toHaveKeys(['current_stock', 'last_purchase_date']);
});

test('sem pivot_only_targets nada muda — integrações antigas seguem iguais', function (): void {
    $prepared = TenantUpsertRecordPreparer::prepare(
        [storeMetricRecord('loja-1', 42.0, '2026-03-13')],
        ['id', 'tenant_id', 'ean', 'name', 'current_stock', 'last_purchase_date'],
        'products',
    );

    expect($prepared[0])->toHaveKeys(['current_stock', 'last_purchase_date']);
});

test('cada loja guarda a própria métrica na pivot', function (): void {
    TenantPivotRecordPersister::persist(
        DB::connection('tenant'),
        [
            storeMetricRecord('loja-1', 42.698, '2026-03-13'),
            storeMetricRecord('loja-2', 19.405, '2026-05-20'),
        ],
        [pivotConfigForStoreMetrics(['update_columns' => ['current_stock', 'last_purchase_date']])],
    );

    $linhas = DB::connection('tenant')->table('product_store')->orderBy('store_id')->get();

    expect($linhas)->toHaveCount(2)
        ->and((float) $linhas[0]->current_stock)->toBe(42.698)
        ->and($linhas[0]->last_purchase_date)->toBe('2026-03-13')
        ->and((float) $linhas[1]->current_stock)->toBe(19.405)
        ->and($linhas[1]->last_purchase_date)->toBe('2026-05-20');
});

test('update_columns faz o re-import atualizar a métrica da loja', function (): void {
    $config = [pivotConfigForStoreMetrics(['update_columns' => ['current_stock', 'last_purchase_date']])];

    TenantPivotRecordPersister::persist(DB::connection('tenant'), [storeMetricRecord('loja-1', 42.0, '2026-03-13')], $config);
    TenantPivotRecordPersister::persist(DB::connection('tenant'), [storeMetricRecord('loja-1', 7.5, '2026-07-19')], $config);

    $linha = DB::connection('tenant')->table('product_store')->first();

    expect(DB::connection('tenant')->table('product_store')->count())->toBe(1)
        ->and((float) $linha->current_stock)->toBe(7.5)
        ->and($linha->last_purchase_date)->toBe('2026-07-19');
});

test('sem update_columns a métrica congela no primeiro import — o bug que motivou a chave', function (): void {
    $config = [pivotConfigForStoreMetrics()];

    TenantPivotRecordPersister::persist(DB::connection('tenant'), [storeMetricRecord('loja-1', 42.0, '2026-03-13')], $config);
    TenantPivotRecordPersister::persist(DB::connection('tenant'), [storeMetricRecord('loja-1', 7.5, '2026-07-19')], $config);

    expect((float) DB::connection('tenant')->table('product_store')->first()->current_stock)->toBe(42.0);
});

test('lote com e sem a métrica não quebra o upsert em lote', function (): void {
    // Regressão: `isset` ignora null, então o produto sem estoque gerava linha
    // SEM a coluna e o produto com estoque gerava linha COM — o upsert em lote
    // estourava com "VALUES lists must all be the same length".
    $semMetrica = storeMetricRecord('loja-1', 0.0, '2026-01-01');
    unset($semMetrica['current_stock'], $semMetrica['last_purchase_date']);

    $comNull = storeMetricRecord('loja-2', 0.0, '2026-01-01');
    $comNull['current_stock'] = null;
    $comNull['last_purchase_date'] = null;

    TenantPivotRecordPersister::persist(
        DB::connection('tenant'),
        [$semMetrica, $comNull, storeMetricRecord('loja-3', 42.0, '2026-03-13')],
        [pivotConfigForStoreMetrics(['update_columns' => ['current_stock', 'last_purchase_date']])],
    );

    $linhas = DB::connection('tenant')->table('product_store')->orderBy('store_id')->get();

    expect($linhas)->toHaveCount(3)
        ->and($linhas[0]->current_stock)->toBeNull()
        ->and($linhas[1]->current_stock)->toBeNull()
        ->and((float) $linhas[2]->current_stock)->toBe(42.0);
});

test('update_columns com coluna inexistente é ignorada, não quebra o upsert', function (): void {
    TenantPivotRecordPersister::persist(
        DB::connection('tenant'),
        [storeMetricRecord('loja-1', 42.0, '2026-03-13')],
        [pivotConfigForStoreMetrics(['update_columns' => ['current_stock', 'coluna_que_nao_existe']])],
    );

    expect(DB::connection('tenant')->table('product_store')->count())->toBe(1);
});

afterEach(function (): void {
    Schema::connection('tenant')->dropIfExists('product_store');
});
