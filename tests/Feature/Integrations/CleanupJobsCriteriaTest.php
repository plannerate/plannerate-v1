<?php

use App\Jobs\Cleanup\CleanupOldSalesJob;
use App\Jobs\Cleanup\CleanupOrphanSalesJob;
use App\Jobs\Cleanup\DeactivateInactiveProductsJob;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * ATENÇÃO: este arquivo deve ter UM único teste. O beforeEach troca
 * database.default e purga conexões, o que quebra o setUp do RefreshDatabase
 * a partir do segundo teste do mesmo arquivo (ver memória do projeto).
 */
beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'database.default' => 'tenant',
        'database.connections.landlord' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'multitenancy.tenant_database_connection_name' => null,
    ]);

    DB::purge('landlord');
    DB::purge('tenant');

    Schema::connection('landlord')->create('tenants', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('database')->unique();
        $table->string('status')->default('active');
        $table->timestamps();
    });

    Schema::connection('tenant')->create('products', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->string('name')->nullable();
        $table->string('ean')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('sales', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->ulid('product_id')->nullable()->index();
        $table->string('ean')->nullable();
        $table->date('sale_date')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('layers', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->ulid('product_id')->nullable()->index();
        $table->timestamps();
        $table->softDeletes();
    });
});

test('jobs de cleanup derivam as linhas do critério na execução', function (): void {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Criteria',
        'slug' => 'tenant-criteria',
        'database' => 'tenant_criteria',
        'status' => 'active',
    ]));

    $activeProduct = (string) Str::ulid();
    $inactiveProduct = (string) Str::ulid();

    DB::connection('tenant')->table('products')->insert([
        ['id' => $activeProduct, 'tenant_id' => $tenant->id, 'name' => 'Ativo', 'ean' => 'E1', 'created_at' => now(), 'updated_at' => now()],
        ['id' => $inactiveProduct, 'tenant_id' => $tenant->id, 'name' => 'Inativo', 'ean' => 'E2', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $oldSale = (string) Str::ulid();
    $recentSale = (string) Str::ulid();
    $orphanSale = (string) Str::ulid();

    DB::connection('tenant')->table('sales')->insert([
        ['id' => $oldSale, 'tenant_id' => $tenant->id, 'product_id' => $activeProduct, 'ean' => 'E1', 'sale_date' => now()->subDays(400)->toDateString(), 'created_at' => now(), 'updated_at' => now()],
        ['id' => $recentSale, 'tenant_id' => $tenant->id, 'product_id' => $activeProduct, 'ean' => 'E1', 'sale_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
        ['id' => $orphanSale, 'tenant_id' => $tenant->id, 'product_id' => (string) Str::ulid(), 'ean' => 'E9', 'sale_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Vendas antigas: corte de 200 dias apaga só a venda de 400 dias
    (new CleanupOldSalesJob(
        (string) $tenant->id,
        now()->subDays(200)->toDateString(),
        'tenant',
        false,
    ))->handle();

    // Órfãs: apaga só a venda apontando para produto inexistente
    (new CleanupOrphanSalesJob((string) $tenant->id, 'tenant', false))->handle();

    // Inativos: corte de 120 dias desativa só o produto sem nenhuma venda
    (new DeactivateInactiveProductsJob(
        (string) $tenant->id,
        now()->subDays(120)->toDateString(),
        'tenant',
        false,
    ))->handle();

    $sales = DB::connection('tenant')->table('sales')->pluck('id')->all();

    expect($sales)->toBe([$recentSale]);

    $active = DB::connection('tenant')->table('products')->where('id', $activeProduct)->first();
    $inactive = DB::connection('tenant')->table('products')->where('id', $inactiveProduct)->first();

    expect($active->deleted_at)->toBeNull()
        ->and($inactive->deleted_at)->not->toBeNull();
});
