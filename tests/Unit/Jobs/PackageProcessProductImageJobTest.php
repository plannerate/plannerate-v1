<?php

use Callcocam\LaravelRaptorPlannerate\Jobs\DOProcessProductImageJob;
use Illuminate\Bus\Batchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('package job processImageFromStorage converte png para webp sem usar api removida', function (): void {
    Storage::fake('public');
    Storage::fake('do');

    $ean = '7891234567890';
    $pngPath = "repositorioimagens/frente/{$ean}.png";
    Storage::disk('do')->put($pngPath, UploadedFile::fake()->image('source.png', 200, 120)->getContent());

    $job = new DOProcessProductImageJob(productId: 'fake-product-id');
    $product = (object) [
        'ean' => $ean,
        'width' => null,
        'height' => null,
    ];

    $result = $job->processImageFromStorage($pngPath, $product);

    expect($result)->toBe("repositorioimagens/frente/{$ean}.webp");
    Storage::disk('public')->assertExists("repositorioimagens/frente/{$ean}.webp");
});

test('package job can be added to a Laravel batch', function (): void {
    expect(class_uses_recursive(DOProcessProductImageJob::class))
        ->toContain(Batchable::class);
});
