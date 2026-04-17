<?php

use Callcocam\LaravelRaptor\Services\NavigationService;
use Tests\TestCase;

uses(TestCase::class);

it('applies block ordering metadata for direct items and groups', function () {
    $service = new NavigationService;

    $navigationItems = [
        [
            'title' => 'Analytics',
            'label' => 'Analytics',
            'href' => '/analytics',
            'routeName' => 'analytics.index',
            'order' => 30,
            'group' => 'Analytics',
            'icon' => 'BarChart3',
        ],
        [
            'title' => 'Store',
            'label' => 'Stores',
            'href' => '/stores',
            'routeName' => 'stores.index',
            'order' => 10,
            'group' => null,
            'icon' => 'Store',
        ],
        [
            'title' => 'Client',
            'label' => 'Clients',
            'href' => '/clients',
            'routeName' => 'clients.index',
            'order' => 20,
            'group' => 'Operacional',
            'icon' => 'Building',
        ],
        [
            'title' => 'Cluster',
            'label' => 'Clusters',
            'href' => '/clusters',
            'routeName' => 'clusters.index',
            'order' => 22,
            'group' => 'Operacional',
            'icon' => 'Grid',
        ],
        [
            'title' => 'Planograms',
            'label' => 'Planograms',
            'href' => '/planograms',
            'routeName' => 'planograms.index',
            'order' => 100,
            'group' => null,
            'icon' => 'Layout',
        ],
    ];

    $reflection = new ReflectionClass($service);

    $applyGroupIconFallback = $reflection->getMethod('applyGroupIconFallback');
    $applyGroupIconFallback->setAccessible(true);
    $navigationItems = $applyGroupIconFallback->invoke($service, $navigationItems);

    $applyBlockOrdering = $reflection->getMethod('applyBlockOrdering');
    $applyBlockOrdering->setAccessible(true);
    $navigationItems = $applyBlockOrdering->invoke($service, $navigationItems);

    expect($navigationItems[0]['groupKey'])->toBe('Analytics')
        ->and($navigationItems[0]['blockOrder'])->toBe(30)
        ->and($navigationItems[0]['isDirect'])->toBeFalse();

    expect($navigationItems[1]['groupKey'])->toBe('direct:stores.index')
        ->and($navigationItems[1]['blockOrder'])->toBe(10)
        ->and($navigationItems[1]['isDirect'])->toBeTrue();

    expect($navigationItems[2]['groupKey'])->toBe('Operacional')
        ->and($navigationItems[2]['blockOrder'])->toBe(20)
        ->and($navigationItems[2]['isDirect'])->toBeFalse();

    expect($navigationItems[3]['groupKey'])->toBe('Operacional')
        ->and($navigationItems[3]['blockOrder'])->toBe(20)
        ->and($navigationItems[3]['isDirect'])->toBeFalse();

    expect($navigationItems[4]['groupKey'])->toBe('direct:planograms.index')
        ->and($navigationItems[4]['blockOrder'])->toBe(100)
        ->and($navigationItems[4]['isDirect'])->toBeTrue();
});

it('calculates grouped blockOrder as the minimum order from the group', function () {
    $service = new NavigationService;

    $navigationItems = [
        [
            'title' => 'Item A',
            'label' => 'Item A',
            'href' => '/a',
            'routeName' => 'a.index',
            'order' => 100,
            'group' => 'Grupo 1',
            'icon' => 'A',
        ],
        [
            'title' => 'Item B',
            'label' => 'Item B',
            'href' => '/b',
            'routeName' => 'b.index',
            'order' => 50,
            'group' => 'Grupo 1',
            'icon' => 'B',
        ],
        [
            'title' => 'Item C',
            'label' => 'Item C',
            'href' => '/c',
            'routeName' => 'c.index',
            'order' => 75,
            'group' => 'Grupo 1',
            'icon' => 'C',
        ],
    ];

    $reflection = new ReflectionClass($service);
    $applyBlockOrdering = $reflection->getMethod('applyBlockOrdering');
    $applyBlockOrdering->setAccessible(true);
    $navigationItems = $applyBlockOrdering->invoke($service, $navigationItems);

    foreach ($navigationItems as $item) {
        expect($item['blockOrder'])->toBe(50)
            ->and($item['groupKey'])->toBe('Grupo 1')
            ->and($item['isDirect'])->toBeFalse();
    }
});
