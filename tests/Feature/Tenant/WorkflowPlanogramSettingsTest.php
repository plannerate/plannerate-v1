<?php

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Gondola;
use App\Models\Module;
use App\Models\Planogram;
use App\Models\Role;
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

test('workflow settings sync creates missing steps and copies template suggested users', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-sync');
    $this->actingAs($context['user']);

    $templateUser = User::factory()->create();

    $template = WorkflowTemplate::query()->create([
        'name' => 'Revisão de imagens',
        'slug' => 'revisao-imagens-'.Str::lower(Str::random(8)),
        'suggested_order' => 1,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $template->suggestedUsers()->sync([$templateUser->id]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Sync',
        'slug' => 'planograma-sync',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $response = $this->get(route('tenant.planograms.workflow-settings.index', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('steps.0.workflow_template_id', $template->id)
        ->assertJsonPath('steps.0.is_required', true)
        ->assertJsonPath('steps.0.selected_user_ids.0', $templateUser->id);

    $step = WorkflowPlanogramStep::query()
        ->where('planogram_id', $planogram->id)
        ->where('workflow_template_id', $template->id)
        ->firstOrFail();

    $this->assertDatabaseHas('workflow_planogram_step_users', [
        'workflow_planogram_step_id' => $step->id,
        'user_id' => $templateUser->id,
    ]);
});

test('workflow settings update persists required skipped and allowed users', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-update');
    $this->actingAs($context['user']);

    $allowedA = User::factory()->create();
    $allowedB = User::factory()->create();

    $template = WorkflowTemplate::query()->create([
        'name' => 'Aprovação comercial',
        'slug' => 'aprovacao-comercial-'.Str::lower(Str::random(8)),
        'suggested_order' => 2,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Update',
        'slug' => 'planograma-update',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $this->get(route('tenant.planograms.workflow-settings.index', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]))
        ->assertOk();

    $step = WorkflowPlanogramStep::query()->where('planogram_id', $planogram->id)->firstOrFail();

    $response = $this->putJson(route('tenant.planograms.workflow-settings.update', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]), [
        'steps' => [
            [
                'step_id' => $step->id,
                'is_required' => false,
                'is_skipped' => true,
                'estimated_duration_days' => 9,
                'user_ids' => [$allowedA->id, $allowedB->id],
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('steps.0.id', $step->id)
        ->assertJsonPath('steps.0.is_required', false)
        ->assertJsonPath('steps.0.is_skipped', true);

    $this->assertDatabaseHas('workflow_planogram_steps', [
        'id' => $step->id,
        'estimated_duration_days' => 9,
        'is_required' => 0,
        'is_skipped' => 1,
    ]);

    $this->assertDatabaseHas('workflow_planogram_step_users', [
        'workflow_planogram_step_id' => $step->id,
        'user_id' => $allowedA->id,
    ]);

    $this->assertDatabaseHas('workflow_planogram_step_users', [
        'workflow_planogram_step_id' => $step->id,
        'user_id' => $allowedB->id,
    ]);
});

test('workflow settings load defaults resets settings based on tenant templates', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-load-defaults');
    $this->actingAs($context['user']);

    $templateSuggestedUser = User::factory()->create();
    $oldUser = User::factory()->create();
    $defaultRoleId = (string) Str::ulid();

    $template = WorkflowTemplate::query()->create([
        'name' => 'Validação de layout',
        'slug' => 'validacao-layout-'.Str::lower(Str::random(8)),
        'description' => 'Validar layout final da exposição',
        'suggested_order' => 1,
        'estimated_duration_days' => 3,
        'default_role_id' => $defaultRoleId,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $template->suggestedUsers()->sync([$templateSuggestedUser->id]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Defaults',
        'slug' => 'planograma-defaults',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $step = WorkflowPlanogramStep::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'workflow_template_id' => $template->id,
        'is_required' => false,
        'is_skipped' => true,
        'status' => 'draft',
    ]);

    $step->availableUsers()->sync([$oldUser->id]);

    $response = $this->postJson(route('tenant.planograms.workflow-settings.load-defaults', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('steps.0.id', $step->id)
        ->assertJsonPath('steps.0.is_required', true)
        ->assertJsonPath('steps.0.is_skipped', false)
        ->assertJsonPath('steps.0.selected_user_ids.0', $templateSuggestedUser->id);

    $this->assertDatabaseHas('workflow_planogram_steps', [
        'id' => $step->id,
        'name' => 'Validação de layout',
        'description' => 'Validar layout final da exposição',
        'estimated_duration_days' => 3,
        'role_id' => $defaultRoleId,
        'is_required' => 1,
        'is_skipped' => 0,
    ]);

    $this->assertDatabaseHas('workflow_planogram_step_users', [
        'workflow_planogram_step_id' => $step->id,
        'user_id' => $templateSuggestedUser->id,
    ]);

    $this->assertDatabaseMissing('workflow_planogram_step_users', [
        'workflow_planogram_step_id' => $step->id,
        'user_id' => $oldUser->id,
    ]);
});

test('kanban board hides skipped steps for a planogram', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-board');
    $this->actingAs($context['user']);

    $template = WorkflowTemplate::query()->create([
        'name' => 'Execução loja',
        'slug' => 'execucao-loja-'.Str::lower(Str::random(8)),
        'suggested_order' => 3,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Board',
        'slug' => 'planograma-board',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $this->get(route('tenant.planograms.workflow-settings.index', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]))
        ->assertOk();

    $step = WorkflowPlanogramStep::query()->where('planogram_id', $planogram->id)->firstOrFail();
    $step->update(['is_skipped' => true]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'name' => 'Gondola A',
        'slug' => 'gondola-a',
        'status' => 'draft',
    ]);

    WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'active',
        'current_responsible_id' => $context['user']->id,
        'execution_started_by' => $context['user']->id,
        'started_at' => now(),
    ]);

    $response = $this->get(route('tenant.kanban.index', [
        'subdomain' => $context['subdomain'],
        'planogram_id' => $planogram->id,
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Kanban')
            ->has('board', 0));
});

test('kanban board includes execution display fields for cards', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-card-fields');
    $this->actingAs($context['user']);

    WorkflowTemplate::query()->create([
        'name' => 'Execução em loja',
        'slug' => 'execucao-em-loja-'.Str::lower(Str::random(8)),
        'suggested_order' => 1,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Cards',
        'slug' => 'planograma-cards',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $this->get(route('tenant.planograms.workflow-settings.index', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]))
        ->assertOk();

    $step = WorkflowPlanogramStep::query()->where('planogram_id', $planogram->id)->firstOrFail();

    $gondola = Gondola::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'name' => 'Gondola Cards',
        'slug' => 'gondola-cards',
        'location' => 'Corredor 3',
        'status' => 'draft',
    ]);

    WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'active',
        'current_responsible_id' => $context['user']->id,
        'execution_started_by' => $context['user']->id,
        'started_at' => now(),
    ]);

    $this->get(route('tenant.kanban.index', [
        'subdomain' => $context['subdomain'],
        'planogram_id' => $planogram->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planograms/Kanban')
            ->has('board', 1)
            ->where('board.0.executions', [])
            ->where('board.0.executions_count', 1)
            ->where('board.0.step_ids.0', $step->id)
            ->where('board.0.step.is_skipped', false)
        );

    $query = http_build_query([
        'step_ids' => [$step->id],
        'page' => 1,
    ]);

    $this->getJson(route('tenant.kanban.column-executions', [
        'subdomain' => $context['subdomain'],
    ]).'?'.$query)
        ->assertOk()
        ->assertJsonPath('data.0.gondola_name', 'Gondola Cards')
        ->assertJsonPath('data.0.gondola_location', 'Corredor 3')
        ->assertJsonPath('data.0.planogram_name', $planogram->name)
        ->assertJsonPath('data.0.step_name', $step->name)
        ->assertJsonPath('data.0.started_by.id', $context['user']->id)
        ->assertJsonPath('data.0.can_pause', true)
        ->assertJsonPath('data.0.can_move', true);
});

test('execution details returns allowed users and assign only accepts users allowed by step', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-assign');
    $this->actingAs($context['user']);

    $allowedUser = User::factory()->create();
    $blockedUser = User::factory()->create();

    $template = WorkflowTemplate::query()->create([
        'name' => 'Aprovação GC',
        'slug' => 'aprovacao-gc-'.Str::lower(Str::random(8)),
        'suggested_order' => 4,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Assign',
        'slug' => 'planograma-assign',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $this->get(route('tenant.planograms.workflow-settings.index', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]))
        ->assertOk();

    $step = WorkflowPlanogramStep::query()->where('planogram_id', $planogram->id)->firstOrFail();
    $step->availableUsers()->sync([$allowedUser->id]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'name' => 'Gondola Assign',
        'slug' => 'gondola-assign',
        'status' => 'draft',
    ]);

    $execution = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'pending',
    ]);

    $detailsResponse = $this->get(route('tenant.kanban.executions.details', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]));

    $detailsResponse
        ->assertOk()
        ->assertJsonPath('execution.id', $execution->id)
        ->assertJsonPath('allowed_users.0.id', $allowedUser->id);

    $blockedAssignResponse = $this->patchJson(route('tenant.kanban.executions.assign', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]), [
        'user_id' => $blockedUser->id,
    ]);

    $blockedAssignResponse->assertStatus(422);

    $allowedAssignResponse = $this->patchJson(route('tenant.kanban.executions.assign', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]), [
        'user_id' => $allowedUser->id,
    ]);

    $allowedAssignResponse->assertOk();

    $this->assertDatabaseHas('workflow_gondola_executions', [
        'id' => $execution->id,
        'current_responsible_id' => $allowedUser->id,
    ]);
});

