<?php

use App\Jobs\Integrations\DiscoverIntegrationPagesJob;

it('never expands the discovered last page when max_page is higher', function (): void {
    $job = new DiscoverIntegrationPagesJob(
        integrationId: '01testintegrationid000000000',
        pathKey: 'products',
    );

    $method = new ReflectionMethod($job, 'applyMaxPageLimit');
    $method->setAccessible(true);

    $result = $method->invoke($job, 4, ['max_page' => 10]);

    expect($result)->toBe(4);
});

it('caps the discovered last page when max_page is lower', function (): void {
    $job = new DiscoverIntegrationPagesJob(
        integrationId: '01testintegrationid000000000',
        pathKey: 'products',
    );

    $method = new ReflectionMethod($job, 'applyMaxPageLimit');
    $method->setAccessible(true);

    $result = $method->invoke($job, 10, ['max_page' => 3]);

    expect($result)->toBe(3);
});
