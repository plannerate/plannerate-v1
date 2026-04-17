<?php

use App\Console\Commands\Sync\RestoreProductsInActiveLayersCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    config([
        'database.connections.restore_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    DB::purge('restore_test');

    Schema::connection('restore_test')->create('products', function ($table) {
        $table->string('id')->primary();
        $table->string('client_id')->nullable();
        $table->string('ean')->nullable();
        $table->string('name')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    Schema::connection('restore_test')->create('layers', function ($table) {
        $table->string('id')->primary();
        $table->string('product_id')->nullable();
        $table->timestamp('deleted_at')->nullable();
    });
});

it('restores only soft-deleted products in active layers', function () {
    DB::connection('restore_test')->table('products')->insert([
        [
            'id' => 'prod_active_layer',
            'client_id' => 'client-1',
            'ean' => '111',
            'name' => 'Produto 1',
            'deleted_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 'prod_deleted_layer',
            'client_id' => 'client-1',
            'ean' => '222',
            'name' => 'Produto 2',
            'deleted_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::connection('restore_test')->table('layers')->insert([
        [
            'id' => 'layer-1',
            'product_id' => 'prod_active_layer',
            'deleted_at' => null,
        ],
        [
            'id' => 'layer-2',
            'product_id' => 'prod_deleted_layer',
            'deleted_at' => now(),
        ],
    ]);

    $command = app(RestoreProductsInActiveLayersCommand::class);
    $method = new ReflectionMethod($command, 'restoreProductsForClient');
    $method->setAccessible(true);

    /** @var array{candidate_count:int,restored_count:int,sample:\Illuminate\Support\Collection} $result */
    $result = $method->invoke($command, 'restore_test', 'client-1', false, 100);

    expect($result['candidate_count'])->toBe(1)
        ->and($result['restored_count'])->toBe(1)
        ->and(DB::connection('restore_test')->table('products')->where('id', 'prod_active_layer')->value('deleted_at'))->toBeNull()
        ->and(DB::connection('restore_test')->table('products')->where('id', 'prod_deleted_layer')->value('deleted_at'))->not->toBeNull();
});

it('does not restore in preview mode', function () {
    DB::connection('restore_test')->table('products')->insert([
        'id' => 'prod_preview',
        'client_id' => 'client-1',
        'ean' => '333',
        'name' => 'Produto Preview',
        'deleted_at' => now(),
        'updated_at' => now(),
    ]);

    DB::connection('restore_test')->table('layers')->insert([
        'id' => 'layer-preview',
        'product_id' => 'prod_preview',
        'deleted_at' => null,
    ]);

    $command = app(RestoreProductsInActiveLayersCommand::class);
    $method = new ReflectionMethod($command, 'restoreProductsForClient');
    $method->setAccessible(true);

    /** @var array{candidate_count:int,restored_count:int,sample:\Illuminate\Support\Collection} $result */
    $result = $method->invoke($command, 'restore_test', 'client-1', true, 100);

    expect($result['candidate_count'])->toBe(1)
        ->and($result['restored_count'])->toBe(0)
        ->and(DB::connection('restore_test')->table('products')->where('id', 'prod_preview')->value('deleted_at'))->not->toBeNull();
});
