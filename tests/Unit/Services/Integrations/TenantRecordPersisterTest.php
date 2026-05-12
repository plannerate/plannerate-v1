<?php

use App\Models\TenantIntegration;
use Tests\TestCase;

uses(TestCase::class);
use App\Services\Integrations\TenantRecordPersister;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

beforeEach(function (): void {
    Log::shouldReceive('debug')->zeroOrMoreTimes();
});

it('skips when target table is empty', function (): void {
    $integration = Mockery::mock(TenantIntegration::class);
    $integration->shouldNotReceive('getAttribute');

    TenantRecordPersister::persist($integration, '', [
        ['id' => 'abc', 'name' => 'foo'],
    ]);
});

it('skips when records array is empty', function (): void {
    $integration = Mockery::mock(TenantIntegration::class);
    $integration->shouldNotReceive('getAttribute');

    TenantRecordPersister::persist($integration, 'products', []);
});

it('logs a warning and skips upsert when the target table does not exist', function (): void {
    $tenant = Mockery::mock(Tenant::class);
    $tenant->shouldReceive('execute')->once()->andReturnUsing(function (Closure $callback): void {
        $schemaBuilder = Mockery::mock();
        $schemaBuilder->shouldReceive('hasTable')->once()->with('products')->andReturn(false);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);

        DB::shouldReceive('connection')->with('tenant')->zeroOrMoreTimes()->andReturn($connection);

        $callback();
    });

    $integration = Mockery::mock(TenantIntegration::class);
    $integration->shouldReceive('getAttribute')->with('id')->andReturn('01integ000000000000000001');
    $integration->shouldReceive('getAttribute')->with('tenant')->andReturn($tenant);

    Log::shouldReceive('warning')->once();
    Log::shouldReceive('info')->once();

    TenantRecordPersister::persist($integration, 'products', [
        ['id' => 'abc', 'name' => 'foo', 'tenant_id' => 't1', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
    ]);
});

it('filters out columns not present in the target table before upsert', function (): void {
    $upsertedChunk = null;

    $tenant = Mockery::mock(Tenant::class);
    $tenant->shouldReceive('execute')->once()->andReturnUsing(function (Closure $callback) use (&$upsertedChunk): void {
        $schemaBuilder = Mockery::mock();
        $schemaBuilder->shouldReceive('hasTable')->once()->with('products')->andReturn(true);
        $schemaBuilder->shouldReceive('getColumnListing')->once()->with('products')->andReturn(['id', 'name', 'tenant_id', 'updated_at']);

        $table = Mockery::mock();
        $table->shouldReceive('upsert')->once()->andReturnUsing(function (array $rows) use (&$upsertedChunk): void {
            $upsertedChunk = $rows;
        });

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);
        $connection->shouldReceive('transaction')->once()->andReturnUsing(function (Closure $transaction) {
            return $transaction();
        });
        $connection->shouldReceive('table')->once()->with('products')->andReturn($table);

        DB::shouldReceive('connection')->with('tenant')->zeroOrMoreTimes()->andReturn($connection);

        $callback();
    });

    $integration = Mockery::mock(TenantIntegration::class);
    $integration->shouldReceive('getAttribute')->with('id')->andReturn('01integ000000000000000001');
    $integration->shouldReceive('getAttribute')->with('tenant')->andReturn($tenant);

    Log::shouldReceive('info')->once();

    TenantRecordPersister::persist($integration, 'products', [
        ['id' => 'abc', 'name' => 'Milk', 'store_id' => 'store1', 'tenant_id' => 't1', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
    ]);

    expect($upsertedChunk)->toHaveCount(1)
        ->and($upsertedChunk[0])->not->toHaveKey('store_id')
        ->and($upsertedChunk[0])->not->toHaveKey('created_at')
        ->and($upsertedChunk[0])->toHaveKeys(['id', 'name', 'tenant_id', 'updated_at']);
});

