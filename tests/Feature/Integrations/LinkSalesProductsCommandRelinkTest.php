<?php

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
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

    Schema::connection('landlord')->create('users', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::connection('tenant')->create('products', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->string('name')->nullable();
        $table->string('ean')->nullable();
        $table->string('codigo_erp')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('sales', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->ulid('product_id')->nullable()->index();
        $table->string('ean')->nullable();
        $table->string('codigo_erp')->nullable();
        $table->date('sale_date')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

test('re-vincula vendas sem produto E vendas apontando para produto errado, de forma determinística', function (): void {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Relink',
        'slug' => 'tenant-relink',
        'database' => 'tenant_relink',
        'status' => 'active',
    ]));

    $productOlder = (string) Str::ulid();
    $productNewer = (string) Str::ulid();
    $productWrong = (string) Str::ulid();

    DB::connection('tenant')->table('products')->insert([
        [
            'id' => $productOlder,
            'tenant_id' => $tenant->id,
            'name' => 'Produto Certo (mais antigo)',
            'ean' => 'E1',
            'codigo_erp' => 'ERP1',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ],
        [
            // Duplicata de codigo_erp: o mais antigo deve vencer (determinismo)
            'id' => $productNewer,
            'tenant_id' => $tenant->id,
            'name' => 'Produto Duplicado (mais novo)',
            'ean' => 'E1B',
            'codigo_erp' => 'ERP1',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $productWrong,
            'tenant_id' => $tenant->id,
            'name' => 'Produto Errado',
            'ean' => 'E2',
            'codigo_erp' => 'ERP2',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $saleUnlinked = (string) Str::ulid();
    $saleMislinked = (string) Str::ulid();

    DB::connection('tenant')->table('sales')->insert([
        [
            'id' => $saleUnlinked,
            'tenant_id' => $tenant->id,
            'product_id' => null,
            'ean' => null,
            'codigo_erp' => 'ERP1',
            'sale_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            // Aponta para o produto errado — o vínculo antigo (product_id IS NULL) ignorava
            'id' => $saleMislinked,
            'tenant_id' => $tenant->id,
            'product_id' => $productWrong,
            'ean' => 'E2',
            'codigo_erp' => 'ERP1',
            'sale_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $exitCode = Artisan::call('sync:link-sales', ['--tenant' => $tenant->id]);

    expect($exitCode)->toBe(0);

    $unlinked = DB::connection('tenant')->table('sales')->where('id', $saleUnlinked)->first();
    $mislinked = DB::connection('tenant')->table('sales')->where('id', $saleMislinked)->first();

    expect($unlinked->product_id)->toBe($productOlder)
        ->and($unlinked->ean)->toBe('E1')
        ->and($mislinked->product_id)->toBe($productOlder)
        ->and($mislinked->ean)->toBe('E1');
});