test('logged user can start and abandon a pending execution with notes', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-start-abandon');
    $executor = User::factory()->create();
    $executor->assignRole(Role::query()->where('system_name', 'tenant-admin')->firstOrFail());
    $this->actingAs($executor);

    $template = WorkflowTemplate::query()->create([
        'name' => 'Execução loja',
        'slug' => 'execucao-loja-'.Str::lower(Str::random(8)),
        'suggested_order' => 5,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Start',
        'slug' => 'planograma-start',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $this->get(route('tenant.planograms.workflow-settings.index', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]))
        ->assertOk();

    $step = WorkflowPlanogramStep::query()
        ->where('planogram_id', $planogram->id)
        ->where('workflow_template_id', $template->id)
        ->firstOrFail();
    $step->availableUsers()->sync([$executor->id]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'name' => 'Gondola Start',
        'slug' => 'gondola-start',
        'status' => 'draft',
    ]);

    $execution = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'pending',
    ]);

    $startResponse = $this->patchJson(route('tenant.kanban.executions.start', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]), [
        'notes' => 'Começando conferência em loja.',
    ]);

    $startResponse
        ->assertOk()
        ->assertJsonPath('execution.status', 'active');

    $this->assertDatabaseHas('workflow_gondola_executions', [
        'id' => $execution->id,
        'status' => 'active',
        'current_responsible_id' => $executor->id,
        'execution_started_by' => $executor->id,
    ]);

    $detailsResponse = $this->get(route('tenant.kanban.executions.details', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]));

    $detailsResponse
        ->assertOk()
        ->assertJsonPath('execution.started_by.id', $executor->id)
        ->assertJsonPath('execution.assigned_to_user.id', $executor->id);

    $abandonResponse = $this->patchJson(route('tenant.kanban.executions.abandon', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]), [
        'notes' => 'Gondola indisponível para execução.',
    ]);

    $abandonResponse
        ->assertOk()
        ->assertJsonPath('execution.status', 'cancelled');

    $this->assertDatabaseHas('workflow_gondola_executions', [
        'id' => $execution->id,
        'status' => 'cancelled',
    ]);

    $this->assertDatabaseHas('workflow_histories', [
        'workflow_gondola_execution_id' => $execution->id,
        'action' => 'cancelled',
        'description' => 'Gondola indisponível para execução.',
    ]);
});

