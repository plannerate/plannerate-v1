<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class ProductRepositoryImageResolver
{
    /**
     * @return array{path: string, public_url: string}|null
     */
    public function resolveByEan(string $ean, ?float $width = null, ?float $height = null): ?array
    {
        $normalizedEan = trim($ean);

        if ($normalizedEan === '') {
            return null;
        }

        $webpPath = sprintf('repositorioimagens/frente/%s.webp', $normalizedEan);
        if (Storage::disk('do')->exists($webpPath)) {
            $this->copyToPublic($webpPath, $webpPath);

            return [
                'path' => $webpPath,
                'public_url' => Storage::disk('public')->url($webpPath),
            ];
        }

        $pngPath = sprintf('repositorioimagens/frente/%s.png', $normalizedEan);
        $processedPath = $this->processPngToWebp(
            sourcePath: $pngPath,
            targetPath: $webpPath,
            width: $width,
            height: $height,
            ean: $normalizedEan,
        );

        if ($processedPath === null) {
            $this->logMissingImage($normalizedEan);

            return null;
        }

        return [
            'path' => $processedPath,
            'public_url' => Storage::disk('public')->url($processedPath),
        ];
    }

    public function resolveForProduct(Product $product): ?string
    {
        if (! $product->ean) {
            return null;
        }

        $width = is_numeric($product->width) ? (float) $product->width : null;
        $height = is_numeric($product->height) ? (float) $product->height : null;

        $result = $this->resolveByEan(
            ean: (string) $product->ean,
            width: $width,
            height: $height,
        );

        return $result['path'] ?? null;
    }

    protected function copyToPublic(string $sourcePath, string $targetPath): void
    {
        $binary = Storage::disk('do')->get($sourcePath);
        Storage::disk('public')->put($targetPath, $binary);
    }

    protected function processPngToWebp(
        string $sourcePath,
        string $targetPath,
        ?float $width,
        ?float $height,
        string $ean
    ): ?string {
        // Fator para converter dimensoes de produto (cm) para pixels.
        $pixelMultiplier = 7;
        $quality = 90;

        try {
            $imageFile = Storage::disk('do')->get($sourcePath);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($imageFile) || $imageFile === '') {
            return null;
        }

        try {
            $image = Image::decodeBinary($imageFile);

            $resolvedWidth = $width;
            $resolvedHeight = $height;

            if (! is_numeric($resolvedWidth) || $resolvedWidth <= 0) {
                $resolvedWidth = $image->width() / $pixelMultiplier;
            }

            if (! is_numeric($resolvedHeight) || $resolvedHeight <= 0) {
                $resolvedHeight = $image->height() / $pixelMultiplier;
            }

            $targetWidth = (int) ($resolvedWidth * $pixelMultiplier);
            $targetHeight = (int) ($resolvedHeight * $pixelMultiplier);

            $image->resize($targetWidth, $targetHeight);
            $encodedImage = $image->encode(new WebpEncoder($quality));

            Storage::disk('public')->put($targetPath, (string) $encodedImage);

            return $targetPath;
        } catch (\Throwable $exception) {
            Log::error('Falha ao processar imagem de repositorio por EAN', [
                'ean' => $ean,
                'source_path' => $sourcePath,
                'target_path' => $targetPath,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function logMissingImage(string $ean): void
    {
        $reportPath = 'reports/missing-images.txt';
        $timestamp = now()->toDateTimeString();
        $entry = "{$timestamp} - EAN: {$ean}\n";

        Storage::disk('local')->append($reportPath, $entry);
    }
}
