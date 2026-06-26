<?php

use App\Enums\PlanogramLifecycleStatus;
use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Planogram;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

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

test('alterar o período de um planograma concluído recalcula o vencimento', function (): void {
    $context = setupKanbanTenantCtx('observer-recalc');

    $planogram = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'user_id' => $context['user']->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'lifecycle_status' => PlanogramLifecycleStatus::Completed,
        'completed_at' => '2026-02-10 12:00:00',
        'periodic_review_due_at' => '2026-03-12 12:00:00',
        'periodic_review_started_at' => null,
    ]);

    // Estende o período: start Jan/01 → end Fev/10 (40 dias).
    $planogram->update(['end_date' => '2026-02-10']);
    $planogram->refresh();

    $expected = $planogram->completed_at->copy()->add(
        $planogram->start_date->diffAsCarbonInterval($planogram->end_date)
    );

    expect($planogram->periodic_review_due_at->equalTo($expected))->toBeTrue();
});

test('alterar o período de um planograma já em revisão não altera o vencimento', function (): void {
    $context = setupKanbanTenantCtx('observer-frozen');

    $fixedDue = '2026-03-12 12:00:00';

    $planogram = Planogram::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'user_id' => $context['user']->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'lifecycle_status' => PlanogramLifecycleStatus::PeriodicReview,
        'completed_at' => '2026-02-10 12:00:00',
        'periodic_review_due_at' => $fixedDue,
        'periodic_review_started_at' => '2026-03-12 12:00:00',
    ]);

    $before = $planogram->periodic_review_due_at->copy();

    $planogram->update(['end_date' => '2026-02-10']);
    $planogram->refresh();

    expect($planogram->periodic_review_due_at->equalTo($before))->toBeTrue();
});