test('execution policy guards allowed users statuses and last step completion', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-policy-rules');
    $executor = User::factory()->create();
    $blockedUser = User::factory()->create();
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();
    $executor->assignRole($role);
    $blockedUser->assignRole($role);

    $firstTemplate = WorkflowTemplate::query()->create([
        'name' => 'Revisão inicial',
        'slug' => 'revisao-inicial-'.Str::lower(Str::random(8)),
        'suggested_order' => 1,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $lastTemplate = WorkflowTemplate::query()->create([
        'name' => 'Execução final',
        'slug' => 'execucao-final-'.Str::lower(Str::random(8)),
        'suggested_order' => 2,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Policy',
        'slug' => 'planograma-policy',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $this->actingAs($executor)
        ->get(route('tenant.planograms.workflow-settings.index', [
            'subdomain' => $context['subdomain'],
            'planogram' => $planogram->id,
        ]))
        ->assertOk();

    $firstStep = WorkflowPlanogramStep::query()
        ->where('planogram_id', $planogram->id)
        ->where('workflow_template_id', $firstTemplate->id)
        ->firstOrFail();
    $lastStep = WorkflowPlanogramStep::query()
        ->where('planogram_id', $planogram->id)
        ->where('workflow_template_id', $lastTemplate->id)
        ->firstOrFail();
    $firstStep->availableUsers()->sync([$executor->id]);
    $lastStep->availableUsers()->sync([$executor->id]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'name' => 'Gondola Policy',
        'slug' => 'gondola-policy',
        'status' => 'draft',
    ]);

    $pendingExecution = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $firstStep->id,
        'status' => 'pending',
    ]);

    $this->actingAs($blockedUser)
        ->patchJson(route('tenant.kanban.executions.start', [
            'subdomain' => $context['subdomain'],
            'execution' => $pendingExecution->id,
        ]))
        ->assertForbidden();

    $this->actingAs($executor)
        ->patchJson(route('tenant.kanban.executions.start', [
            'subdomain' => $context['subdomain'],
            'execution' => $pendingExecution->id,
        ]), [
            'notes' => 'Iniciando com usuário permitido.',
        ])
        ->assertOk();

    $this->assertDatabaseHas('workflow_histories', [
        'workflow_gondola_execution_id' => $pendingExecution->id,
        'action' => 'started',
        'description' => 'Iniciando com usuário permitido.',
    ]);

    $pendingForBlockedActions = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $firstStep->id,
        'status' => 'pending',
    ]);

    $this->patchJson(route('tenant.kanban.executions.pause', [
        'subdomain' => $context['subdomain'],
        'execution' => $pendingForBlockedActions->id,
    ]))->assertForbidden();

    $this->patchJson(route('tenant.kanban.executions.abandon', [
        'subdomain' => $context['subdomain'],
        'execution' => $pendingForBlockedActions->id,
    ]))->assertForbidden();

    $activeFirstStep = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $firstStep->id,
        'status' => 'active',
        'current_responsible_id' => $executor->id,
        'execution_started_by' => $executor->id,
        'started_at' => now(),
    ]);

    $this->patchJson(route('tenant.kanban.executions.complete', [
        'subdomain' => $context['subdomain'],
        'execution' => $activeFirstStep->id,
    ]))->assertForbidden();

    $this->patchJson(route('tenant.kanban.executions.pause', [
        'subdomain' => $context['subdomain'],
        'execution' => $activeFirstStep->id,
    ]), [
        'notes' => 'Pausa operacional.',
    ])->assertOk();

    $this->assertDatabaseHas('workflow_histories', [
        'workflow_gondola_execution_id' => $activeFirstStep->id,
        'action' => 'paused',
        'description' => 'Pausa operacional.',
    ]);

    $activeToAbandon = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $firstStep->id,
        'status' => 'active',
        'current_responsible_id' => $executor->id,
        'execution_started_by' => $executor->id,
        'started_at' => now(),
    ]);

    $this->patchJson(route('tenant.kanban.executions.abandon', [
        'subdomain' => $context['subdomain'],
        'execution' => $activeToAbandon->id,
    ]), [
        'notes' => 'Abandono autorizado.',
    ])->assertOk();

    $this->assertDatabaseHas('workflow_histories', [
        'workflow_gondola_execution_id' => $activeToAbandon->id,
        'action' => 'cancelled',
        'description' => 'Abandono autorizado.',
    ]);

    $activeLastStep = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $lastStep->id,
        'status' => 'active',
        'current_responsible_id' => $executor->id,
        'execution_started_by' => $executor->id,
        'started_at' => now(),
    ]);

    $this->patchJson(route('tenant.kanban.executions.complete', [
        'subdomain' => $context['subdomain'],
        'execution' => $activeLastStep->id,
    ]), [
        'notes' => 'Conclusão na última etapa.',
    ])->assertOk();

    $this->assertDatabaseHas('workflow_histories', [
        'workflow_gondola_execution_id' => $activeLastStep->id,
        'action' => 'completed',
        'description' => 'Conclusão na última etapa.',
    ]);
});

