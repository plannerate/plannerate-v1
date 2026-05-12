<?php

use App\Models\TenantIntegration;
use Tests\TestCase;

uses(TestCase::class);
use App\Services\Integrations\TenantRecordPersister;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Models\Tenant;

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
        Schema::shouldReceive('connection->hasTable')->andReturn(false);
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
        Schema::shouldReceive('connection->hasTable')->andReturn(true);
        Schema::shouldReceive('connection->getColumnListing')->andReturn(['id', 'name', 'tenant_id', 'updated_at']);
        DB::shouldReceive('connection->table->upsert')
            ->andReturnUsing(function () use (&$upsertedChunk): void {
                $upsertedChunk = func_get_arg(0);
            });
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
        Schema::shouldReceive('connection->hasTable')->andReturn(true);
        Schema::shouldReceive('connection->getColumnListing')->andReturn($columns);
        DB::shouldReceive('connection->table->upsert')->andReturnUsing(function () use (&$upsertCallCount): void {
            $upsertCallCount++;
        });
        $callback();
    });

    $integration = Mockery::mock(TenantIntegration::class);
    $integration->shouldReceive('getAttribute')->with('id')->andReturn('01integ000000000000000001');
    $integration->shouldReceive('getAttribute')->with('tenant')->andReturn($tenant);

    Log::shouldReceive('info')->once();

    TenantRecordPersister::persist($integration, 'sales', $records);

    expect($upsertCallCount)->toBe(3); // 500 + 500 + 100
});
