<?php

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Sysmo\SysmoSingleProductIntegrationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

test('sysmo single product service fetches and persists one product', function (): void {
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Sysmo',
        'slug' => 'tenant-sysmo-'.fake()->numberBetween(100, 999),
        'database' => 'tenant_sysmo',
        'status' => 'active',
    ]));

    $integration = TenantIntegration::query()->create([
        'tenant_id' => (string) $tenant->id,
        'integration_type' => 'sysmo',
        'http_method' => 'POST',
        'api_url' => 'https://sysmo.test',
        'is_active' => true,
        'config' => [
            'connection' => [
                'base_url' => 'https://sysmo.test',
            ],
            'processing' => [
                'partner_key' => 'Proplanner',
                'empresa' => '10623678000184',
            ],
        ],
    ]);

    Http::fake([
        'https://sysmo.test/*' => Http::response([
            'produto' => '7896038308484',
            'descricao' => 'PRODUTO TESTE',
            'gtins' => [
                'completo' => [
                    [
                        'gtin' => '7896038308484',
                        'principal' => 'S',
                    ],
                ],
            ],
            'cadastro_ativo' => 'S',
            'ativo_na_empresa' => 'S',
            'pertence_ao_mix' => 'S',
        ], 200),
    ]);

    $result = app(SysmoSingleProductIntegrationService::class)->fetchAndPersist(
        integration: $integration,
        produto: '7896038308484',
    );

    $product = DB::table('products')
        ->where('tenant_id', (string) $tenant->id)
        ->where('ean', '7896038308484')
        ->first();

    expect($result['found'] ?? false)->toBeTrue()
        ->and($product)->not->toBeNull()
        ->and($product?->codigo_erp)->toBe('7896038308484');
});
