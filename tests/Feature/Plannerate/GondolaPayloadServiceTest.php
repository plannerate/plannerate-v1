<?php

use App\Enums\WorkflowExecutionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\GondolaPayloadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('editor payload filters navigation gondolas to started executions when kanban module is active', function (): void {
    $now = now();
    $tenant = new Tenant([
        'name' => 'Tenant Teste',
        'slug' => 'tenant-teste',
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status' => 'active',
    ]);
    $tenant->id = (string) Str::ulid();
    $user = new User;
    $user->id = (string) Str::ulid();
    Auth::guard()->setUser($user);

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

    $planogramId = (string) Str::ulid();
    $startedGondolaId = (string) Str::ulid();
    $anotherStartedGondolaId = (string) Str::ulid();
    $pendingGondolaId = (string) Str::ulid();
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
            'id' => $startedGondolaId,
            'tenant_id' => $tenant->id,
            'planogram_id' => $planogramId,
            'name' => 'Gôndola Iniciada',
            'slug' => 'gondola-iniciada',
            'num_modulos' => 1,
            'flow' => 'left_to_right',
            'alignment' => 'justify',
            'scale_factor' => 1,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $anotherStartedGondolaId,
            'tenant_id' => $tenant->id,
            'planogram_id' => $planogramId,
            'name' => 'Gôndola Também Iniciada',
            'slug' => 'gondola-tambem-iniciada',
            'num_modulos' => 1,
            'flow' => 'left_to_right',
            'alignment' => 'justify',
            'scale_factor' => 1,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $pendingGondolaId,
            'tenant_id' => $tenant->id,
            'planogram_id' => $planogramId,
            'name' => 'Gôndola Pendente',
            'slug' => 'gondola-pendente',
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
            'gondola_id' => $startedGondolaId,
            'workflow_planogram_step_id' => $stepId,
            'status' => WorkflowExecutionStatus::Active->value,
            'current_responsible_id' => $user->id,
            'execution_started_by' => (string) Str::ulid(),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'gondola_id' => $anotherStartedGondolaId,
            'workflow_planogram_step_id' => $stepId,
            'status' => WorkflowExecutionStatus::Pending->value,
            'current_responsible_id' => (string) Str::ulid(),
            'execution_started_by' => (string) Str::ulid(),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'gondola_id' => $pendingGondolaId,
            'workflow_planogram_step_id' => $stepId,
            'status' => WorkflowExecutionStatus::Pending->value,
            'current_responsible_id' => null,
            'execution_started_by' => null,
            'started_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $gondola = Gondola::query()
        ->with('planogram.gondolas')
        ->findOrFail($startedGondolaId);

    $payload = app(GondolaPayloadService::class)->buildEditorPayload($gondola);

    expect(collect($payload['planogram']['gondolas'])->pluck('id')->all())
        ->toBe([$startedGondolaId]);
});
