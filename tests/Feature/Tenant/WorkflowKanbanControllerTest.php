<?php

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Gondola;
use App\Models\Module;
use App\Models\Planogram;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowPlanogramStep;
use App\Models\WorkflowTemplate;
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
            ->where('board', [])
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

test('kanban index filtra execucoes por current_responsible_id', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-responsible');
    $this->actingAs($context['user']);

    $responsavelSelecionado = User::factory()->create();
    $outroResponsavel = User::factory()->create();

    $planogram = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
    ]);

    $gondolaA = Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
    ]);

    $gondolaB = Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
    ]);

    $template = WorkflowTemplate::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Etapa Teste',
        'slug' => 'etapa-teste-'.Str::lower(Str::random(8)),
        'suggested_order' => 1,
        'status' => 'published',
    ]);

    $step = WorkflowPlanogramStep::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'workflow_template_id' => $template->id,
        'status' => 'published',
    ]);

    $execucaoFiltrada = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondolaA->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'active',
        'current_responsible_id' => $responsavelSelecionado->id,
    ]);

    WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondolaB->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'active',
        'current_responsible_id' => $outroResponsavel->id,
    ]);

    $this->get(route('tenant.kanban.index', [
        'subdomain' => $context['subdomain'],
        'planogram_id' => $planogram->id,
        'current_responsible_id' => $responsavelSelecionado->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.current_responsible_id', $responsavelSelecionado->id)
            ->where('board', function ($board) use ($execucaoFiltrada): bool {
                $executionIds = collect($board)
                    ->flatMap(fn (array $column) => $column['executions'] ?? [])
                    ->pluck('id')
                    ->values()
                    ->all();

                return $executionIds === [$execucaoFiltrada->id];
            })
        );
});

test('board expõe can_open_editor conforme access_mode da etapa', function (): void {
    $context = setupKanbanTenantCtx('kanban-access-mode');
    $this->actingAs($context['user']);

    $planogram = Planogram::factory()->create(['tenant_id' => $context['tenant']->id]);

    $gondolaEdit = Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
    ]);
    $gondolaView = Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
    ]);

    $templateEdit = WorkflowTemplate::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Criação',
        'slug' => 'criacao-'.Str::lower(Str::random(8)),
        'suggested_order' => 1,
        'access_mode' => 'edit',
        'status' => 'published',
    ]);
    $templateView = WorkflowTemplate::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Aprovação comercial',
        'slug' => 'aprovacao-'.Str::lower(Str::random(8)),
        'suggested_order' => 4,
        'access_mode' => 'view',
        'status' => 'published',
    ]);

    $stepEdit = WorkflowPlanogramStep::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'workflow_template_id' => $templateEdit->id,
        'status' => 'published',
    ]);
    $stepView = WorkflowPlanogramStep::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'workflow_template_id' => $templateView->id,
        'status' => 'published',
    ]);

    $execEdit = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondolaEdit->id,
        'workflow_planogram_step_id' => $stepEdit->id,
        'status' => 'active',
        'execution_started_by' => $context['user']->id,
        'current_responsible_id' => $context['user']->id,
        'started_at' => now(),
    ]);
    $execView = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondolaView->id,
        'workflow_planogram_step_id' => $stepView->id,
        'status' => 'active',
        'execution_started_by' => $context['user']->id,
        'current_responsible_id' => $context['user']->id,
        'started_at' => now(),
    ]);

    $this->get(route('tenant.kanban.index', [
        'subdomain' => $context['subdomain'],
        'planogram_id' => $planogram->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('board', function ($board) use ($execEdit, $execView): bool {
                $byId = collect($board)
                    ->flatMap(fn ($column) => $column['executions'] ?? [])
                    ->keyBy('id');

                return $byId[$execEdit->id]['can_open_editor'] === true
                    && $byId[$execView->id]['can_open_editor'] === false;
            })
        );
});

test('editor de etapa somente leitura redireciona para o PDF', function (): void {
    $context = setupKanbanTenantCtx('kanban-editor-block');
    $this->actingAs($context['user']);

    $planogram = Planogram::factory()->create(['tenant_id' => $context['tenant']->id]);
    $gondola = Gondola::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
    ]);

    $template = WorkflowTemplate::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Aprovação comercial',
        'slug' => 'aprovacao-'.Str::lower(Str::random(8)),
        'suggested_order' => 4,
        'access_mode' => 'view',
        'status' => 'published',
    ]);
    $step = WorkflowPlanogramStep::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'workflow_template_id' => $template->id,
        'status' => 'published',
    ]);
    WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'active',
        'execution_started_by' => $context['user']->id,
        'current_responsible_id' => $context['user']->id,
        'started_at' => now(),
    ]);

    $this->get(route('tenant.planograms.gondolas.editor', [
        'subdomain' => $context['subdomain'],
        'record' => $gondola->id,
    ]))
        ->assertRedirect(route('export.gondola.view', ['gondola' => $gondola->id]));
});

test('rota planograms.kanban redireciona para kanban.index', function (): void {
    $context = setupKanbanTenantCtx('kanban-ctrl-redirect');
    $this->actingAs($context['user']);

    // O controller redireciona via to_route sem repassar o subdomain (resolvido
    // por host), então o destino esperado é a rota sem o parâmetro de query.
    $this->get(route('tenant.planograms.kanban', ['subdomain' => $context['subdomain']]))
        ->assertRedirect(route('tenant.kanban.index'));
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
    migrateTenantSchema();

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

function migrateTenantSchema(): void
{
    // migrate (não :fresh) é idempotente via tabela migrations: reaplica só o
    // que falta, evitando "table already exists" quando a conexão tenant
    // (:memory:) persiste entre os testes do mesmo processo.
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);
}
