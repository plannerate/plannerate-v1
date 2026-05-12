<?php

use App\Services\Integrations\TenantUpsertRecordPreparer;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('filters columns, normalizes ids, and keeps the last duplicate record', function (): void {
    Log::shouldReceive('warning')->once();
    Log::shouldReceive('info')->once();

    $prepared = TenantUpsertRecordPreparer::prepare(
        [
            ['id' => '  prod-1  ', 'name' => 'Old name', 'tenant_id' => 'tenant-1', 'ignored' => 'x'],
            ['id' => 'prod-1', 'name' => 'New name', 'tenant_id' => 'tenant-1', 'ignored' => 'y'],
            ['id' => 'prod-2', 'name' => 'Milk', 'tenant_id' => 'tenant-1', 'ignored' => 'z'],
            ['id' => null, 'name' => 'Invalid', 'tenant_id' => 'tenant-1'],
        ],
        ['id', 'name', 'tenant_id'],
        'products',
    );

    expect($prepared)->toHaveCount(2)
        ->and($prepared[0])->toBe([
            'id' => 'prod-1',
            'name' => 'New name',
            'tenant_id' => 'tenant-1',
        ])
        ->and($prepared[1])->toBe([
            'id' => 'prod-2',
            'name' => 'Milk',
            'tenant_id' => 'tenant-1',
        ]);
});

it('resolves upsert update columns excluding id and created_at', function (): void {
    $columns = TenantUpsertRecordPreparer::resolveUpdateColumns([
        'id' => 'prod-1',
        'name' => 'Milk',
        'tenant_id' => 'tenant-1',
        'created_at' => '2026-01-01 00:00:00',
        'updated_at' => '2026-01-01 00:00:00',
    ]);

    expect($columns)->toBe(['name', 'tenant_id', 'updated_at']);
});

it('deduplicates by normalized id and discards invalid ids', function (): void {
    Log::shouldReceive('info')->once();

    $rows = TenantUpsertRecordPreparer::deduplicateById([
        ['id' => '  item-1  ', 'name' => 'First'],
        ['id' => 'item-1', 'name' => 'Second'],
        ['id' => '', 'name' => 'Invalid empty'],
        ['id' => null, 'name' => 'Invalid null'],
        ['id' => 'item-2', 'name' => 'Another'],
    ]);

    expect($rows)->toBe([
        ['id' => 'item-1', 'name' => 'Second'],
        ['id' => 'item-2', 'name' => 'Another'],
    ]);
});
