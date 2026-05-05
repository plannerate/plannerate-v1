<?php

use App\Models\Product;
use App\Services\ProductRepositoryImageResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $landlordPath = database_path('testing_resolver_unit_landlord.sqlite');
    if (! file_exists($landlordPath)) {
        touch($landlordPath);
    }

    Config::set('database.connections.landlord', [
        'driver' => 'sqlite',
        'database' => $landlordPath,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    DB::purge('landlord');

    Schema::connection('landlord')->dropIfExists('ean_references');
    Schema::connection('landlord')->create('ean_references', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('ean')->unique();
        $table->string('image_front_url')->nullable();
        $table->string('unit')->default('cm');
        $table->boolean('has_dimensions')->default(false);
        $table->string('dimension_status')->default('published');
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });
});

test('service resolves image path for product using existing webp from repository', function (): void {
    Storage::fake('public');
    Storage::fake('do');

    $ean = '7899999999999';
    $expectedPath = "repositorioimagens/frente/{$ean}.webp";
    Storage::disk('do')->put($expectedPath, 'binary-webp-content');

    $product = new Product([
        'ean' => $ean,
    ]);

    $service = new ProductRepositoryImageResolver;

    $result = $service->resolveForProduct($product);

    expect($result)->toBe($expectedPath);
    Storage::disk('public')->assertExists($expectedPath);
});

test('service returns null when repository image is not found', function (): void {
    Storage::fake('public');
    Storage::fake('do');
    Http::fake([
        '*' => Http::response([], 404),
    ]);

    $product = new Product([
        'ean' => '0000000000000',
    ]);

    $service = new ProductRepositoryImageResolver;

    $result = $service->resolveForProduct($product);

    expect($result)->toBeNull();
});

test('service fetches product image from web when repository misses', function (): void {
    Storage::fake('public');
    Storage::fake('do');

    $ean = '7891000000001';
    $webpPath = "repositorioimagens/frente/{$ean}.webp";
    $webImageUrl = 'https://images.openfoodfacts.org/images/products/789/100/000/0001/front_pt.3.400.jpg';
    $webImageBinary = UploadedFile::fake()->image('front.jpg', 320, 320)->getContent();

    Http::fake([
        "https://world.openfoodfacts.org/api/v2/product/{$ean}.json" => Http::response([
            'status' => 1,
            'product' => [
                'image_front_url' => $webImageUrl,
            ],
        ], 200),
        "https://world.openbeautyfacts.org/api/v2/product/{$ean}.json" => Http::response([], 404),
        "https://world.openpetfoodfacts.org/api/v2/product/{$ean}.json" => Http::response([], 404),
        "https://world.openproductsfacts.org/api/v2/product/{$ean}.json" => Http::response([], 404),
        $webImageUrl => Http::response($webImageBinary, 200),
        '*' => Http::response([], 404),
    ]);

    $service = new ProductRepositoryImageResolver;
    $result = $service->resolveByEan($ean);

    expect($result)->not()->toBeNull();
    expect($result['path'])->toBe($webpPath);
    Storage::disk('public')->assertExists($webpPath);
});

test('service resolves image from side angle in repository when front is missing', function (): void {
    Storage::fake('public');
    Storage::fake('do');
    Http::fake([
        '*' => Http::response([], 404),
    ]);

    $ean = '7891222233334';
    $sideWebpPath = "repositorioimagens/lado/{$ean}.webp";
    $targetPath = "repositorioimagens/frente/{$ean}.webp";
    Storage::disk('do')->put($sideWebpPath, 'binary-side-webp-content');

    $service = new ProductRepositoryImageResolver;
    $result = $service->resolveByEan($ean);

    expect($result)->not()->toBeNull();
    expect($result['path'])->toBe($targetPath);
    Storage::disk('public')->assertExists($targetPath);
});

test('service converts png from repository to webp', function (): void {
    Storage::fake('public');
    Storage::fake('do');

    $ean = '7891111111111';
    $pngPath = "repositorioimagens/frente/{$ean}.png";
    $webpPath = "repositorioimagens/frente/{$ean}.webp";
    Storage::disk('do')->put($pngPath, UploadedFile::fake()->image('source.png', 200, 200)->getContent());

    $service = new ProductRepositoryImageResolver;

    $result = $service->resolveByEan($ean);

    expect($result)->not()->toBeNull();
    expect($result['path'])->toBe($webpPath);
    Storage::disk('public')->assertExists($webpPath);
});
