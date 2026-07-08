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
    // O foco é o comportamento do mercadológico no tenant. A autorização passa
    // pela CategoryPolicy (mesma linha já coberta pelos testes de categorias);
    // com o tenant vinculado como corrente (para preservar o :memory:), o RBAC
    // entraria no rabbit hole de team scoping.
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
 * Provisiona um tenant de teste com o schema migrado e o deixa vinculado como
 * corrente (sem makeCurrent, que purgaria o :memory:). Devolve [tenant, host].
 *
 * @return array{0: Tenant, 1: string}
 */
function tenantMercadologico(string $slug): array
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

    // Vincula direto como corrente para não disparar o SwitchTenantDatabaseTask
    // (que purgaria a conexão :memory:). Como já está corrente, o makeCurrent()
    // do NeedsTenant vira no-op (Spatie pula quando isCurrent()).
    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);

    return [$tenant, $slug.'.'.config('app.landlord_domain')];
}

/**
 * Re-vincula o tenant como corrente após uma request (que reseta o contexto),
 * permitindo assertions diretas via Eloquent no banco do tenant.
 */
function rebindTenant(Tenant $tenant): void
{
    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);
}

function tenantMercCategory(Tenant $tenant, string $name, ?string $parentId = null): Category
{
    return Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
        'category_id' => $parentId,
        'status' => 'published',
    ]);
}

test('index renderiza a árvore do mercadológico apenas com as raízes', function (): void {
    $this->withoutVite();
    $this->actingAs(User::factory()->create());
    [$tenant, $host] = tenantMercadologico('merc-t-index');

    $root = tenantMercCategory($tenant, 'Mercearia');
    tenantMercCategory($tenant, 'Biscoitos', $root->id);

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.mercadologico.index', ['subdomain' => 'merc-t-index'], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/mercadologico/Index', false)
            ->has('roots', 1)
            ->where('roots.0.name', 'Mercearia')
            ->where('roots.0.children_count', 1)
        );
});

test('endpoint de filhos devolve as subcategorias diretas', function (): void {
    $this->actingAs(User::factory()->create());
    [$tenant, $host] = tenantMercadologico('merc-t-children');

    $root = tenantMercCategory($tenant, 'Bebidas');
    tenantMercCategory($tenant, 'Refrigerantes', $root->id);
    tenantMercCategory($tenant, 'Sucos', $root->id);

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->getJson(route('tenant.mercadologico.children', ['subdomain' => 'merc-t-children', 'parent_id' => $root->id], false))
        ->assertOk()
        ->assertJsonCount(2, 'nodes');
});

test('store cria uma categoria e devolve o nó', function (): void {
    $this->actingAs(User::factory()->create());
    [$tenant, $host] = tenantMercadologico('merc-t-store');

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->postJson(route('tenant.mercadologico.categories.store', ['subdomain' => 'merc-t-store'], false), [
            'name' => 'Hortifruti',
            'codigo' => 55,
            'status' => 'published',
        ])
        ->assertOk()
        ->assertJsonPath('category.name', 'Hortifruti')
        ->assertJsonPath('category.codigo', 55)
        ->assertJsonPath('category.children_count', 0);

    rebindTenant($tenant);
    expect(Category::query()->where('name', 'Hortifruti')->exists())->toBeTrue();
});

test('move reparenta uma categoria dentro do tenant', function (): void {
    $this->actingAs(User::factory()->create());
    [$tenant, $host] = tenantMercadologico('merc-t-move');

    $mercearia = tenantMercCategory($tenant, 'Mercearia');
    $biscoitos = tenantMercCategory($tenant, 'Biscoitos', $mercearia->id);
    $bebidas = tenantMercCategory($tenant, 'Bebidas');

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.mercadologico.move', ['subdomain' => 'merc-t-move', 'category' => $biscoitos->id], false), [
            'target_category_id' => $bebidas->id,
        ])
        ->assertRedirect();

    rebindTenant($tenant);
    expect($biscoitos->refresh()->category_id)->toBe($bebidas->id);
    expect($biscoitos->full_path)->toBe('Bebidas > Biscoitos');
});

test('destroy é bloqueado (422) quando a categoria possui subcategorias', function (): void {
    $this->actingAs(User::factory()->create());
    [$tenant, $host] = tenantMercadologico('merc-t-del-block');

    $parent = tenantMercCategory($tenant, 'Limpeza');
    tenantMercCategory($tenant, 'Sabão', $parent->id);

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->deleteJson(route('tenant.mercadologico.categories.destroy', ['subdomain' => 'merc-t-del-block', 'category' => $parent->id], false))
        ->assertStatus(422);

    rebindTenant($tenant);
    expect(Category::query()->whereKey($parent->id)->first())->not->toBeNull();
});

test('destroy soft-deleta uma folha vazia e restore a traz de volta', function (): void {
    $this->actingAs(User::factory()->create());
    [$tenant, $host] = tenantMercadologico('merc-t-restore');

    $leaf = tenantMercCategory($tenant, 'Folha');

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->deleteJson(route('tenant.mercadologico.categories.destroy', ['subdomain' => 'merc-t-restore', 'category' => $leaf->id], false))
        ->assertOk();

    rebindTenant($tenant);
    expect(Category::query()->whereKey($leaf->id)->first())->toBeNull();

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->postJson(route('tenant.mercadologico.categories.restore', ['subdomain' => 'merc-t-restore', 'category' => $leaf->id], false))
        ->assertOk()
        ->assertJsonPath('category.id', $leaf->id);

    rebindTenant($tenant);
    expect(Category::query()->whereKey($leaf->id)->first())->not->toBeNull();
});

test('move-products reatribui produtos para outra categoria', function (): void {
    $this->actingAs(User::factory()->create());
    [$tenant, $host] = tenantMercadologico('merc-t-products');

    $origem = tenantMercCategory($tenant, 'Origem');
    $destino = tenantMercCategory($tenant, 'Destino');

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto X',
        'slug' => 'produto-x-'.Str::lower(Str::random(5)),
        'ean' => '7890000000110',
        'status' => 'published',
        'category_id' => $origem->id,
    ]);

    $this->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.mercadologico.move-products', ['subdomain' => 'merc-t-products'], false), [
            'product_ids' => [$product->id],
            'target_category_id' => $destino->id,
        ])
        ->assertRedirect();

    rebindTenant($tenant);
    expect($product->refresh()->category_id)->toBe($destino->id);
});
