<?php

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Module;
use App\Models\Planogram;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

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

test('kanban index renderiza componente correto sem planograma selecionado', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-empty');
    $this->actingAs($context['user']);

    $this->get(route('tenant.kanban.index', ['subdomain' => $context['subdomain']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Kanban')
            ->has('planograms')
            ->has('stores')
            ->has('users')
            ->where('board', null)
            ->where('selected_planogram', null)
        );
});

test('kanban index carrega board quando planogram_id é passado', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-board');
    $this->actingAs($context['user']);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Teste',
        'slug' => 'planograma-teste-'.Str::lower(Str::random(6)),
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $this->get(route('tenant.kanban.index', [
        'subdomain' => $context['subdomain'],
        'planogram_id' => $planogram->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Kanban')
            ->where('selected_planogram.id', $planogram->id)
            ->has('board')
        );
});

test('kanban index filtra planogramas por store_id', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-store');
    $this->actingAs($context['user']);

    $store = Store::factory()->create(['tenant_id' => $context['tenant']->id]);

    $planogramNaLoja = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id' => $store->id,
    ]);

    Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'store_id' => null,
    ]);

    $this->get(route('tenant.kanban.index', [
        'subdomain' => $context['subdomain'],
        'store_id' => $store->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('planograms', fn ($planograms) => count($planograms) === 1 && $planograms[0]['id'] === $planogramNaLoja->id
            )
        );
});

test('rota planograms.kanban redireciona para kanban.index', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-redirect');
    $this->actingAs($context['user']);

    $this->get(route('tenant.planograms.kanban', ['subdomain' => $context['subdomain']]))
        ->assertRedirect(route('tenant.kanban.index', ['subdomain' => $context['subdomain']]));
});

function setupKanbanTenantCtx(string $subdomain): array
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

    $kanban = Module::query()->firstOrCreate([
        'slug' => ModuleSlug::KANBAN,
    ], [
        'name' => 'Kanban',
        'is_active' => true,
    ]);

    $tenant->modules()->attach($kanban->id);

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
