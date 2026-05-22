<?php

use App\Enums\DimensionStatus;
use App\Jobs\ResearchProductDimensionsJob;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate:fresh', [
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

test('index renderiza a página de aprovação de dimensões', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForDimApproval('tenant-dimapproval-index');
    assignTenantAdminRoleForDimApproval($user, $tenant->id);

    $host = 'tenant-dimapproval-index.'.config('app.landlord_domain');

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Aguardando',
        'slug' => 'produto-aguardando',
        'ean' => '7890000000001',
        'status' => 'published',
        'dimension_status' => DimensionStatus::AwaitingApproval->value,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.products.dimensions.index', ['subdomain' => 'tenant-dimapproval-index'], false));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/PendingDimensionsApproval')
            ->has('products')
            ->has('statuses')
            ->has('filters'),
        );
});

test('approve muda dimension_status para approved', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForDimApproval('tenant-dimapproval-approve');
    assignTenantAdminRoleForDimApproval($user, $tenant->id);

    $host = 'tenant-dimapproval-approve.'.config('app.landlord_domain');

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Pendente',
        'slug' => 'produto-pendente',
        'ean' => '7890000000002',
        'status' => 'published',
        'dimension_status' => DimensionStatus::AwaitingApproval->value,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.dimensions.approve', [
            'subdomain' => 'tenant-dimapproval-approve',
            'product' => $product->id,
        ], false));

    $response->assertRedirect();

    $product->refresh();

    expect($product->dimension_status)->toBe(DimensionStatus::Approved)
        ->and($product->dimension_approved_by)->toBe($user->id)
        ->and($product->dimension_approved_at)->not->toBeNull();
});

test('reject muda dimension_status para rejected e registra motivo', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForDimApproval('tenant-dimapproval-reject');
    assignTenantAdminRoleForDimApproval($user, $tenant->id);

    $host = 'tenant-dimapproval-reject.'.config('app.landlord_domain');

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Para Rejeitar',
        'slug' => 'produto-para-rejeitar',
        'ean' => '7890000000003',
        'status' => 'published',
        'dimension_status' => DimensionStatus::AwaitingApproval->value,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.dimensions.reject', [
            'subdomain' => 'tenant-dimapproval-reject',
            'product' => $product->id,
        ], false), [
            'reason' => 'Dimensões inconsistentes com o produto físico.',
        ]);

    $response->assertRedirect();

    $product->refresh();

    expect($product->dimension_status)->toBe(DimensionStatus::Rejected)
        ->and($product->dimension_warnings)->toContain('Dimensões inconsistentes com o produto físico.');
});

test('reject falha sem motivo', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForDimApproval('tenant-dimapproval-reject-validation');
    assignTenantAdminRoleForDimApproval($user, $tenant->id);

    $host = 'tenant-dimapproval-reject-validation.'.config('app.landlord_domain');

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Qualquer',
        'slug' => 'produto-qualquer',
        'ean' => '7890000000004',
        'status' => 'published',
        'dimension_status' => DimensionStatus::AwaitingApproval->value,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.dimensions.reject', [
            'subdomain' => 'tenant-dimapproval-reject-validation',
            'product' => $product->id,
        ], false), []);

    $response->assertSessionHasErrors('reason');
});

test('research enfileira o job de pesquisa e define status pending', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForDimApproval('tenant-dimapproval-research');
    assignTenantAdminRoleForDimApproval($user, $tenant->id);

    $host = 'tenant-dimapproval-research.'.config('app.landlord_domain');

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Para Repesquisar',
        'slug' => 'produto-para-repesquisar',
        'ean' => '7890000000005',
        'status' => 'published',
        'dimension_status' => DimensionStatus::Rejected->value,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.dimensions.research', [
            'subdomain' => 'tenant-dimapproval-research',
            'product' => $product->id,
        ], false));

    $response->assertRedirect();

    Queue::assertPushed(ResearchProductDimensionsJob::class, fn ($job) => $job->product->id === $product->id);

    $product->refresh();
    expect($product->dimension_status)->toBe(DimensionStatus::Pending);
});

test('approve-all aprova em lote apenas produtos com confidence high e awaiting_approval', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForDimApproval('tenant-dimapproval-batch');
    assignTenantAdminRoleForDimApproval($user, $tenant->id);

    $host = 'tenant-dimapproval-batch.'.config('app.landlord_domain');

    $highProduct = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Alta Confiança',
        'slug' => 'produto-alta-confianca',
        'ean' => '7890000000006',
        'status' => 'published',
        'dimension_status' => DimensionStatus::AwaitingApproval->value,
        'dimension_confidence' => 'high',
    ]);

    $lowProduct = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Baixa Confiança',
        'slug' => 'produto-baixa-confianca',
        'ean' => '7890000000007',
        'status' => 'published',
        'dimension_status' => DimensionStatus::AwaitingApproval->value,
        'dimension_confidence' => 'low',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.dimensions.approve-all', ['subdomain' => 'tenant-dimapproval-batch'], false), [
            'product_ids' => [$highProduct->id, $lowProduct->id],
        ]);

    $response->assertRedirect();

    expect($highProduct->fresh()->dimension_status)->toBe(DimensionStatus::Approved)
        ->and($lowProduct->fresh()->dimension_status)->toBe(DimensionStatus::AwaitingApproval);
});

if (! function_exists('makeTenantForDimApproval')) {
    function makeTenantForDimApproval(string $subdomain): Tenant
    {
        $databaseAttributes = (array) config('database.connections.'.config('database.default'));

        $tenant = Tenant::query()->create([
            'name' => strtoupper($subdomain),
            'slug' => $subdomain,
            'database' => (string) ($databaseAttributes['database'] ?? ':memory:'),
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
}

if (! function_exists('assignTenantAdminRoleForDimApproval')) {
    function assignTenantAdminRoleForDimApproval(User $user, string $tenantId): void
    {
        $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

        setPermissionsTeamId($tenantId);
        $user->assignRole($role);
    }
}
