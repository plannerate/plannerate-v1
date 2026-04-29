<?php

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\MercadologicoReorganizeLog;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Sale;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Store;
use Tests\TestCase;

uses(TestCase::class);

test('plannerate editor tenant models use configured tenant connection', function (string $modelClass): void {
    config(['multitenancy.tenant_database_connection_name' => 'tenant']);

    expect((new $modelClass)->getConnectionName())->toBe('tenant');
})->with([
    Category::class,
    Gondola::class,
    GondolaAnalysis::class,
    Layer::class,
    MercadologicoReorganizeLog::class,
    MonthlySalesSummary::class,
    Planogram::class,
    Product::class,
    Sale::class,
    Section::class,
    Segment::class,
    Shelf::class,
    Store::class,
]);
