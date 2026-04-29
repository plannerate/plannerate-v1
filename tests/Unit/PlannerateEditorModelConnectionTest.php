<?php

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Client;
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

test('plannerate package does not query tenant tables through default or landlord connections', function (): void {
    $sourcePath = base_path('packages/callcocam/laravel-raptor-plannerate/src');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath));
    $violations = [];
    $tenantTables = '(categories|gondolas|gondola_analyses|layers|monthly_sales_summaries|planograms|products|sales|sections|segments|shelves|stores)';

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $contents = (string) file_get_contents($path);

        $patterns = [
            "/DB::connection\\(config\\(['\"]database\\.default['\"]\\)\\)->table\\(['\"]{$tenantTables}['\"]\\)/",
            "/DB::connection\\(config\\(['\"]raptor\\.database\\.landlord_connection_name['\"].*?\\)\\)->table\\(['\"]{$tenantTables}['\"]\\)/s",
            "/DB::table\\(['\"]{$tenantTables}['\"]\\)/",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                break;
            }
        }
    }

    expect($violations)->toBe([]);
});

test('plannerate package wraps tenant writes in tenant connection transactions', function (): void {
    $sourcePath = base_path('packages/callcocam/laravel-raptor-plannerate/src');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath));
    $violations = [];

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $contents = (string) file_get_contents($path);

        if (preg_match('/DB::(beginTransaction|commit|rollBack|transaction)\s*\(/', $contents) === 1) {
            $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($violations)->toBe([]);
});

test('plannerate legacy client model resolves to landlord tenant model', function (): void {
    $client = new Client;

    expect($client)->toBeInstanceOf(Tenant::class)
        ->and($client->getConnectionName())->toBe('landlord');
});
