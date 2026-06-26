<?php

use App\Enums\PlanogramLifecycleStatus;
use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowHistoryAction;
use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use App\Models\WorkflowPlanogramStep;
use App\Models\WorkflowTemplate;
use App\Notifications\AppNotification;
use App\Services\PeriodicReviewService;
use Carbon\CarbonInterface;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
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
 * Cria um planograma concluído com etapa de Revisão Periódica e gôndolas,
 * pronto para promoção. O usuário do contexto é vinculado como responsável.
 *
 * @return array{planogram: Planogram, step: WorkflowPlanogramStep, gondolas: Collection, responsible: User}
 */
function buildPromotablePlanogram(array $context, ?CarbonInterface $dueAt = null, int $gondolaCount = 1): array
{
    $tenantId = $context['tenant']->id;

    // Responsável criado já no contexto tenant para que o belongsToMany
    // availableUsers (conexão tenant) o encontre ao notificar.
    $responsible = User::factory()->create();

    $planogram = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => $context['user']->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'lifecycle_status' => PlanogramLifecycleStatus::Completed,
        'completed_at' => now()->subDays(40),
        'periodic_review_due_at' => $dueAt ?? now()->subDay(),
        'periodic_review_started_at' => null,
    ]);

    $template = WorkflowTemplate::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Revisão periódica',
        'slug' => 'revisao-periodica-'.Str::lower(Str::random(8)),
        'suggested_order' => 7,
        'stage_type' => 'periodic_review',
        'access_mode' => 'view',
        'status' => 'published',
    ]);

    $step = WorkflowPlanogramStep::query()->create([
        'tenant_id' => $tenantId,
        'planogram_id' => $planogram->id,
        'workflow_template_id' => $template->id,
        'stage_type' => 'periodic_review',
        'status' => 'published',
    ]);

    $step->availableUsers()->sync([$responsible->id]);

    $gondolas = collect(range(1, $gondolaCount))->map(fn (): Gondola => Gondola::factory()->create([
        'tenant_id' => $tenantId,
        'planogram_id' => $planogram->id,
    ]));

    return compact('planogram', 'step', 'gondolas', 'responsible');
}

test('promove planograma concluído e vencido para revisão periódica', function (): void {
    Notification::fake();

    $context = setupKanbanTenantCtx('periodic-promote');
    $data = buildPromotablePlanogram($context, gondolaCount: 2);

    $promoted = app(PeriodicReviewService::class)->promote($data['planogram']);

    expect($promoted)->toBeTrue();

    $data['planogram']->refresh();
    expect($data['planogram']->lifecycle_status)->toBe(PlanogramLifecycleStatus::PeriodicReview);
    expect($data['planogram']->periodic_review_started_at)->not->toBeNull();

    $executions = WorkflowGondolaExecution::query()
        ->where('workflow_planogram_step_id', $data['step']->id)
        ->get();

    expect($executions)->toHaveCount(2);
    expect($executions->every(fn (WorkflowGondolaExecution $e): bool => $e->status === WorkflowExecutionStatus::Pending))->toBeTrue();

    expect(
        WorkflowHistory::query()
            ->where('action', WorkflowHistoryAction::PeriodicReviewTriggered->value)
            ->count()
    )->toBe(2);

    Notification::assertSentTo($data['responsible'], AppNotification::class);
});

test('promover duas vezes não duplica execuções nem histórico', function (): void {
    Notification::fake();

    $context = setupKanbanTenantCtx('periodic-idempotent');
    $data = buildPromotablePlanogram($context, gondolaCount: 1);

    $service = app(PeriodicReviewService::class);

    expect($service->promote($data['planogram']))->toBeTrue();
    expect($service->promote($data['planogram']->refresh()))->toBeFalse();

    expect(
        WorkflowGondolaExecution::query()->where('workflow_planogram_step_id', $data['step']->id)->count()
    )->toBe(1);

    expect(
        WorkflowHistory::query()->where('action', WorkflowHistoryAction::PeriodicReviewTriggered->value)->count()
    )->toBe(1);
});

test('elegibilidade ignora vencimento futuro, sem data, já iniciados e em andamento', function (): void {
    $context = setupKanbanTenantCtx('periodic-eligible');
    $tenantId = $context['tenant']->id;

    $eligivel = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => $context['user']->id,
        'lifecycle_status' => PlanogramLifecycleStatus::Completed,
        'periodic_review_due_at' => now()->subDay(),
        'periodic_review_started_at' => null,
    ]);

    $futuro = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => $context['user']->id,
        'lifecycle_status' => PlanogramLifecycleStatus::Completed,
        'periodic_review_due_at' => now()->addDays(5),
    ]);

    $semData = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => $context['user']->id,
        'lifecycle_status' => PlanogramLifecycleStatus::Completed,
        'periodic_review_due_at' => null,
    ]);

    $jaIniciado = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => $context['user']->id,
        'lifecycle_status' => PlanogramLifecycleStatus::PeriodicReview,
        'periodic_review_due_at' => now()->subDays(3),
        'periodic_review_started_at' => now()->subDay(),
    ]);

    $emAndamento = Planogram::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => $context['user']->id,
        'lifecycle_status' => PlanogramLifecycleStatus::InProgress,
        'periodic_review_due_at' => now()->subDay(),
    ]);

    $ids = app(PeriodicReviewService::class)->eligibleForPromotion()->pluck('id')->all();

    expect($ids)->toContain($eligivel->id);
    expect($ids)->not->toContain($futuro->id);
    expect($ids)->not->toContain($semData->id);
    expect($ids)->not->toContain($jaIniciado->id);
    expect($ids)->not->toContain($emAndamento->id);
});
