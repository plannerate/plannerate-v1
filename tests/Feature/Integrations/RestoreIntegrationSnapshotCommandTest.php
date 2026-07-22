<?php

use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
 * O snapshot em last_payload é a única cópia de blueprint + credenciais fora do banco
 * landlord. Ele carrega o FQCN dos models que o produziram, mas a restauração NÃO usa
 * esse campo para resolver classe — é o que mantém snapshots antigos restauráveis
 * quando o motor muda de namespace.
 */

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Storage::fake('local');
});

/**
 * @param  array{tenant_integration?: string, integration_api?: string}  $models
 */
function writeIntegrationSnapshot(string $tenantId, string $apiId, array $models = []): void
{
    Storage::disk('local')->put("last_payload/{$tenantId}.json", json_encode([
        'tenant_integration' => [
            'model' => $models['tenant_integration'] ?? TenantIntegration::class,
            'data' => [
                'tenant_id' => $tenantId,
                'integration_type' => $apiId,
                'is_active' => true,
                'config' => [
                    'connection' => ['base_url' => 'https://erp.exemplo.test'],
                    'auth' => ['type' => 'basic', 'credentials' => ['username' => 'u', 'password' => 'p']],
                ],
            ],
        ],
        'integration_api' => [
            'model' => $models['integration_api'] ?? IntegrationApi::class,
            'data' => [
                'id' => $apiId,
                'name' => 'ERP Exemplo',
                'slug' => 'erp-exemplo',
                'description' => null,
                'requests' => ['paths' => ['products' => ['fallback_path' => '/produtos']]],
                'response' => ['items_path' => 'data'],
                'is_active' => true,
            ],
        ],
    ]));
}

function makeSnapshotTenant(): Tenant
{
    return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'name' => 'Tenant Snapshot',
        'slug' => 'tenant-snapshot',
        'database' => 'tenant_snapshot',
        'status' => 'active',
    ]));
}

test('restaura blueprint e integração preservando os ids do snapshot', function (): void {
    $tenant = makeSnapshotTenant();
    $apiId = (string) str()->ulid();

    writeIntegrationSnapshot((string) $tenant->id, $apiId);

    Artisan::call('integration:restore-snapshot', ['--no-interaction' => true]);

    $api = IntegrationApi::query()->whereKey($apiId)->first();
    $integration = TenantIntegration::query()->where('tenant_id', $tenant->id)->first();

    expect($api)->not->toBeNull()
        ->and($api->slug)->toBe('erp-exemplo')
        ->and($integration)->not->toBeNull()
        ->and($integration->integration_type)->toBe($apiId)
        ->and(data_get($integration->config, 'connection.base_url'))->toBe('https://erp.exemplo.test');
});

test('snapshot gravado por outro namespace de model ainda restaura', function (): void {
    $tenant = makeSnapshotTenant();
    $apiId = (string) str()->ulid();

    // Simula um snapshot produzido antes (ou depois) de o motor mudar de namespace:
    // a chave `model` é metadado, não resolvedor de classe.
    writeIntegrationSnapshot((string) $tenant->id, $apiId, [
        'tenant_integration' => 'Legacy\\Namespace\\TenantIntegration',
        'integration_api' => 'Legacy\\Namespace\\IntegrationApi',
    ]);

    Artisan::call('integration:restore-snapshot', ['--no-interaction' => true]);

    expect(IntegrationApi::query()->whereKey($apiId)->exists())->toBeTrue()
        ->and(TenantIntegration::query()->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

test('não restaura quando o tenant não existe no landlord', function (): void {
    $apiId = (string) str()->ulid();

    writeIntegrationSnapshot((string) str()->ulid(), $apiId);

    Artisan::call('integration:restore-snapshot', ['--no-interaction' => true]);

    expect(IntegrationApi::query()->whereKey($apiId)->exists())->toBeFalse();
});

test('dry-run não grava nada', function (): void {
    $tenant = makeSnapshotTenant();
    $apiId = (string) str()->ulid();

    writeIntegrationSnapshot((string) $tenant->id, $apiId);

    Artisan::call('integration:restore-snapshot', ['--dry-run' => true, '--no-interaction' => true]);

    expect(IntegrationApi::query()->whereKey($apiId)->exists())->toBeFalse()
        ->and(TenantIntegration::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
});
