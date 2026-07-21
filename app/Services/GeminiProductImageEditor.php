<?php

namespace App\Services;

use App\Contracts\ProductImageAiEditor;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Image;
use RuntimeException;

/**
 * Padroniza a foto do produto para o catálogo usando o Gemini (image-to-image).
 *
 * A imagem de origem vai como anexo do prompt — o modelo EDITA a foto real em vez de
 * inventar uma nova, que é o que se espera de uma foto de catálogo de supermercado.
 */
class GeminiProductImageEditor implements ProductImageAiEditor
{
    private const PROMPT = 'Standardize this product photo for e-commerce catalog. Remove/clean background, center the product, keep original colors and realistic details, improve sharpness, no text or watermark.';

    public function __construct(
        protected ProductImageStandardizer $imageStandardizer
    ) {}

    public function process(string $sourcePath, string $targetPath): string
    {
        if ((string) config('ai.providers.gemini.key') === '') {
            throw new RuntimeException('GEMINI_API_KEY nao configurada.');
        }

        if (! Storage::disk('public')->exists($sourcePath)) {
            throw new RuntimeException('Imagem de origem nao encontrada.');
        }

        $response = Image::of(self::PROMPT)
            ->attachments([new StoredImage($sourcePath, 'public')])
            ->square()
            ->timeout(120)
            ->generate(Lab::Gemini);

        $editedBinary = $response->firstImage()->content();

        if ($editedBinary === '') {
            throw new RuntimeException('Nao foi possivel obter o conteudo da imagem processada.');
        }

        // O Gemini devolve a imagem em resolução própria; clampa ao padrão único antes de
        // salvar para não jogar uma imagem grande no canvas do editor.
        Storage::disk('public')->put($targetPath, $this->imageStandardizer->encode($editedBinary));

        return $targetPath;
    }
}
