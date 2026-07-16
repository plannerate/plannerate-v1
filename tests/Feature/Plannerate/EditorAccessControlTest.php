<?php

use App\Enums\GondolaEditDecision;
use App\Enums\WorkflowExecutionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PermissionName;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use App\Support\Workflow\GondolaEditGate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Prepara um tenant de teste com o módulo Kanban ativo (ou não) e devolve seu id.
 */
function bootEditorAccessTenant(bool $kanbanActive): Tenant
{
    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $tenant = new Tenant([
        'name' => 'Tenant Editor',
        'slug' => 'tenant-editor',
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status' => 'active',
    ]);
    $tenant->id = (string) Str::ulid();

    app()->instance('tenant', $tenant);
    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);
    app()->instance(TenantModuleService::class, new class($kanbanActive) extends TenantModuleService
    {
        public function __construct(private bool $kanbanActive) {}

        public function tenantHasActiveModule(Tenant $tenant, string $slug): bool
        {
            unset($tenant);

            return $this->kanbanActive && $slug === ModuleSlug::KANBAN;
        }
    });

    return $tenant;
}

test('só o responsável que iniciou pode abrir o editor quando o Kanban está ativo', function (): void {
    $tenant = bootEditorAccessTenant(kanbanActive: true);
    $now = now();

    $user = new User;
    $user->id = (string) Str::ulid();

    $otherUser = new User;
    $otherUser->id = (string) Str::ulid();

    $noPermUser = new User;
    $noPermUser->id = (string) Str::ulid();

    // Concede TENANT_GONDOLAS_UPDATE apenas ao $user (o gate usa $user->can()).
    Gate::before(fn (User $u, string $ability): ?bool => $ability === PermissionName::TENANT_GONDOLAS_UPDATE && $u->id === $user->id ? true : null);

    $planogramId = (string) Str::ulid();
    $templateId = (string) Str::ulid();
    $stepEditId = (string) Str::ulid();
    $stepViewId = (string) Str::ulid();

    $ownEditable = (string) Str::ulid();
    $otherStarted = (string) Str::ulid();
    $notStarted = (string) Str::ulid();
    $readOnly = (string) Str::ulid();

    DB::table('planograms')->insert([
        'id' => $planogramId,
        'tenant_id' => $tenant->id,
        'name' => 'Planograma Editor',
        'slug' => 'planograma-editor',
        'type' => 'planograma',
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gondolaRow = fn (string $id, string $slug): array => [
        'id' => $id,
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogramId,
        'name' => $slug,
        'slug' => $slug,
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ];

    DB::table('gondolas')->insert([
        $gondolaRow($ownEditable, 'own-editable'),
        $gondolaRow($otherStarted, 'other-started'),
        $gondolaRow($notStarted, 'not-started'),
        $gondolaRow($readOnly, 'read-only'),
    ]);

    DB::table('workflow_templates')->insert([
        'id' => $templateId,
        'tenant_id' => $tenant->id,
        'name' => 'Template Editor',
        'slug' => 'template-editor',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('workflow_planogram_steps')->insert([
        [
            'id' => $stepEditId,
            'tenant_id' => $tenant->id,
            'planogram_id' => $planogramId,
            'workflow_template_id' => $templateId,
            'name' => 'Etapa Edição',
            'access_mode' => 'edit',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => $stepViewId,
            'tenant_id' => $tenant->id,
            'planogram_id' => $planogramId,
            'workflow_template_id' => $templateId,
            'name' => 'Etapa Somente Leitura',
            'access_mode' => 'view',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $execution = fn (string $gondolaId, string $stepId, string $startedBy): array => [
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenant->id,
        'gondola_id' => $gondolaId,
        'workflow_planogram_step_id' => $stepId,
        'status' => WorkflowExecutionStatus::Active->value,
        'current_responsible_id' => $startedBy,
        'execution_started_by' => $startedBy,
        'started_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    DB::table('workflow_gondola_executions')->insert([
        $execution($ownEditable, $stepEditId, $user->id),
        $execution($otherStarted, $stepEditId, $otherUser->id),
        $execution($readOnly, $stepViewId, $user->id),
        // $notStarted: propositalmente sem execução ativa
    ]);

    $gate = app(GondolaEditGate::class);

    expect($gate->decide($user, $ownEditable))->toBe(GondolaEditDecision::Allowed)
        ->and($gate->decide($user, $otherStarted))->toBe(GondolaEditDecision::NotOwner)
        ->and($gate->decide($user, $notStarted))->toBe(GondolaEditDecision::NotStarted)
        ->and($gate->decide($user, $readOnly))->toBe(GondolaEditDecision::ReadOnlyStep)
        ->and($gate->decide($noPermUser, $ownEditable))->toBe(GondolaEditDecision::Forbidden);
});

test('sem o módulo Kanban ativo a edição permanece liberada (legado)', function (): void {
    $tenant = bootEditorAccessTenant(kanbanActive: false);
    $now = now();

    $user = new User;
    $user->id = (string) Str::ulid();

    $planogramId = (string) Str::ulid();
    $gondolaId = (string) Str::ulid();

    DB::table('planograms')->insert([
        'id' => $planogramId,
        'tenant_id' => $tenant->id,
        'name' => 'Planograma Legado',
        'slug' => 'planograma-legado',
        'type' => 'planograma',
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('gondolas')->insert([
        'id' => $gondolaId,
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogramId,
        'name' => 'Gôndola Legado',
        'slug' => 'gondola-legado',
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Sem execução de workflow e sem permissão concedida: ainda assim liberado,
    // porque o módulo Kanban está inativo (curto-circuito no gate).
    expect(app(GondolaEditGate::class)->decide($user, $gondolaId))->toBe(GondolaEditDecision::Allowed);
});
