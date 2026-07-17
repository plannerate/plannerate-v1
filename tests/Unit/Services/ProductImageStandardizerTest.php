<?php

use App\Services\ProductImageStandardizer;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Confirma o "padrão dos tamanhos": toda imagem sai como WebP com o lado maior
 * dentro do teto de config('plannerate.image.max_side') (384 por padrão).
 */
function decodedSize(string $binary): array
{
    $image = Image::decodeBinary($binary);

    return [$image->width(), $image->height()];
}

test('clampa o lado maior ao teto quando a imagem excede', function (): void {
    $source = UploadedFile::fake()->image('big.jpg', 2000, 1000)->getContent();

    $encoded = app(ProductImageStandardizer::class)->encode($source);

    [$width, $height] = decodedSize($encoded);

    expect(max($width, $height))->toBe(384)
        ->and($width)->toBe(384)
        ->and($height)->toBe(192);
});

test('gera WebP válido (assinatura RIFF/WEBP)', function (): void {
    $source = UploadedFile::fake()->image('big.jpg', 2000, 1000)->getContent();

    $encoded = app(ProductImageStandardizer::class)->encode($source);

    expect(substr($encoded, 0, 4))->toBe('RIFF')
        ->and(substr($encoded, 8, 4))->toBe('WEBP');
});

test('usa as dimensões cadastradas (cm × multiplier) e clampa ao teto', function (): void {
    // 100cm × 7 = 700px de lado maior → clampado a 384; 20cm × 7 = 140px.
    $source = UploadedFile::fake()->image('big.jpg', 2000, 1000)->getContent();

    $encoded = app(ProductImageStandardizer::class)->encode($source, widthCm: 100, heightCm: 20);

    [$width, $height] = decodedSize($encoded);

    expect($width)->toBe(384)
        ->and(max($width, $height))->toBe(384);
});

test('não faz upscale de imagem já dentro do teto', function (): void {
    $source = UploadedFile::fake()->image('small.png', 300, 200)->getContent();

    $encoded = app(ProductImageStandardizer::class)->encode($source);

    [$width, $height] = decodedSize($encoded);

    expect($width)->toBe(300)
        ->and($height)->toBe(200);
});
