<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['multitenancy.tenant_database_connection_name' => null]);

    if (! Schema::connection('landlord')->hasTable('tenants')) {
        Schema::connection('landlord')->create('tenants', function ($table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }
});

function insertDimTenant(string $name, string $slug): string
{
    $id = (string) Str::ulid();

    DB::connection('landlord')->table('tenants')->insert([
        'id' => $id,
        'name' => $name,
        'slug' => $slug,
        'database' => null,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

test('command reporta cobertura de dimensões por categoria', function (): void {
    $slug = 'tenant-dim-'.Str::random(6);
    $tenantId = insertDimTenant('Tenant Dimensoes', $slug);
    $categoryId = (string) Str::ulid();
    $now = now();

    // Produto com dimensões
    DB::table('products')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenantId,
        'category_id' => $categoryId,
        'name' => 'Produto Com Dimensao',
        'slug' => 'prod-com-dim-'.Str::random(6),
        'ean' => '7891000000001',
        'status' => 'published',
        'width' => 8.5,
        'height' => 20.0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Produto sem dimensões
    DB::table('products')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenantId,
        'category_id' => $categoryId,
        'name' => 'Produto Sem Dimensao',
        'slug' => 'prod-sem-dim-'.Str::random(6),
        'ean' => '7891000000002',
        'status' => 'published',
        'width' => null,
        'height' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Produto draft — excluído da contagem
    DB::table('products')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenantId,
        'category_id' => $categoryId,
        'name' => 'Produto Draft',
        'slug' => 'prod-draft-dim-'.Str::random(6),
        'ean' => '7891000000003',
        'status' => 'draft',
        'width' => 10.0,
        'height' => 15.0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan("auto-planogram:dimensions-report --tenant={$slug}")
        ->expectsOutputToContain('Tenant Dimensoes')
        ->expectsOutputToContain('Com dimensão: 1')
        ->expectsOutputToContain('Sem dimensão: 1')
        ->expectsOutputToContain('50,0%')
        ->assertSuccessful();
});

test('command --missing lista EANs dos produtos sem dimensão', function (): void {
    $slug = 'tenant-miss-'.Str::random(6);
    $tenantId = insertDimTenant('Tenant Missing', $slug);
    $now = now();

    DB::table('products')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenantId,
        'category_id' => (string) Str::ulid(),
        'name' => 'Leite sem dim',
        'slug' => 'leite-sem-dim-'.Str::random(6),
        'ean' => '7891000099999',
        'status' => 'published',
        'width' => null,
        'height' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan("auto-planogram:dimensions-report --tenant={$slug} --missing --limit=5")
        ->expectsOutputToContain('7891000099999')
        ->expectsOutputToContain('Leite sem dim')
        ->assertSuccessful();
});

test('command retorna failure quando tenant não encontrado', function (): void {
    $this->artisan('auto-planogram:dimensions-report --tenant=inexistente-xyz')
        ->assertFailed();
});
