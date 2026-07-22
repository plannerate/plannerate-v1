<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelIntegrations\Jobs\SyncSingleProductJob;
use Callcocam\LaravelIntegrations\Models\IntegrationApi;
use Callcocam\LaravelIntegrations\Models\TenantIntegration;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    migrateIntegrationsLandlord();

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

if (! function_exists('setupSyncCtrlTenant')) {
    function setupSyncCtrlTenant(string $subdomain, User $user): Tenant
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

        if (! Schema::connection('tenant')->hasTable('products')) {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations',
                '--force' => true,
                '--no-interaction' => true,
            ]);
        }

        $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role);

        return $tenant;
    }
}

if (! function_exists('makeCtrlIntegration')) {
    function makeCtrlIntegration(Tenant $tenant, bool $active = true): void
    {
        $api = IntegrationApi::query()->create([
            'name' => 'Sysmo',
            'slug' => 'sysmo-'.Str::lower(Str::random(6)),
            'requests' => ['lookups' => ['sales' => ['fallback_path' => '/x']]],
            'response' => [],
            'is_active' => true,
        ]);

        TenantIntegration::query()->create([
            'tenant_id' => $tenant->id,
            'integration_type' => $api->id,
            'is_active' => $active,
            'config' => ['connection' => ['base_url' => 'https://api.sysmo.test']],
        ]);
    }
}

if (! function_exists('makeCtrlProductAndStore')) {
    /** @return array{0: Product, 1: Store} */
    function makeCtrlProductAndStore(Tenant $tenant): array
    {
        $store = Store::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Loja Centro',
            'status' => 'published',
            'code' => '73',
            'document' => '12345678000199',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Produto Original',
            'slug' => 'produto-original',
            'status' => 'published',
            'ean' => '7891234567895',
            'codigo_erp' => '66526',
        ]);

        return [$product, $store];
    }
}

test('despacha o job com loja e flag de atualização de produto', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupSyncCtrlTenant('tenant-sync-ctrl', $user);
    makeCtrlIntegration($tenant);
    [$product, $store] = makeCtrlProductAndStore($tenant);

    Queue::fake();

    $host = 'tenant-sync-ctrl.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.sync-single', ['subdomain' => 'tenant-sync-ctrl'], false), [
            'product' => $product->id,
            'store_id' => $store->id,
            'update_product' => true,
        ]);

    $response->assertRedirect();

    Queue::assertPushed(SyncSingleProductJob::class, function (SyncSingleProductJob $job) use ($tenant, $product, $store): bool {
        return $job->tenantId === $tenant->id
            && $job->productId === $product->id
            && $job->storeId === $store->id
            && $job->updateProduct === true;
    });
});

test('store_id é obrigatório', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupSyncCtrlTenant('tenant-sync-ctrl-req', $user);
    makeCtrlIntegration($tenant);
    [$product] = makeCtrlProductAndStore($tenant);

    Queue::fake();

    $host = 'tenant-sync-ctrl-req.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.sync-single', ['subdomain' => 'tenant-sync-ctrl-req'], false), [
            'product' => $product->id,
        ]);

    $response->assertSessionHasErrors('store_id');
    Queue::assertNothingPushed();
});

test('sem integração ativa: avisa e não despacha', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupSyncCtrlTenant('tenant-sync-ctrl-noint', $user);
    [$product, $store] = makeCtrlProductAndStore($tenant);

    Queue::fake();

    $host = 'tenant-sync-ctrl-noint.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.sync-single', ['subdomain' => 'tenant-sync-ctrl-noint'], false), [
            'product' => $product->id,
            'store_id' => $store->id,
        ]);

    $response->assertRedirect();
    Queue::assertNothingPushed();
});
