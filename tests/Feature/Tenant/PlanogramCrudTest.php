<?php

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowPlanogramStep;
use App\Models\WorkflowTemplate;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant admin can execute planogram crud in tenant context', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForPlanograms('tenant-planograms-crud');
    assignTenantAdminRoleForPlanograms($user, $tenant->id);

    $host = 'tenant-planograms-crud.'.config('app.landlord_domain');
    $subdomain = 'tenant-planograms-crud';

    $store = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja A',
        'slug' => 'loja-a',
        'status' => 'published',
    ]);

    $cluster = Cluster::query()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Cluster A',
        'slug' => 'cluster-a',
        'status' => 'published',
    ]);

    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Categoria A',
        'slug' => 'categoria-a',
        'status' => 'published',
    ]);

    $createResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.planograms.store', ['subdomain' => $subdomain], false), [
            'name' => 'Planograma 1',
            'slug' => 'planograma-1',
            'type' => 'planograma',
            'store_id' => $store->id,
            'cluster_id' => $cluster->id,
            'category_id' => $category->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'order' => 1,
            'status' => 'draft',
        ]);

    $planogram = Planogram::query()
        ->where('tenant_id', $tenant->id)
        ->where('slug', 'planograma-1')
        ->firstOrFail();

    // Após criar, redireciona para a edição com a aba de workflow ativa.
    $createResponse->assertRedirect(route('tenant.planograms.edit', [
        'subdomain' => $subdomain,
        'planogram' => $planogram->id,
        'tab' => 'workflow',
    ], false));

    $updateResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->put(route('tenant.planograms.update', ['subdomain' => $subdomain, 'planogram' => $planogram->id], false), [
            'name' => 'Planograma 2',
            'slug' => 'planograma-2',
            'type' => 'realograma',
            'store_id' => $store->id,
            'cluster_id' => $cluster->id,
            'category_id' => $category->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'order' => 2,
            'status' => 'published',
        ]);

    $updateResponse->assertRedirect(route('tenant.planograms.index', ['subdomain' => $subdomain], false));

    $planogram->refresh();
    expect($planogram->name)->toBe('Planograma 2');
    expect($planogram->type)->toBe('realograma');
    expect($planogram->status)->toBe('published');

    $deleteResponse = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->delete(route('tenant.planograms.destroy', ['subdomain' => $subdomain, 'planogram' => $planogram->id], false));

    $deleteResponse->assertRedirect(route('tenant.planograms.index', ['subdomain' => $subdomain], false));
    expect($planogram->fresh())->toBeNull();
});

test('force deletes a trashed planogram permanently', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForPlanograms('tenant-planograms-force');
    assignTenantAdminRoleForPlanograms($user, $tenant->id);

    $planogram = Planogram::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Para excluir',
        'slug' => 'para-excluir',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    // Envia para a lixeira primeiro (soft delete).
    $planogram->delete();
    expect($planogram->trashed())->toBeTrue();

    // Excluir de novo um planograma que já está na lixeira deve removê-lo em definitivo.
    $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-force.'.config('app.landlord_domain')])
        ->delete(route('tenant.planograms.destroy', ['subdomain' => 'tenant-planograms-force', 'planogram' => $planogram->id], false))
        ->assertRedirect();

    expect(Planogram::withTrashed()->find($planogram->id))->toBeNull();
});

test('planogram store requires store, category and sales period', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForPlanograms('tenant-planograms-required');
    assignTenantAdminRoleForPlanograms($user, $tenant->id);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-required.'.config('app.landlord_domain')])
        ->post(route('tenant.planograms.store', ['subdomain' => 'tenant-planograms-required'], false), [
            'name' => 'Sem obrigatórios',
            'type' => 'planograma',
            'status' => 'draft',
        ]);

    $response->assertSessionHasErrors(['store_id', 'category_id', 'start_date', 'end_date']);
});

test('planogram store generates workflow steps from published templates', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForPlanograms('tenant-planograms-workflow');
    assignTenantAdminRoleForPlanograms($user, $tenant->id);

    $store = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja WF',
        'slug' => 'loja-wf',
        'status' => 'published',
    ]);

    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Categoria WF',
        'slug' => 'categoria-wf',
        'status' => 'published',
    ]);

    $template = WorkflowTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Etapa WF',
        'slug' => 'etapa-wf',
        'status' => 'published',
        'suggested_order' => 1,
    ]);

    $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-workflow.'.config('app.landlord_domain')])
        ->post(route('tenant.planograms.store', ['subdomain' => 'tenant-planograms-workflow'], false), [
            'name' => 'Planograma WF',
            'slug' => 'planograma-wf',
            'type' => 'planograma',
            'store_id' => $store->id,
            'category_id' => $category->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'draft',
        ])
        ->assertSessionHasNoErrors();

    $planogram = Planogram::query()
        ->where('tenant_id', $tenant->id)
        ->where('slug', 'planograma-wf')
        ->firstOrFail();

    // O workflow deve ser gerado na criação a partir dos templates publicados.
    expect(WorkflowPlanogramStep::query()
        ->where('planogram_id', $planogram->id)
        ->where('workflow_template_id', $template->id)
        ->exists())->toBeTrue();
});

test('tenant planograms index is isolated by tenant_id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenantForPlanograms('tenant-planograms-a');
    $tenantB = makeTenantForPlanograms('tenant-planograms-b');
    assignTenantAdminRoleForPlanograms($user, $tenantA->id);

    Planogram::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Planograma A',
        'slug' => 'planograma-a',
        'type' => 'planograma',
        'status' => 'published',
    ]);

    Planogram::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Planograma B',
        'slug' => 'planograma-b',
        'type' => 'realograma',
        'status' => 'published',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-a.'.config('app.landlord_domain')])
        ->get(route('tenant.planograms.index', ['subdomain' => 'tenant-planograms-a'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Index')
            ->has('planograms.data', 1)
            ->where('planograms.data.0.slug', 'planograma-a'));
});

