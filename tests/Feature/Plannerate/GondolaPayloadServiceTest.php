<?php

use App\Enums\WorkflowExecutionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
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

test('editor payload includes template slot details for shelf when mapping exists', function (): void {
    $now = now();
    $tenantId = (string) Str::ulid();
    $planogramId = (string) Str::ulid();
    $gondolaId = (string) Str::ulid();
    $sectionId = (string) Str::ulid();
    $templateId = (string) Str::ulid();
    $subtemplateId = (string) Str::ulid();
    $slotId = (string) Str::ulid();
    $middleShelfId = (string) Str::ulid();

    $tenant = new Tenant([
        'name' => 'Tenant Slot',
        'slug' => 'tenant-slot',
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status' => 'active',
    ]);
    $tenant->id = $tenantId;

    app()->instance('tenant', $tenant);
    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);

    DB::table('planograms')->insert([
        'id' => $planogramId,
        'tenant_id' => $tenantId,
        'subtemplate_id' => $subtemplateId,
        'name' => 'Planograma com template',
        'slug' => 'planograma-com-template',
        'type' => 'planograma',
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('gondolas')->insert([
        'id' => $gondolaId,
        'tenant_id' => $tenantId,
        'planogram_id' => $planogramId,
        'template_id' => $templateId,
        'name' => 'Gondola Template',
        'slug' => 'gondola-template',
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('sections')->insert([
        'id' => $sectionId,
        'tenant_id' => $tenantId,
        'gondola_id' => $gondolaId,
        'name' => 'Modulo 1',
        'code' => 'M1',
        'ordering' => 1,
        'width' => 90,
        'height' => 180,
        'base_height' => 10,
        'base_depth' => 40,
        'base_width' => 90,
        'cremalheira_width' => 2,
        'hole_height' => 2,
        'hole_spacing' => 2,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('shelves')->insert([
        [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'section_id' => $sectionId,
            'ordering' => 1,
            'shelf_position' => 10,
            'shelf_width' => 90,
            'shelf_height' => 4,
            'shelf_depth' => 40,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $middleShelfId,
            'tenant_id' => $tenantId,
            'section_id' => $sectionId,
            'ordering' => 2,
            'shelf_position' => 20,
            'shelf_width' => 90,
            'shelf_height' => 4,
            'shelf_depth' => 40,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'section_id' => $sectionId,
            'ordering' => 3,
            'shelf_position' => 30,
            'shelf_width' => 90,
            'shelf_height' => 4,
            'shelf_depth' => 40,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('planogram_templates')->insert([
        'id' => $templateId,
        'tenant_id' => $tenantId,
        'code' => 'TPL-1',
        'name' => 'Template 1',
        'department' => 'Bebidas',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('planogram_subtemplates')->insert([
        'id' => $subtemplateId,
        'tenant_id' => $tenantId,
        'template_id' => $templateId,
        'code' => 'SUB-1',
        'num_modules' => 1,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('planogram_template_slots')->insert([
        'id' => $slotId,
        'tenant_id' => $tenantId,
        'subtemplate_id' => $subtemplateId,
        'category_id' => null,
        'module_number' => 1,
        'shelf_order' => 2,
        'category' => 'Refrigerante',
        'subcategory' => 'Cola',
        'min_facings' => 2,
        'priority' => 1,
        'price_order' => 'none',
        'size_order' => 'none',
        'brand_exposure' => 'mixed',
        'flavor_exposure' => 'mixed',
        'space_fallback' => 'reduce_c',
        'use_target_stock' => false,
        'facing_expansion' => 'none',
        'max_facings' => 5,
        'ordering' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gondola = Gondola::query()
        ->with(['planogram', 'sections.shelves'])
        ->findOrFail($gondolaId);

    $payload = app(GondolaPayloadService::class)->buildEditorPayload($gondola);
    $shelves = collect($payload['sections'][0]['shelves'] ?? []);
    $targetShelf = $shelves->firstWhere('id', $middleShelfId);

    expect($targetShelf)
        ->not->toBeNull()
        ->and($targetShelf['template_slot']['id'] ?? null)->toBe($slotId)
        ->and($targetShelf['template_slot']['module_number'] ?? null)->toBe(1)
        ->and($targetShelf['template_slot']['shelf_order'] ?? null)->toBe(2)
        ->and($targetShelf['template_slot']['min_facings'] ?? null)->toBe(2);
});