it('chunks records into batches of 500', function (): void {
    $upsertCallCount = 0;
    $columns = ['id', 'codigo_erp', 'tenant_id', 'created_at', 'updated_at'];

    $records = array_map(fn (int $i): array => [
        'id' => "id{$i}",
        'codigo_erp' => (string) $i,
        'tenant_id' => 't1',
        'created_at' => '2026-01-01 00:00:00',
        'updated_at' => '2026-01-01 00:00:00',
    ], range(1, 1100));

    $tenant = Mockery::mock(Tenant::class);
    $tenant->shouldReceive('execute')->once()->andReturnUsing(function (Closure $callback) use (&$upsertCallCount, $columns): void {
        $schemaBuilder = Mockery::mock();
        $schemaBuilder->shouldReceive('hasTable')->once()->with('sales')->andReturn(true);
        $schemaBuilder->shouldReceive('getColumnListing')->once()->with('sales')->andReturn($columns);

        $table = Mockery::mock();
        $table->shouldReceive('upsert')->times(3)->andReturnUsing(function () use (&$upsertCallCount): void {
            $upsertCallCount++;
        });

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);
        $connection->shouldReceive('transaction')->once()->andReturnUsing(function (Closure $transaction) {
            return $transaction();
        });
        $connection->shouldReceive('table')->times(3)->with('sales')->andReturn($table);

        DB::shouldReceive('connection')->with('tenant')->zeroOrMoreTimes()->andReturn($connection);

        $callback();
    });

    $integration = Mockery::mock(TenantIntegration::class);
    $integration->shouldReceive('getAttribute')->with('id')->andReturn('01integ000000000000000001');
    $integration->shouldReceive('getAttribute')->with('tenant')->andReturn($tenant);

    Log::shouldReceive('info')->once();

    TenantRecordPersister::persist($integration, 'sales', $records);

    expect($upsertCallCount)->toBe(3); // 500 + 500 + 100
});

it('persists pivot rows with their own filtered payload', function (): void {
    $mainUpsertChunk = null;
    $pivotUpsertChunk = null;

    $tenant = Mockery::mock(Tenant::class);
    $tenant->shouldReceive('execute')->once()->andReturnUsing(function (Closure $callback) use (&$mainUpsertChunk, &$pivotUpsertChunk): void {
        $schemaBuilder = Mockery::mock();
        $schemaBuilder->shouldReceive('hasTable')->once()->with('products')->andReturn(true);
        $schemaBuilder->shouldReceive('hasTable')->once()->with('product_store')->andReturn(true);
        $schemaBuilder->shouldReceive('getColumnListing')->once()->with('products')->andReturn(['id', 'name', 'tenant_id', 'updated_at']);
        $schemaBuilder->shouldReceive('getColumnListing')->once()->with('product_store')->andReturn(['id', 'product_id', 'store_id', 'tenant_id', 'created_at', 'updated_at']);

        $mainTable = Mockery::mock();
        $mainTable->shouldReceive('upsert')->once()->andReturnUsing(function (array $rows) use (&$mainUpsertChunk): void {
            $mainUpsertChunk = $rows;
        });

        $pivotTable = Mockery::mock();
        $pivotTable->shouldReceive('upsert')->once()->andReturnUsing(function (array $rows) use (&$pivotUpsertChunk): void {
            $pivotUpsertChunk = $rows;
        });

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);
        $connection->shouldReceive('transaction')->once()->andReturnUsing(function (Closure $transaction) {
            return $transaction();
        });
        $connection->shouldReceive('table')->once()->with('products')->andReturn($mainTable);
        $connection->shouldReceive('table')->once()->with('product_store')->andReturn($pivotTable);

        DB::shouldReceive('connection')->with('tenant')->zeroOrMoreTimes()->andReturn($connection);

        $callback();
    });

    $integration = Mockery::mock(TenantIntegration::class);
    $integration->shouldReceive('getAttribute')->with('id')->andReturn('01integ000000000000000001');
    $integration->shouldReceive('getAttribute')->with('tenant')->andReturn($tenant);

    Log::shouldReceive('info')->twice();

    TenantRecordPersister::persist(
        $integration,
        'products',
        [
            ['id' => 'prod-1', 'name' => 'Milk', 'store_id' => 'store-1', 'tenant_id' => 'tenant-1', 'ignored' => 'x'],
        ],
        [[
            'table' => 'product_store',
            'local_key' => 'id',
            'foreign_key' => 'product_id',
            'related_key' => 'store_id',
        ]],
    );

    expect($mainUpsertChunk)->toHaveCount(1)
        ->and($mainUpsertChunk[0])->toHaveKeys(['id', 'name', 'tenant_id'])
        ->and($mainUpsertChunk[0])->not->toHaveKey('store_id')
        ->and($mainUpsertChunk[0])->not->toHaveKey('updated_at')
        ->and($pivotUpsertChunk)->toHaveCount(1)
        ->and($pivotUpsertChunk[0])->toHaveKeys(['id', 'product_id', 'store_id', 'tenant_id', 'created_at', 'updated_at'])
        ->and($pivotUpsertChunk[0]['product_id'])->toBe('prod-1')
        ->and($pivotUpsertChunk[0]['store_id'])->toBe('store-1')
        ->and($pivotUpsertChunk[0])->not->toHaveKey('ignored');
});
