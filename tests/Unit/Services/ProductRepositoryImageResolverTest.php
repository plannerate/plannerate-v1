<?php

use App\Models\Product;
use App\Services\ProductRepositoryImageResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

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
        $webImageUrl => Http::response($webImageBinary, 200),
        '*' => Http::response([], 404),
    ]);

    $service = new ProductRepositoryImageResolver;
    $result = $service->resolveByEan($ean);

    expect($result)->not()->toBeNull();
    expect($result['path'])->toBe($webpPath);
    Storage::disk('public')->assertExists($webpPath);
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
