<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    // Foco destes testes é o comportamento do mercadológico. A autorização é a
    // mesma linha (`authorize('update', $tenant)`) já coberta pelos testes de
    // WorkflowTemplate/Impersonation; com o tenant vinculado como corrente (para
    // preservar o :memory:), o RBAC entraria no rabbit hole de team scoping.
    config()->set('permission.rbac_enabled', false);
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

/**
 * Provisiona um tenant de teste com o schema migrado, deixando-o corrente para
 * que as categorias/produtos criados em seguida vivam no banco do tenant.
 */
function mercadologicoTenant(string $slug): Tenant
{
    $tenant = Tenant::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => $slug.'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    if (! Schema::connection('tenant')->hasTable('categories')) {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations',
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    // Bind direto como tenant corrente (sem makeCurrent) para não disparar o
    // SwitchTenantDatabaseTask, que purgaria a conexão :memory: e apagaria o
    // schema recém-migrado. Como o tenant já está corrente, o makeCurrent() que
    // o controller executa vira no-op (Spatie pula quando isCurrent()).
    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);

    return $tenant;
}

function mercCategory(Tenant $tenant, string $name, ?string $parentId = null): Category
{
    return Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
        'category_id' => $parentId,
        'status' => 'published',
    ]);
}

test('index renders the mercadologico tree with roots only', function (): void {
    // withoutVite: o componente Vue só entra no manifest após a Fase 2/build do
    // frontend; stubamos o Vite para o blade raiz renderizar sem consultá-lo.
    $this->withoutVite();
    $this->actingAs(User::factory()->create());
    $tenant = mercadologicoTenant('merc-index');

    $root = mercCategory($tenant, 'Mercearia');
    mercCategory($tenant, 'Biscoitos', $root->id);

    $this->get(route('landlord.tenants.mercadologico.index', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landlord/tenants/mercadologico/Index', false)
            ->where('tenant.id', $tenant->id)
            ->has('roots', 1)
            ->where('roots.0.name', 'Mercearia')
            ->where('roots.0.children_count', 1)
        );
});

test('children endpoint returns direct children of a node', function (): void {
    $this->actingAs(User::factory()->create());
    $tenant = mercadologicoTenant('merc-children');

    $root = mercCategory($tenant, 'Mercearia');
    mercCategory($tenant, 'Biscoitos', $root->id);
    mercCategory($tenant, 'Bolachas', $root->id);

    $this->getJson(route('landlord.tenants.mercadologico.children', ['tenant' => $tenant, 'parent_id' => $root->id]))
        ->assertOk()
        ->assertJsonCount(2, 'nodes');
});

test('move reparents a category within the tenant', function (): void {
    $this->actingAs(User::factory()->create());
    $tenant = mercadologicoTenant('merc-move');

    $mercearia = mercCategory($tenant, 'Mercearia');
    $biscoitos = mercCategory($tenant, 'Biscoitos', $mercearia->id);
    $bebidas = mercCategory($tenant, 'Bebidas');

    $this->post(
        route('landlord.tenants.mercadologico.move', ['tenant' => $tenant, 'category' => $biscoitos->id]),
        ['target_category_id' => $bebidas->id],
    )->assertRedirect();

    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);
    $biscoitos->refresh();
    expect($biscoitos->category_id)->toBe($bebidas->id);
    expect($biscoitos->full_path)->toBe('Bebidas > Biscoitos');
});

test('move into a descendant is rejected with a validation error', function (): void {
    $this->actingAs(User::factory()->create());
    $tenant = mercadologicoTenant('merc-cycle');

    $mercearia = mercCategory($tenant, 'Mercearia');
    $biscoitos = mercCategory($tenant, 'Biscoitos', $mercearia->id);

    $this->post(
        route('landlord.tenants.mercadologico.move', ['tenant' => $tenant, 'category' => $mercearia->id]),
        ['target_category_id' => $biscoitos->id],
    )->assertSessionHasErrors('target_category_id');

    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);
    expect($mercearia->refresh()->category_id)->toBeNull();
});

test('move-products reassigns products to another category', function (): void {
    $this->actingAs(User::factory()->create());
    $tenant = mercadologicoTenant('merc-products');

    $origem = mercCategory($tenant, 'Origem');
    $destino = mercCategory($tenant, 'Destino');

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto X',
        'slug' => 'produto-x-'.Str::lower(Str::random(5)),
        'ean' => '7890000000110',
        'status' => 'published',
        'category_id' => $origem->id,
    ]);

    $this->post(
        route('landlord.tenants.mercadologico.move-products', $tenant),
        ['product_ids' => [$product->id], 'target_category_id' => $destino->id],
    )->assertRedirect();

    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);
    expect($product->refresh()->category_id)->toBe($destino->id);
});
