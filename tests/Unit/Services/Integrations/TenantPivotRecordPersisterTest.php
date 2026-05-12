<?php

use App\Services\Integrations\TenantPivotRecordPersister;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Log::shouldReceive('debug')->zeroOrMoreTimes();
});

it('builds pivot rows, skips invalid source records, and deduplicates by unique key', function (): void {
    $upsertedRows = null;

    $schemaBuilder = Mockery::mock();
    $schemaBuilder->shouldReceive('hasTable')->once()->with('product_store')->andReturn(true);
    $schemaBuilder->shouldReceive('getColumnListing')->once()->with('product_store')->andReturn([
        'id',
        'product_id',
        'store_id',
        'tenant_id',
        'created_at',
        'updated_at',
    ]);

    $table = Mockery::mock();
    $table->shouldReceive('upsert')->once()->andReturnUsing(function (array $rows) use (&$upsertedRows): void {
        $upsertedRows = $rows;
    });

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);
    $connection->shouldReceive('table')->once()->with('product_store')->andReturn($table);

    Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context): bool {
        return $message === 'TenantPivotRecordPersister: registros duplicados removidos antes do upsert'
            && ($context['table'] ?? null) === 'product_store'
            && ($context['removed'] ?? null) === 1;
    });
    Log::shouldReceive('info')->once();

    TenantPivotRecordPersister::persist(
        $connection,
        [
            ['id' => 'prod-1', 'store_id' => 'store-1', 'tenant_id' => 'tenant-1'],
            ['id' => 'prod-1', 'store_id' => 'store-1', 'tenant_id' => 'tenant-1'],
            ['id' => 'prod-2', 'tenant_id' => 'tenant-1'],
            ['id' => 'prod-3', 'store_id' => 'store-3', 'tenant_id' => 'tenant-1'],
        ],
        [[
            'table' => 'product_store',
            'local_key' => 'id',
            'foreign_key' => 'product_id',
            'related_key' => 'store_id',
        ]],
    );

    expect($upsertedRows)->toHaveCount(2)
        ->and($upsertedRows[0])->toHaveKeys(['id', 'product_id', 'store_id', 'tenant_id', 'created_at', 'updated_at'])
        ->and($upsertedRows[1])->toHaveKeys(['id', 'product_id', 'store_id', 'tenant_id', 'created_at', 'updated_at'])
        ->and(collect($upsertedRows)->pluck('product_id')->all())->toBe(['prod-1', 'prod-3'])
        ->and(collect($upsertedRows)->pluck('store_id')->all())->toBe(['store-1', 'store-3']);
});