test('orphan layers page lists only invalid layers from current tenant', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenantForPlanograms('tenant-planograms-orphans-a');
    $tenantB = makeTenantForPlanograms('tenant-planograms-orphans-b');
    assignTenantAdminRoleForPlanograms($user, $tenantA->id);

    $validProduct = Product::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Produto válido',
        'slug' => 'produto-valido-planograma',
        'ean' => '7891000000119',
        'codigo_erp' => 'ERP-PLAN-1',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    DB::table('layers')->insert([
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantA->id,
            'segment_id' => (string) str()->ulid(),
            'product_id' => '01ORPHANPRODUCT000000000001',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantA->id,
            'segment_id' => (string) str()->ulid(),
            'product_id' => $validProduct->id,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) str()->ulid(),
            'tenant_id' => $tenantB->id,
            'segment_id' => (string) str()->ulid(),
            'product_id' => '01ORPHANPRODUCT000000000099',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-orphans-a.'.config('app.landlord_domain')])
        ->get(route('tenant.planograms.orphan-layers', ['subdomain' => 'tenant-planograms-orphans-a'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/OrphanLayers')
            ->has('orphans.data', 1)
            ->where('orphans.data.0.product_id_atual', '01ORPHANPRODUCT000000000001'));
});

test('maps returns store regions with active execution permissions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForPlanograms('tenant-planograms-maps');
    assignTenantAdminRoleForPlanograms($user, $tenant->id);

    $store = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja Mapa',
        'slug' => 'loja-mapa',
        'status' => 'published',
        'map_image_path' => 'store-maps/mapa-loja.png',
        'map_regions' => [
            [
                'id' => 'regiao-1',
                'x' => 40,
                'y' => 20,
                'width' => 120,
                'height' => 80,
                'shape' => 'rectangle',
                'label' => 'G-01',
            ],
        ],
    ]);

    $cluster = Cluster::query()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Cluster Mapa',
        'slug' => 'cluster-mapa',
        'status' => 'published',
    ]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'cluster_id' => $cluster->id,
        'name' => 'Planograma Mapa',
        'slug' => 'planograma-mapa',
        'type' => 'planograma',
        'status' => 'published',
    ]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogram->id,
        'linked_map_gondola_id' => 'regiao-1',
        'name' => 'Gondola 01',
        'slug' => 'gondola-01',
        'status' => 'published',
    ]);

    $store->update([
        'map_regions' => [
            [
                'id' => 'regiao-1',
                'x' => 40,
                'y' => 20,
                'width' => 120,
                'height' => 80,
                'shape' => 'rectangle',
                'label' => 'G-01',
            ],
        ],
    ]);

    $template = WorkflowTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Etapa Teste',
        'slug' => 'etapa-teste',
        'status' => 'published',
        'suggested_order' => 1,
    ]);

    $step = WorkflowPlanogramStep::query()->create([
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogram->id,
        'workflow_template_id' => $template->id,
        'name' => 'Etapa 1',
        'status' => 'published',
    ]);

    WorkflowGondolaExecution::query()->create([
        'tenant_id' => $tenant->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'active',
        'execution_started_by' => $user->id,
        'started_at' => now(),
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-maps.'.config('app.landlord_domain')])
        ->get(route('tenant.planograms.maps', ['subdomain' => 'tenant-planograms-maps'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Maps')
            ->has('store_maps', 1)
            ->where('store_maps.0.name', 'Loja Mapa')
            ->where('store_maps.0.can_edit_store', true)
            ->where('store_maps.0.regions.0.gondola.id', $gondola->id)
            ->where('store_maps.0.regions.0.gondola.execution_started', true)
            ->where('store_maps.0.regions.0.gondola.can_open_editor', true));
});

test('planogram store validates related records ownership by tenant', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenantForPlanograms('tenant-planograms-owner-a');
    $tenantB = makeTenantForPlanograms('tenant-planograms-owner-b');
    assignTenantAdminRoleForPlanograms($user, $tenantA->id);

    $storeFromTenantB = Store::query()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Loja Externa',
        'slug' => 'loja-externa',
        'status' => 'published',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-owner-a.'.config('app.landlord_domain')])
        ->post(route('tenant.planograms.store', ['subdomain' => 'tenant-planograms-owner-a'], false), [
            'name' => 'Planograma invalido',
            'type' => 'planograma',
            'store_id' => $storeFromTenantB->id,
            'status' => 'draft',
        ]);

    $response->assertSessionHasErrors(['store_id']);
});

test('tenant planogram routes are forbidden without permissions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    makeTenantForPlanograms('tenant-planograms-no-role');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tenant-planograms-no-role.'.config('app.landlord_domain')])
        ->get(route('tenant.planograms.index', ['subdomain' => 'tenant-planograms-no-role'], false));

    $response->assertForbidden();
});

function makeTenantForPlanograms(string $subdomain): Tenant
{
    $databaseAttributes = tenantDatabaseAttributesForPlanograms();

    $tenant = Tenant::query()->create([
        'name' => strtoupper($subdomain),
        'slug' => $subdomain,
        'database' => (string) ($databaseAttributes['database'] ?? 'database.sqlite'),
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => $subdomain.'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

function assignTenantAdminRoleForPlanograms(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}

/**
 * @return array<string, mixed>
 */
function tenantDatabaseAttributesForPlanograms(): array
{
    $defaultConnection = (string) config('database.default');

    return (array) config("database.connections.{$defaultConnection}");
}
