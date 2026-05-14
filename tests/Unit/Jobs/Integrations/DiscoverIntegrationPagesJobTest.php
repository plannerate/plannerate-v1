<?php

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
