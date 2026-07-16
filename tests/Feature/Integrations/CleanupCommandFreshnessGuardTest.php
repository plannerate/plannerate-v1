<?php

use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * ATENÇÃO: este arquivo deve ter UM único teste. O beforeEach troca
 * database.default e purga conexões, o que quebra o setUp do RefreshDatabase
 * a partir do segundo teste do mesmo arquivo (mesmo padrão do CleanupCommandTest).
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

    Schema::connection('landlord')->create('integration_apis', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->json('requests')->nullable();
        $table->json('response')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('landlord')->create('tenant_integrations', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id');
        $table->string('integration_type');
        $table->string('identifier')->nullable();
        $table->json('config')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_sync')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('landlord')->create('users', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::connection('tenant')->create('sales', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->ulid('product_id')->nullable()->index();
        $table->string('ean')->nullable();
        $table->date('sale_date')->nullable();
        $table->decimal('total_sale_value', 12, 2)->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('products', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('tenant_id')->nullable()->index();
        $table->string('name')->nullable();
        $table->string('ean')->nullable();
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

function makeFreshnessTenant(string $slug): Tenant
{
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'database' => 'tenant_'.str_replace('-', '_', $slug),
        'status' => 'active',
    ]));

    $api = IntegrationApi::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'requests' => [
            'paths' => [
                'sales' => [
                    'target_table' => 'sales',
                    'initial_days' => 200,
                    'field_map' => [],
                ],
            ],
        ],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'identifier' => $slug,
        'config' => [],
        'is_active' => true,
    ]);

    return $tenant;
}

function insertFreshnessProduct(Tenant $tenant, string $name, string $ean): string
{
    $productId = (string) Str::ulid();

    DB::connection('tenant')->table('products')->insert([
        'id' => $productId,
        'tenant_id' => $tenant->id,
        'name' => $name,
        'ean' => $ean,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $productId;
}

function insertFreshnessSale(Tenant $tenant, string $productId, string $ean, string $saleDate): void
{
    DB::connection('tenant')->table('sales')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenant->id,
        'product_id' => $productId,
        'ean' => $ean,
        'sale_date' => $saleDate,
        'total_sale_value' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('vendas velhas pulam a limpeza destrutiva; vendas frescas mantêm', function (): void {
    Bus::fake();

    // Tenant "stale": única venda tem 300 dias — o import diário aparenta quebrado
    $staleTenant = makeFreshnessTenant('cleanup-stale');
    $staleProductId = insertFreshnessProduct($staleTenant, 'Produto Parado', '7890000000001');
    insertFreshnessSale($staleTenant, $staleProductId, '7890000000001', now()->subDays(300)->toDateString());

    Artisan::call('sync:cleanup', ['--tenant' => $staleTenant->id]);

    expect(Artisan::output())
        ->toContain('puladas por segurança')
        ->not->toContain('anteriores ao período')
        ->not->toContain('produtos sem vendas no período');

    // Tenant "fresh": venda de hoje + um produto sem vendas → desativação deve rodar
    $freshTenant = makeFreshnessTenant('cleanup-fresh');
    $activeProductId = insertFreshnessProduct($freshTenant, 'Produto Ativo', '7890000000002');
    insertFreshnessProduct($freshTenant, 'Produto Sem Venda', '7890000000003');
    insertFreshnessSale($freshTenant, $activeProductId, '7890000000002', now()->toDateString());

    Artisan::call('sync:cleanup', ['--tenant' => $freshTenant->id]);

    expect(Artisan::output())
        ->not->toContain('puladas por segurança')
        ->toContain('1 produtos sem vendas no período');
});
