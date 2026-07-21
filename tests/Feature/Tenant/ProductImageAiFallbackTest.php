<?php

use App\Models\EanReference;
use App\Services\GeminiProductImageEditor;
use App\Services\ProductRepositoryImageResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;

beforeEach(function (): void {
    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Storage::fake('public');
    Storage::fake('do');

    // Sem foto real em lugar nenhum: força o resolver até o último recurso.
    Http::fake(['*' => Http::response(['status' => 0], 404)]);

    config()->set('ai.providers.gemini.key', 'test-key');
    config()->set('services.product_images.ai_fallback', true);
});

/** PNG 1x1 válido — o ProductImageStandardizer precisa decodificar o binário de verdade. */
function fakeGeneratedPng(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
}

test('GeminiProductImageEditor manda a imagem de origem como anexo e salva o resultado', function (): void {
    Image::fake([base64_encode(fakeGeneratedPng())]);
    Storage::disk('public')->put('origem.webp', fakeGeneratedPng());

    $path = app(GeminiProductImageEditor::class)->process('origem.webp', 'destino.webp');

    expect($path)->toBe('destino.webp');
    Storage::disk('public')->assertExists('destino.webp');

    Image::assertGenerated(fn ($prompt): bool => $prompt->contains('Standardize this product photo')
        && $prompt->attachments->count() === 1);
});

test('GeminiProductImageEditor falha sem a GEMINI_API_KEY configurada', function (): void {
    Image::fake();
    config()->set('ai.providers.gemini.key', '');
    Storage::disk('public')->put('origem.webp', fakeGeneratedPng());

    expect(fn (): string => app(GeminiProductImageEditor::class)->process('origem.webp', 'destino.webp'))
        ->toThrow(RuntimeException::class, 'GEMINI_API_KEY nao configurada.');

    Image::assertNothingGenerated();
});

test('resolver gera imagem por IA como último recurso e marca o caminho como sintético', function (): void {
    Image::fake([base64_encode(fakeGeneratedPng())]);

    $result = app(ProductRepositoryImageResolver::class)
        ->resolveByEan('7891234567895', description: 'Refrigerante Cola 2L PET');

    expect($result['path'])->toBe('repositorioimages/ia/7891234567895.webp');
    Storage::disk('public')->assertExists('repositorioimages/ia/7891234567895.webp');

    // Fica cacheado no landlord como qualquer outra origem, mas sob o prefixo auditável.
    expect(EanReference::where('ean', '7891234567895')->value('image_front_url'))
        ->toBe('repositorioimages/ia/7891234567895.webp');

    Image::assertGenerated(fn ($prompt): bool => $prompt->contains('Refrigerante Cola 2L PET'));
});

test('resolver não chama a IA quando não há descrição do produto', function (): void {
    Image::fake();

    $result = app(ProductRepositoryImageResolver::class)->resolveByEan('7891234567895');

    expect($result)->toBeNull();
    Image::assertNothingGenerated();
});

test('resolver não chama a IA quando o fallback está desligado', function (): void {
    Image::fake();
    config()->set('services.product_images.ai_fallback', false);

    $result = app(ProductRepositoryImageResolver::class)
        ->resolveByEan('7891234567895', description: 'Refrigerante Cola 2L PET');

    expect($result)->toBeNull();
    Image::assertNothingGenerated();
});
