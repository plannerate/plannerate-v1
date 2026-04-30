<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'multitenancy.tenant_database_connection_name' => null,
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('command reporta layers órfãos e total geral', function (): void {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Relatorio',
        'slug' => 'tenant-relatorio-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_relatorio',
        'status' => 'active',
    ]));

    $tenantId = (string) $tenant->id;
    $now = now();

    $productId = (string) str()->ulid();
    DB::table('products')->insert([
        'id' => $productId,
        'tenant_id' => $tenantId,
        'name' => 'Produto válido',
        'slug' => 'produto-valido-relatorio',
        'ean' => '7891000000119',
        'codigo_erp' => 'ERP-REP',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('layers')->insert([
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'segment_id' => (string) str()->ulid(),
            'product_id' => '01ORPHANPRODUCT000000000001',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantId,
            'segment_id' => (string) str()->ulid(),
            'product_id' => $productId,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $this->artisan(sprintf('report:layers-orphan-products --tenant=%s --limit=10', $tenantId))
        ->expectsOutputToContain('Total órfãos: 1')
        ->expectsOutputToContain('Total geral de layers órfãos: 1')
        ->assertSuccessful();
});

test('command exporta csv do relatório', function (): void {
    Storage::fake('public');

    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Export',
        'slug' => 'tenant-export-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_export',
        'status' => 'active',
    ]));

    $tenantId = (string) $tenant->id;
    $now = now();

    DB::table('layers')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => $tenantId,
        'segment_id' => (string) str()->ulid(),
        'product_id' => '01ORPHANPRODUCT000000000123',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan(sprintf('report:layers-orphan-products --tenant=%s --export=csv', $tenantId))
        ->expectsOutputToContain('CSV salvo em:')
        ->expectsOutputToContain('Link para download:')
        ->assertSuccessful();

    $files = Storage::disk('public')->allFiles('reports');
    expect($files)->not->toBeEmpty();
});
