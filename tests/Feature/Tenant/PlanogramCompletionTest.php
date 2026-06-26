<?php

use App\Enums\PlanogramLifecycleStatus;
use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowPlanogramStep;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowKanbanService;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

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
 * Cria um template de etapa do workflow para o tenant atual.
 */
function completionTemplate(string $tenantId, int $order, string $stageType = 'flow'): WorkflowTemplate
{
    return WorkflowTemplate::query()->create([
        'tenant_id' => $tenantId,
        'name' => "Etapa {$order}",
        'slug' => "etapa-{$order}-".Str::lower(Str::random(8)),
        'suggested_order' => $order,
        'stage_type' => $stageType,
        'access_mode' => 'view',
        'status' => 'published',
    ]);
}

/**
 * Cria a etapa de planograma vinculada ao template informado.
 */
function completionStep(string $tenantId, Planogram $planogram, WorkflowTemplate $template): WorkflowPlanogramStep
{
    return WorkflowPlanogramStep::query()->create([
        'tenant_id' => $tenantId,
        'planogram_id' => $planogram->id,
        'workflow_template_id' => $template->id,
        'status' => 'published',
    ]);
}

/**
 * Cria uma execução ativa, iniciada pelo usuário, na etapa informada.
 */
function activeExecution(string $tenantId, Gondola $gondola, WorkflowPlanogramStep $step, string $userId): WorkflowGondolaExecution
{
    return WorkflowGondolaExecution::query()->create([
        'tenant_id' => $tenantId,
        'gondola_id' => $gondola->id,
        'workflow_planogram_step_id' => $step->id,
        'status' => 'active',
        'execution_started_by' => $userId,
        'current_responsible_id' => $userId,
        'started_at' => now(),
    ]);
}

test('concluir a última gôndola na etapa final de fluxo marca o planograma como concluído', function (): void {
    $context = setupKanbanTenantCtx('plano-complete-final');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    $tplFlow = completionTemplate($tenantId, 6, 'flow');
    $tplPeriodic = completionTemplate($tenantId, 7, 'periodic_review');
    $stepFlow = completionStep($tenantId, $planogram, $tplFlow);
    $stepPeriodic = completionStep($tenantId, $planogram, $tplPeriodic);

    $gondola = Gondola::factory()->create([
        'tenant_id' => $tenantId,
        'planogram_id' => $planogram->id,
    ]);
    $execution = activeExecution($tenantId, $gondola, $stepFlow, $user->id);

    // "Concluir" disponível na etapa final de fluxo (Execução Loja, ordem 6).
    expect($user->can('complete', $execution))->toBeTrue();

    app(WorkflowKanbanService::class)->complete($execution, $user);

    $planogram->refresh();

    expect($planogram->lifecycle_status)->toBe(PlanogramLifecycleStatus::Completed);
    expect($planogram->completed_at)->not->toBeNull();
    expect($planogram->periodic_review_due_at)->not->toBeNull();

    // due = completed_at + (end - start) = completed_at + 30 dias.
    $expectedDue = $planogram->completed_at->copy()->addDays(30);
    expect($planogram->periodic_review_due_at->equalTo($expectedDue))->toBeTrue();

    // Etapa de Revisão Periódica não foi tocada.
    expect(WorkflowGondolaExecution::query()->where('workflow_planogram_step_id', $stepPeriodic->id)->count())->toBe(0);
});

test('concluir apenas uma de duas gôndolas não conclui o planograma', function (): void {
    $context = setupKanbanTenantCtx('plano-complete-partial');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    $tplFlow = completionTemplate($tenantId, 6, 'flow');
    $stepFlow = completionStep($tenantId, $planogram, $tplFlow);

    $gondolaA = Gondola::factory()->create(['tenant_id' => $tenantId, 'planogram_id' => $planogram->id]);
    $gondolaB = Gondola::factory()->create(['tenant_id' => $tenantId, 'planogram_id' => $planogram->id]);

    $execA = activeExecution($tenantId, $gondolaA, $stepFlow, $user->id);
    activeExecution($tenantId, $gondolaB, $stepFlow, $user->id);

    app(WorkflowKanbanService::class)->complete($execA, $user);

    $planogram->refresh();

    expect($planogram->lifecycle_status)->toBe(PlanogramLifecycleStatus::InProgress);
    expect($planogram->completed_at)->toBeNull();
});

test('a etapa de Revisão Periódica não é concluível manualmente', function (): void {
    $context = setupKanbanTenantCtx('plano-complete-periodic');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $planogram = Planogram::factory()->create(['tenant_id' => $tenantId]);

    $tplFlow = completionTemplate($tenantId, 6, 'flow');
    $tplPeriodic = completionTemplate($tenantId, 7, 'periodic_review');
    completionStep($tenantId, $planogram, $tplFlow);
    $stepPeriodic = completionStep($tenantId, $planogram, $tplPeriodic);

    $gondola = Gondola::factory()->create(['tenant_id' => $tenantId, 'planogram_id' => $planogram->id]);
    $execution = activeExecution($tenantId, $gondola, $stepPeriodic, $user->id);

    expect($user->can('complete', $execution))->toBeFalse();
});

test('planograma sem datas conclui mas não agenda revisão periódica', function (): void {
    $context = setupKanbanTenantCtx('plano-complete-nodate');
    $user = $context['user'];
    $tenantId = $context['tenant']->id;
    $this->actingAs($user);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'start_date' => null,
        'end_date' => null,
    ]);

    $tplFlow = completionTemplate($tenantId, 6, 'flow');
    $stepFlow = completionStep($tenantId, $planogram, $tplFlow);

    $gondola = Gondola::factory()->create(['tenant_id' => $tenantId, 'planogram_id' => $planogram->id]);
    $execution = activeExecution($tenantId, $gondola, $stepFlow, $user->id);

    app(WorkflowKanbanService::class)->complete($execution, $user);

    $planogram->refresh();

    expect($planogram->lifecycle_status)->toBe(PlanogramLifecycleStatus::Completed);
    expect($planogram->periodic_review_due_at)->toBeNull();
});
