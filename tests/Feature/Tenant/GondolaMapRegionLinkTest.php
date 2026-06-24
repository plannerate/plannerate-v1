<?php

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('permission.rbac_enabled', true);
    Queue::fake([ProvisionTenantDatabaseJob::class]);
    app()->forgetInstance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'));

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('não permite vincular uma região do mapa já ocupada por outra gôndola da mesma loja', function (): void {
    $context = setupMapLinkTenantCtx('map-link-taken');
    $this->actingAs($context['user']);

    $regionId = (string) Str::ulid();
    $store = makeStoreWithRegion($context['tenant']->id, $regionId);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id' => $store->id,
    ]);

    // Gôndola A já ocupa a região.
    Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'linked_map_gondola_id' => $regionId,
    ]);

    // Gôndola B tenta vincular à mesma região.
    $gondolaB = Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'linked_map_gondola_id' => null,
    ]);

    $response = $this->put(route('tenant.gondolas.update', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
        'gondola' => $gondolaB->id,
    ]), gondolaUpdatePayload($planogram->id, $gondolaB, ['linked_map_gondola_id' => $regionId]));

    $response->assertSessionHasErrors('linked_map_gondola_id');
    expect($gondolaB->fresh()->linked_map_gondola_id)->toBeNull();
});

test('permite vincular uma gôndola a uma região do mapa livre', function (): void {
    $context = setupMapLinkTenantCtx('map-link-free');
    $this->actingAs($context['user']);

    $regionId = (string) Str::ulid();
    $store = makeStoreWithRegion($context['tenant']->id, $regionId);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id' => $store->id,
    ]);

    $gondola = Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'linked_map_gondola_id' => null,
    ]);

    $response = $this->put(route('tenant.gondolas.update', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
        'gondola' => $gondola->id,
    ]), gondolaUpdatePayload($planogram->id, $gondola, ['linked_map_gondola_id' => $regionId]));

    $response->assertSessionHasNoErrors();
    expect($gondola->fresh()->linked_map_gondola_id)->toBe($regionId);
});

test('a mesma gôndola pode manter o vínculo com sua própria região', function (): void {
    $context = setupMapLinkTenantCtx('map-link-self');
    $this->actingAs($context['user']);

    $regionId = (string) Str::ulid();
    $store = makeStoreWithRegion($context['tenant']->id, $regionId);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id' => $store->id,
    ]);

    $gondola = Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'linked_map_gondola_id' => $regionId,
    ]);

    $response = $this->put(route('tenant.gondolas.update', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
        'gondola' => $gondola->id,
    ]), gondolaUpdatePayload($planogram->id, $gondola, ['linked_map_gondola_id' => $regionId]));

    $response->assertSessionHasNoErrors();
    expect($gondola->fresh()->linked_map_gondola_id)->toBe($regionId);
});

test('não permite trocar a loja do planograma enquanto houver gôndolas vinculadas ao mapa', function (): void {
    $context = setupMapLinkTenantCtx('store-change-blocked');
    $this->actingAs($context['user']);

    $regionId = (string) Str::ulid();
    $storeAtual = makeStoreWithRegion($context['tenant']->id, $regionId);
    $outraLoja = Store::factory()->create(['tenant_id' => $context['tenant']->id]);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id' => $storeAtual->id,
    ]);

    Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'linked_map_gondola_id' => $regionId,
    ]);

    $response = $this->put(route('tenant.planograms.update', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]), planogramUpdatePayload($planogram, ['store_id' => $outraLoja->id]));

    $response->assertSessionHasErrors('store_id');
    expect($planogram->fresh()->store_id)->toBe($storeAtual->id);
});

test('permite trocar a loja do planograma quando nenhuma gôndola está vinculada ao mapa', function (): void {
    $context = setupMapLinkTenantCtx('store-change-allowed');
    $this->actingAs($context['user']);

    $storeAtual = Store::factory()->create(['tenant_id' => $context['tenant']->id]);
    $outraLoja = Store::factory()->create(['tenant_id' => $context['tenant']->id]);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id' => $storeAtual->id,
    ]);

    Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'linked_map_gondola_id' => null,
    ]);

    $response = $this->put(route('tenant.planograms.update', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]), planogramUpdatePayload($planogram, ['store_id' => $outraLoja->id]));

    $response->assertSessionHasNoErrors();
    expect($planogram->fresh()->store_id)->toBe($outraLoja->id);
});

/**
 * Cria uma loja com uma única região de mapa identificada por $regionId.
 */
function makeStoreWithRegion(string $tenantId, string $regionId): Store
{
    return Store::factory()->create([
        'tenant_id' => $tenantId,
        'map_image_path' => 'store-maps/exemplo.png',
        'map_regions' => [
            ['id' => $regionId, 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'type' => 'gondola'],
        ],
    ]);
}

/**
 * Monta o payload de atualização da gôndola exigido pelo GondolaUpdateRequest.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gondolaUpdatePayload(string $planogramId, Gondola $gondola, array $overrides = []): array
{
    return array_merge([
        'planogram_id' => $planogramId,
        'name' => $gondola->name,
        'slug' => $gondola->slug,
        'num_modulos' => $gondola->num_modulos ?? 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'published',
    ], $overrides);
}

/**
 * Monta o payload de atualização do planograma exigido pelo PlanogramUpdateRequest.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function planogramUpdatePayload(Planogram $planogram, array $overrides = []): array
{
    return array_merge([
        'name' => $planogram->name,
        'slug' => $planogram->slug,
        'type' => $planogram->type ?? 'planograma',
        'status' => 'published',
        'store_id' => $planogram->store_id,
    ], $overrides);
}

/**
 * @return array{subdomain: string, host: string, tenant: Tenant, user: User}
 */
function setupMapLinkTenantCtx(string $subdomain): array
{
    $user = User::factory()->create();

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

    // Migra o schema tenant apenas se ainda não existir. Sob RefreshDatabase com
    // sqlite :memory:, a conexão tenant pode já ter sido migrada — recriar quebraria.
    if (! Schema::connection('tenant')->hasTable('gondolas')) {
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

    return [
        'subdomain' => $subdomain,
        'host' => $subdomain.'.'.config('app.landlord_domain'),
        'tenant' => $tenant,
        'user' => $user,
    ];
}
