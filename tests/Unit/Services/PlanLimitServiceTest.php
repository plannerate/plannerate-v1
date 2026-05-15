<?php

use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Tenant;
use App\Services\PlanLimitService;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

function planLimitServiceMakePlan(string $key, mixed $value, string $type = 'integer'): Plan
{
    $item = new PlanItem([
        'key' => $key,
        'value' => (string) $value,
        'type' => $type,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $plan = Mockery::mock(Plan::class)->makePartial();
    $plan->allows('loadMissing')->andReturnSelf();
    $plan->setRelation('items', new Collection([$item]));

    return $plan;
}

function planLimitServiceWithTenant(?Tenant $tenant): PlanLimitService
{
    $service = Mockery::mock(PlanLimitService::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $service->allows('currentTenant')->andReturn($tenant);

    return $service;
}

function planLimitServiceMakeTenant(?Plan $plan): Tenant
{
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->allows('loadMissing')->andReturnSelf();
    $tenant->setRelation('plan', $plan);

    return $tenant;
}

test('returns null when no current tenant', function (): void {
    $service = planLimitServiceWithTenant(null);

    expect($service->getLimit('store_limit'))->toBeNull();
});

test('returns null when tenant has no plan', function (): void {
    $tenant = planLimitServiceMakeTenant(null);
    $service = planLimitServiceWithTenant($tenant);

    expect($service->getLimit('store_limit'))->toBeNull();
});

test('returns null when plan has no matching item', function (): void {
    $plan = Mockery::mock(Plan::class)->makePartial();
    $plan->allows('loadMissing')->andReturnSelf();
    $plan->setRelation('items', new Collection);

    $service = planLimitServiceWithTenant(planLimitServiceMakeTenant($plan));

    expect($service->getLimit('store_limit'))->toBeNull();
});

test('returns integer limit from plan item', function (): void {
    $plan = planLimitServiceMakePlan('store_limit', 5);
    $service = planLimitServiceWithTenant(planLimitServiceMakeTenant($plan));

    expect($service->getLimit('store_limit'))->toBe(5);
});

test('withinLimit returns true when count is below limit', function (): void {
    $plan = planLimitServiceMakePlan('store_limit', 5);
    $service = planLimitServiceWithTenant(planLimitServiceMakeTenant($plan));

    expect($service->withinLimit('store_limit', 4))->toBeTrue();
});

test('withinLimit returns false when count equals limit', function (): void {
    $plan = planLimitServiceMakePlan('store_limit', 5);
    $service = planLimitServiceWithTenant(planLimitServiceMakeTenant($plan));

    expect($service->withinLimit('store_limit', 5))->toBeFalse();
});

test('withinLimit returns true when no limit configured', function (): void {
    $plan = Mockery::mock(Plan::class)->makePartial();
    $plan->allows('loadMissing')->andReturnSelf();
    $plan->setRelation('items', new Collection);

    $service = planLimitServiceWithTenant(planLimitServiceMakeTenant($plan));

    expect($service->withinLimit('store_limit', 9999))->toBeTrue();
});