test('execution move requires active status and blocks skipped target steps', function (): void {
    $context = setupWorkflowTenantContext('tenant-workflow-move-rules');
    $this->actingAs($context['user']);

    $firstTemplate = WorkflowTemplate::query()->create([
        'name' => 'Separação',
        'slug' => 'separacao-'.Str::lower(Str::random(8)),
        'suggested_order' => 1,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $skippedTemplate = WorkflowTemplate::query()->create([
        'name' => 'Etapa ignorada',
        'slug' => 'etapa-ignorada-'.Str::lower(Str::random(8)),
        'suggested_order' => 2,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $nextTemplate = WorkflowTemplate::query()->create([
        'name' => 'Execução',
        'slug' => 'execucao-'.Str::lower(Str::random(8)),
        'suggested_order' => 3,
        'is_required_by_default' => true,
        'status' => 'published',
    ]);

    $planogram = Planogram::query()->create([
        'tenant_id' => $context['tenant']->id,
        'name' => 'Planograma Move',
        'slug' => 'planograma-move',
        'type' => 'planograma',
        'status' => 'draft',
    ]);

    $this->get(route('tenant.planograms.workflow-settings.index', [
        'subdomain' => $context['subdomain'],
        'planogram' => $planogram->id,
    ]))->assertOk();

    $firstStep = WorkflowPlanogramStep::query()
        ->where('planogram_id', $planogram->id)
        ->where('workflow_template_id', $firstTemplate->id)
        ->firstOrFail();
    $skippedStep = WorkflowPlanogramStep::query()
        ->where('planogram_id', $planogram->id)
        ->where('workflow_template_id', $skippedTemplate->id)
        ->firstOrFail();
    $nextStep = WorkflowPlanogramStep::query()
        ->where('planogram_id', $planogram->id)
        ->where('workflow_template_id', $nextTemplate->id)
        ->firstOrFail();

    $skippedStep->update(['is_skipped' => true]);

    $gondola = Gondola::query()->create([
        'tenant_id' => $context['tenant']->id,
        'planogram_id' => $planogram->id,
        'name' => 'Gondola Move',
        'slug' => 'gondola-move',
        'status' => 'draft',
    ]);

    $pendingExecution = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $firstStep->id,
        'status' => 'pending',
    ]);

    $this->patchJson(route('tenant.kanban.executions.move', [
        'subdomain' => $context['subdomain'],
        'execution' => $pendingExecution->id,
    ]), [
        'target_step_id' => $nextStep->id,
    ])->assertForbidden();

    $activeExecution = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $context['tenant']->id,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $firstStep->id,
        'status' => 'active',
        'current_responsible_id' => $context['user']->id,
        'execution_started_by' => $context['user']->id,
        'started_at' => now(),
    ]);

    $this->patchJson(route('tenant.kanban.executions.move', [
        'subdomain' => $context['subdomain'],
        'execution' => $activeExecution->id,
    ]), [
        'target_step_id' => $skippedStep->id,
    ])->assertUnprocessable();

    $this->patchJson(route('tenant.kanban.executions.move', [
        'subdomain' => $context['subdomain'],
        'execution' => $activeExecution->id,
    ]), [
        'target_step_id' => $nextStep->id,
        'notes' => 'Movido para a próxima etapa disponível.',
    ])->assertOk();

    $this->assertDatabaseHas('workflow_gondola_executions', [
        'id' => $activeExecution->id,
        'workflow_planogram_step_id' => $nextStep->id,
    ]);

    $this->assertDatabaseHas('workflow_histories', [
        'workflow_gondola_execution_id' => $activeExecution->id,
        'action' => 'moved',
        'from_step_id' => $firstStep->id,
        'to_step_id' => $nextStep->id,
        'description' => 'Movido para a próxima etapa disponível.',
    ]);
});

/**
 * @return array{subdomain: string, host: string, tenant: Tenant, user: User}
 */
function setupWorkflowTenantContext(string $subdomain): array
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
