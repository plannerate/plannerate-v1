<?php

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Cluster;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Store;
use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/*
 * ABC/BCG/Estoque Alvo somavam vendas de TODAS as lojas do tenant: buildFilters()
 * nunca populava 'store_id', embora os serviços já suportassem o filtro. Comparando
 * com uma planilha de referência do cliente, os totais batiam exatamente com a soma
 * das 4 lojas do tenant, não com a loja do planograma da gôndola analisada.
 *
 * Fix: resolver a loja do planograma (direta ou via cluster) dentro de buildFilters().
 */

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Queue::fake([ProvisionTenantDatabaseJob::class]);
    app()->forgetInstance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'));

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

function setupStoreScopeTenantCtx(string $subdomain): Tenant
{
    $tenant = Tenant::query()->create([
        'name' => strtoupper($subdomain),
        'slug' => $subdomain,
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => $subdomain.'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);

    if (! Schema::connection('tenant')->hasTable('gondolas')) {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations',
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    return $tenant;
}

/**
 * @return array<string, mixed>
 */
function resolvedAnalysisFilters(Gondola $gondola): array
{
    $controller = app(GondolaAnalysisController::class);

    $method = new ReflectionMethod($controller, 'buildFilters');
    $method->setAccessible(true);

    return $method->invoke($controller, Request::create('/'), $gondola);
}

test('buildFilters resolve store_id direto do planograma da gôndola', function (): void {
    $tenant = setupStoreScopeTenantCtx('store-scope-direct');

    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $planogram = Planogram::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'cluster_id' => null,
    ]);
    $gondola = Gondola::factory()->create([
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogram->id,
    ]);

    $filters = resolvedAnalysisFilters($gondola);

    expect($filters['store_id'])->toBe((string) $store->id);
});

test('buildFilters resolve store_id via cluster quando o planograma não tem loja direta', function (): void {
    $tenant = setupStoreScopeTenantCtx('store-scope-cluster');

    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $cluster = Cluster::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Cluster de Teste',
    ]);
    $planogram = Planogram::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => null,
        'cluster_id' => $cluster->id,
    ]);
    $gondola = Gondola::factory()->create([
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogram->id,
    ]);

    $filters = resolvedAnalysisFilters($gondola);

    expect($filters['store_id'])->toBe((string) $store->id);
});

test('buildFilters não adiciona store_id quando o planograma não tem loja nem cluster', function (): void {
    $tenant = setupStoreScopeTenantCtx('store-scope-none');

    $planogram = Planogram::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => null,
        'cluster_id' => null,
    ]);
    $gondola = Gondola::factory()->create([
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogram->id,
    ]);

    $filters = resolvedAnalysisFilters($gondola);

    expect($filters)->not->toHaveKey('store_id');
});
