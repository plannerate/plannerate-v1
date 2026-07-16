<?php

use App\Models\TenantIntegration;
use App\Services\Integrations\Discovery\DailyModeDiscoverer;
use App\Services\Integrations\Discovery\PageModeDiscoverer;

it('never expands the discovered last page when max_page is higher', function (): void {
    $discoverer = new PageModeDiscoverer('01testintegrationid000000000', 'products');

    $method = new ReflectionMethod($discoverer, 'applyMaxPageLimit');
    $method->setAccessible(true);

    $result = $method->invoke($discoverer, 4, ['max_page' => 10]);

    expect($result)->toBe(4);
});

it('caps the discovered last page when max_page is lower', function (): void {
    $discoverer = new PageModeDiscoverer('01testintegrationid000000000', 'products');

    $method = new ReflectionMethod($discoverer, 'applyMaxPageLimit');
    $method->setAccessible(true);

    $result = $method->invoke($discoverer, 10, ['max_page' => 3]);

    expect($result)->toBe(3);
});

it('forceFull skips the changed_since resolution and returns null dates without touching the tenant', function (): void {
    $discoverer = new PageModeDiscoverer('01testintegrationid000000000', 'products');

    // A mock with no expectations set: forceFull must return before the
    // integration is ever inspected (storeHasRecords, resolveChunkDates, ...).
    $integration = Mockery::mock(TenantIntegration::class);

    $method = new ReflectionMethod($discoverer, 'resolveEffectiveDates');
    $method->setAccessible(true);

    $result = $method->invoke($discoverer, $integration, [
        'date_fields' => ['changed_since' => 'data_ultima_alteracao'],
        'initial_days' => 0,
    ], null, true);

    expect($result)->toBe([null, null]);
});

it('generates distinct dates when building the all-dates range', function (): void {
    $discoverer = new DailyModeDiscoverer('01testintegrationid000000000', 'sales');

    $method = new ReflectionMethod($discoverer, 'resolveMissingDays');
    $method->setAccessible(true);

    // Simulate a DB that already has all dates filled — but we just need to check
    // the generated allDates range itself via the missing count when existingDates = [].
    // We do this by injecting a mock integration that returns no existing dates.
    $integration = Mockery::mock(TenantIntegration::class);
    $integration->shouldReceive('getAttribute')->with('tenant')->andReturn(null);

    $pathConfig = [
        'initial_days' => 5,
        'last_date_column' => 'sale_date',
        'target_table' => 'sales',
    ];

    $result = $method->invoke($discoverer, $integration, $pathConfig, null);

    // With null tenant, getExistingDates returns [] → all 6 days (today + 5) are missing
    expect($result)->toHaveCount(6);
    // Dates must all be unique (no duplicate from immutable cursor bug)
    expect(array_unique($result))->toHaveCount(6);
});

it('forceFull returns the full day range without touching the tenant existing dates', function (): void {
    $discoverer = new DailyModeDiscoverer('01testintegrationid000000000', 'sales');

    $method = new ReflectionMethod($discoverer, 'resolveMissingDays');
    $method->setAccessible(true);

    // A mock with no expectations set: forceFull must return before
    // getExistingDates ever inspects the integration/tenant.
    $integration = Mockery::mock(TenantIntegration::class);

    $pathConfig = [
        'initial_days' => 5,
        'last_date_column' => 'sale_date',
        'target_table' => 'sales',
    ];

    $result = $method->invoke($discoverer, $integration, $pathConfig, null, true);

    // All 6 days (today + 5) returned regardless of what's already in the DB
    expect($result)->toHaveCount(6);
    expect(array_unique($result))->toHaveCount(6);
});

it('always re-fetches days inside the recheck window even when they have records', function (): void {
    config(['integrations.recheck_days' => 3]);

    $discoverer = new DailyModeDiscoverer('01testintegrationid000000000', 'sales');

    $method = new ReflectionMethod($discoverer, 'applyRecheckWindow');
    $method->setAccessible(true);

    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();
    $oldDay = now()->subDays(10)->toDateString();

    // Dias dentro da janela saem da lista de "completos" → serão re-buscados
    $result = $method->invoke($discoverer, [$today, $yesterday, $oldDay]);

    expect($result)->toBe([$oldDay]);
});

it('incremental start date in page mode goes back recheck_days instead of one day', function (): void {
    config(['integrations.recheck_days' => 3]);

    $discoverer = new PageModeDiscoverer('01testintegrationid000000000', 'products');

    $method = new ReflectionMethod($discoverer, 'incrementalStartDate');
    $method->setAccessible(true);

    expect($method->invoke($discoverer))->toBe(now()->subDays(3)->toDateString());
});
