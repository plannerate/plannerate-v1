<?php

use App\Models\TenantIntegration;
use App\Services\Integrations\Support\IntegrationServiceResolver;
use App\Services\Integrations\Sysmo\SysmoProductsIntegrationService;
use App\Services\Integrations\Sysmo\SysmoSalesIntegrationService;

test('integration service resolver returns sysmo services for sysmo integration type', function () {
    $resolver = app(IntegrationServiceResolver::class);

    $integration = new TenantIntegration([
        'integration_type' => 'sysmo',
    ]);

    expect($resolver->resolveProductsService($integration))->toBeInstanceOf(SysmoProductsIntegrationService::class)
        ->and($resolver->resolveSalesService($integration))->toBeInstanceOf(SysmoSalesIntegrationService::class);
});

test('integration service resolver throws for unknown integration type', function () {
    $resolver = app(IntegrationServiceResolver::class);
    $integration = new TenantIntegration([
        'integration_type' => 'outra_api',
    ]);

    expect(fn () => $resolver->resolveProductsService($integration))
        ->toThrow(\RuntimeException::class);

    expect(fn () => $resolver->resolveSalesService($integration))
        ->toThrow(\RuntimeException::class);
});
