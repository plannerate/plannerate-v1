<?php

use App\Enums\ExecutionDivergenceStatus;
use App\Enums\PlanogramLifecycleStatus;
use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowHistoryAction;
use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\WorkflowExecutionDivergence;
use App\Models\WorkflowExecutionEvidence;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use App\Models\WorkflowPlanogramStep;
use App\Services\WorkflowExecutionLayerService;
use App\Services\WorkflowKanbanService;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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

/**
 * Monta planograma + etapa final de fluxo + gôndola + execução para o tenant.
 *
 * @return array{planogram: Planogram, gondola: Gondola, step: WorkflowPlanogramStep}
 */
function executionLayerScaffold(string $tenantId, array $planogramAttributes = []): array
{
    $planogram = Planogram::factory()->create(array_merge([
        'tenant_id' => $tenantId,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ], $planogramAttributes));

    $template = completionTemplate($tenantId, 6, 'flow');
    $step = completionStep($tenantId, $planogram, $template);

    $gondola = Gondola::factory()->create([
        'tenant_id' => $tenantId,
        'planogram_id' => $planogram->id,
    ]);

    return ['planogram' => $planogram, 'gondola' => $gondola, 'step' => $step];
}

test('início automático ativa execução pendente e registra histórico', function (): void {
    $context = setupKanbanTenantCtx('exec-layer-autostart');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $scaffold = executionLayerScaffold($tenantId);

    $execution = WorkflowGondolaExecution::query()->create([
        'tenant_id' => $tenantId,
        'gondola_id' => $scaffold['gondola']->id,
        'workflow_planogram_step_id' => $scaffold['step']->id,
        'status' => WorkflowExecutionStatus::Pending->value,
    ]);

    app(WorkflowExecutionLayerService::class)->autoStartIfPending($execution, $user);

    $execution->refresh();

    expect($execution->status)->toBe(WorkflowExecutionStatus::Active);
    expect($execution->started_at)->not->toBeNull();
    expect((string) $execution->current_responsible_id)->toBe((string) $user->id);
    expect(
        WorkflowHistory::query()
            ->where('workflow_gondola_execution_id', $execution->id)
            ->where('action', WorkflowHistoryAction::Started->value)
            ->exists()
    )->toBeTrue();
});

test('salva evidência da execução', function (): void {
    Storage::fake('public');
    $context = setupKanbanTenantCtx('exec-layer-evidence');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $scaffold = executionLayerScaffold($tenantId);
    $execution = activeExecution($tenantId, $scaffold['gondola'], $scaffold['step'], $user->id);

    $this->post(route('tenant.executions.evidences.store', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]), [
        'type' => 'general_photo',
        'file' => UploadedFile::fake()->image('evidencia.jpg'),
    ])->assertRedirect();

    $evidence = WorkflowExecutionEvidence::query()
        ->where('workflow_gondola_execution_id', $execution->id)
        ->first();

    expect($evidence)->not->toBeNull();
    Storage::disk('public')->assertExists($evidence->file_path);
});

test('salva divergência da execução como aberta', function (): void {
    $context = setupKanbanTenantCtx('exec-layer-divergence');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $scaffold = executionLayerScaffold($tenantId);
    $execution = activeExecution($tenantId, $scaffold['gondola'], $scaffold['step'], $user->id);

    $this->post(route('tenant.executions.divergences.store', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]), [
        'type' => 'ruptura',
        'notes' => 'Sem estoque na loja.',
    ])->assertRedirect();

    $divergence = WorkflowExecutionDivergence::query()
        ->where('workflow_gondola_execution_id', $execution->id)
        ->first();

    expect($divergence)->not->toBeNull();
    expect($divergence->status)->toBe(ExecutionDivergenceStatus::Open);
});

test('conclusão é bloqueada quando falta evidência obrigatória', function (): void {
    $context = setupKanbanTenantCtx('exec-layer-block-evidence');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $scaffold = executionLayerScaffold($tenantId);
    $execution = activeExecution($tenantId, $scaffold['gondola'], $scaffold['step'], $user->id);

    $this->post(route('tenant.executions.complete', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]))->assertSessionHasErrors('evidences');

    $execution->refresh();
    expect($execution->status)->toBe(WorkflowExecutionStatus::Active);

    $scaffold['planogram']->refresh();
    expect($scaffold['planogram']->lifecycle_status)->toBe(PlanogramLifecycleStatus::InProgress);
});

test('conclusão é bloqueada quando há divergência pendente', function (): void {
    $context = setupKanbanTenantCtx('exec-layer-block-divergence');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $scaffold = executionLayerScaffold($tenantId);
    $execution = activeExecution($tenantId, $scaffold['gondola'], $scaffold['step'], $user->id);

    WorkflowExecutionEvidence::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'workflow_gondola_execution_id' => $execution->id,
        'type' => 'general_photo',
        'file_path' => 'execution-evidences/test/a.jpg',
    ]);

    WorkflowExecutionDivergence::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'workflow_gondola_execution_id' => $execution->id,
        'type' => 'ruptura',
        'status' => ExecutionDivergenceStatus::Open->value,
    ]);

    $this->post(route('tenant.executions.complete', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]))->assertSessionHasErrors('divergences');

    $execution->refresh();
    expect($execution->status)->toBe(WorkflowExecutionStatus::Active);
});

test('conclusão feliz marca execução e planograma como concluídos', function (): void {
    $context = setupKanbanTenantCtx('exec-layer-complete-ok');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $scaffold = executionLayerScaffold($tenantId);
    $execution = activeExecution($tenantId, $scaffold['gondola'], $scaffold['step'], $user->id);

    WorkflowExecutionEvidence::query()->create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'workflow_gondola_execution_id' => $execution->id,
        'type' => 'general_photo',
        'file_path' => 'execution-evidences/test/a.jpg',
    ]);

    $this->post(route('tenant.executions.complete', [
        'subdomain' => $context['subdomain'],
        'execution' => $execution->id,
    ]))->assertRedirect();

    $execution->refresh();
    expect($execution->status)->toBe(WorkflowExecutionStatus::Completed);
    expect($execution->completed_at)->not->toBeNull();

    $scaffold['planogram']->refresh();
    expect($scaffold['planogram']->lifecycle_status)->toBe(PlanogramLifecycleStatus::Completed);
    expect($scaffold['planogram']->completed_at)->not->toBeNull();
    expect($scaffold['planogram']->periodic_review_due_at)->not->toBeNull();
});

test('planograma concluído some do board por padrão e reaparece com o filtro', function (): void {
    $context = setupKanbanTenantCtx('exec-layer-hide-completed');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $scaffold = executionLayerScaffold($tenantId, [
        'lifecycle_status' => PlanogramLifecycleStatus::Completed->value,
        'completed_at' => now(),
    ]);
    activeExecution($tenantId, $scaffold['gondola'], $scaffold['step'], $user->id);

    // Por padrão: concluído oculto → nenhuma execução no board.
    $service = app(WorkflowKanbanService::class);
    $board = $service->buildBoardForTenant($user);
    $visible = collect($board)->flatMap(fn (array $column): array => $column['executions'])->count();
    expect($visible)->toBe(0);

    // Com o filtro lifecycle_status=completed → reaparece.
    $boardCompleted = $service->buildBoardForTenant($user, null, null, null, null, null, PlanogramLifecycleStatus::Completed->value);
    $visibleCompleted = collect($boardCompleted)->flatMap(fn (array $column): array => $column['executions'])->count();
    expect($visibleCompleted)->toBe(1);
});
