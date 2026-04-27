<?php

use App\Enums\WorkflowExecutionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Policies\GondolaPolicy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('gondola policy only allows assigned started executions when kanban is active', function (): void {
    config()->set('permission.rbac_enabled', false);
    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $now = now();
    $tenant = new Tenant([
        'name' => 'Tenant Teste',
        'slug' => 'tenant-teste',
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status' => 'active',
    ]);
    $tenant->id = (string) Str::ulid();
    app()->instance('tenant', $tenant);
    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);
    app()->instance(TenantModuleService::class, new class extends TenantModuleService
    {
        public function tenantHasActiveModule(Tenant $tenant, string $slug): bool
        {
            unset($tenant);

            return $slug === ModuleSlug::KANBAN;
        }
    });

    $user = new User;
    $user->id = (string) Str::ulid();

    $otherUser = new User;
    $otherUser->id = (string) Str::ulid();

    $planogramId = (string) Str::ulid();
    $assignedGondolaId = (string) Str::ulid();
    $otherResponsibleGondolaId = (string) Str::ulid();
    $templateId = (string) Str::ulid();
    $stepId = (string) Str::ulid();

    DB::table('planograms')->insert([
        'id' => $planogramId,
        'tenant_id' => $tenant->id,
        'name' => 'Planograma Teste',
        'slug' => 'planograma-teste',
        'type' => 'planograma',
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('gondolas')->insert([
        [
            'id' => $assignedGondolaId,
            'tenant_id' => $tenant->id,
            'planogram_id' => $planogramId,
            'name' => 'Gôndola Atribuída',
            'slug' => 'gondola-atribuida',
            'num_modulos' => 1,
            'flow' => 'left_to_right',
            'alignment' => 'justify',
            'scale_factor' => 1,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $otherResponsibleGondolaId,
            'tenant_id' => $tenant->id,
            'planogram_id' => $planogramId,
            'name' => 'Gôndola Outro Responsável',
            'slug' => 'gondola-outro-responsavel',
            'num_modulos' => 1,
            'flow' => 'left_to_right',
            'alignment' => 'justify',
            'scale_factor' => 1,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('workflow_templates')->insert([
        'id' => $templateId,
        'tenant_id' => $tenant->id,
        'name' => 'Etapa Teste',
        'slug' => 'etapa-teste',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('workflow_planogram_steps')->insert([
        'id' => $stepId,
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogramId,
        'workflow_template_id' => $templateId,
        'name' => 'Etapa Teste',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('workflow_gondola_executions')->insert([
        [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'gondola_id' => $assignedGondolaId,
            'workflow_planogram_step_id' => $stepId,
            'status' => WorkflowExecutionStatus::Active->value,
            'current_responsible_id' => $user->id,
            'execution_started_by' => $user->id,
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'gondola_id' => $otherResponsibleGondolaId,
            'workflow_planogram_step_id' => $stepId,
            'status' => WorkflowExecutionStatus::Active->value,
            'current_responsible_id' => $otherUser->id,
            'execution_started_by' => $otherUser->id,
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $policy = new GondolaPolicy;

    expect($policy->view($user, Gondola::findOrFail($assignedGondolaId)))->toBeTrue()
        ->and($policy->view($user, Gondola::findOrFail($otherResponsibleGondolaId)))->toBeFalse();
});
