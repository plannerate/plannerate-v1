<?php

use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    Schema::connection('landlord')->create('plans', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    });

    Schema::connection('landlord')->create('tenants', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('database')->unique();
        $table->string('status')->default('active');
        $table->foreignUlid('plan_id')->nullable();
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
        $table->timestamp('email_verified_at')->nullable();
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

test('cleanup command filters active integrations by tenant option', function (): void {
    $tenantOne = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Um',
        'slug' => 'tenant-um',
        'database' => 'tenant_um',
        'status' => 'active',
    ]));

    $tenantTwo = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Dois',
        'slug' => 'tenant-dois',
        'database' => 'tenant_dois',
        'status' => 'active',
    ]));

    $api = IntegrationApi::query()->create([
        'name' => 'API Ativa',
        'slug' => 'api-ativa',
        'requests' => [
            'paths' => [],
        ],
        'response' => [
            'items_path' => 'data',
        ],
        'is_active' => true,
    ]);

    TenantIntegration::query()->create([
        'tenant_id' => $tenantOne->id,
        'integration_type' => $api->id,
        'identifier' => 'um',
        'config' => [],
        'is_active' => true,
    ]);

    TenantIntegration::query()->create([
        'tenant_id' => $tenantTwo->id,
        'integration_type' => $api->id,
        'identifier' => 'dois',
        'config' => [],
        'is_active' => true,
    ]);

    Artisan::call('sync:cleanup', [
        '--tenant' => $tenantOne->id,
    ]);

    $output = Artisan::output();

    expect($output)
        ->toContain('🏢 Tenant Um')
        ->toContain('Verificação concluída.')
        ->not->toContain('🏢 Tenant Dois');
});
