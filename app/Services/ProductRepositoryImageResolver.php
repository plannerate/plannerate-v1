<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class ProductRepositoryImageResolver
{
    /**
     * @var list<string>
     */
    protected array $repositoryAngles = ['frente', 'lado', 'top'];

    /**
     * @var list<string>
     */
    protected array $webCatalogHosts = [
        'https://world.openfoodfacts.org',
        'https://world.openbeautyfacts.org',
        'https://world.openpetfoodfacts.org',
        'https://world.openproductsfacts.org',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $lastResolutionDebug = [];

    /**
     * @return array{path: string, public_url: string}|null
     */
    public function resolveByEan(string $ean, ?float $width = null, ?float $height = null): ?array
    {
        $normalizedEan = trim($ean);
        $this->lastResolutionDebug = [
            'ean' => $normalizedEan,
            'repository_attempts' => [],
            'web_attempts' => [],
            'result' => null,
        ];

        if ($normalizedEan === '') {
            $this->lastResolutionDebug['result'] = 'invalid_ean';

            return null;
        }

        $targetPath = sprintf('repositorioimagens/frente/%s.webp', $normalizedEan);
        $processedPath = $this->resolveFromRepository(
            ean: $normalizedEan,
            targetPath: $targetPath,
            width: $width,
            height: $height,
        );

        if ($processedPath === null) {
            $webFallbackPath = $this->resolveFromWeb(
                ean: $normalizedEan,
                targetPath: $targetPath,
                width: $width,
                height: $height,
            );

            if ($webFallbackPath === null) {
                $this->lastResolutionDebug['result'] = 'not_found';
                $this->logMissingImage($normalizedEan);

                return null;
            }

            $this->lastResolutionDebug['result'] = 'resolved_from_web';

            return [
                'path' => $webFallbackPath,
                'public_url' => Storage::disk('public')->url($webFallbackPath),
            ];
        }

        $this->lastResolutionDebug['result'] = 'resolved_from_repository';

        return [
            'path' => $processedPath,
            'public_url' => Storage::disk('public')->url($processedPath),
        ];
    }

    public function resolveForProduct(Model $product): ?string
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

    protected function resolveFromRepository(
        string $ean,
        string $targetPath,
        ?float $width,
        ?float $height
    ): ?string {
        foreach ($this->repositoryAngles as $angle) {
            $candidateWebpPath = sprintf('repositorioimagens/%s/%s.webp', $angle, $ean);
            if (Storage::disk('do')->exists($candidateWebpPath)) {
                $this->lastResolutionDebug['repository_attempts'][] = [
                    'angle' => $angle,
                    'extension' => 'webp',
                    'path' => $candidateWebpPath,
                    'status' => 'found',
                ];
                $this->copyToPublic($candidateWebpPath, $targetPath);

                return $targetPath;
            }

            $this->lastResolutionDebug['repository_attempts'][] = [
                'angle' => $angle,
                'extension' => 'webp',
                'path' => $candidateWebpPath,
                'status' => 'not_found',
            ];

            $candidatePngPath = sprintf('repositorioimagens/%s/%s.png', $angle, $ean);
            $processedPath = $this->processPngToWebp(
                sourcePath: $candidatePngPath,
                targetPath: $targetPath,
                width: $width,
                height: $height,
                ean: $ean,
            );

            if ($processedPath !== null) {
                $this->lastResolutionDebug['repository_attempts'][] = [
                    'angle' => $angle,
                    'extension' => 'png',
                    'path' => $candidatePngPath,
                    'status' => 'converted',
                ];

                return $processedPath;
            }

            $this->lastResolutionDebug['repository_attempts'][] = [
                'angle' => $angle,
                'extension' => 'png',
                'path' => $candidatePngPath,
                'status' => 'not_found_or_invalid',
            ];
        }

        return null;
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

    protected function resolveFromWeb(
        string $ean,
        string $targetPath,
        ?float $width,
        ?float $height
    ): ?string {
        foreach ($this->webCatalogHosts as $host) {
            try {
                $productResponse = Http::acceptJson()
                    ->timeout(6)
                    ->get(sprintf('%s/api/v2/product/%s.json', $host, $ean));
            } catch (\Throwable) {
                $this->lastResolutionDebug['web_attempts'][] = [
                    'host' => $host,
                    'status' => 'request_failed',
                ];

                continue;
            }

            if (! $productResponse->ok() || (int) data_get($productResponse->json(), 'status') !== 1) {
                $this->lastResolutionDebug['web_attempts'][] = [
                    'host' => $host,
                    'status' => 'product_not_found',
                    'http_status' => $productResponse->status(),
                ];

                continue;
            }

            $imageUrl = $this->extractBestImageUrl($productResponse->json());

            if ($imageUrl === null) {
                $this->lastResolutionDebug['web_attempts'][] = [
                    'host' => $host,
                    'status' => 'no_image_url',
                ];

                continue;
            }

            try {
                $imageResponse = Http::timeout(8)->get($imageUrl);
            } catch (\Throwable) {
                $this->lastResolutionDebug['web_attempts'][] = [
                    'host' => $host,
                    'status' => 'image_download_failed',
                    'image_url' => $imageUrl,
                ];

                continue;
            }

            if (! $imageResponse->ok()) {
                $this->lastResolutionDebug['web_attempts'][] = [
                    'host' => $host,
                    'status' => 'image_http_error',
                    'http_status' => $imageResponse->status(),
                    'image_url' => $imageUrl,
                ];

                continue;
            }

            $binary = $imageResponse->body();

            if (! is_string($binary) || $binary === '') {
                $this->lastResolutionDebug['web_attempts'][] = [
                    'host' => $host,
                    'status' => 'image_empty',
                    'image_url' => $imageUrl,
                ];

                continue;
            }

            $processedPath = $this->processBinaryToWebp(
                imageBinary: $binary,
                targetPath: $targetPath,
                width: $width,
                height: $height,
                ean: $ean,
                sourceReference: $imageUrl,
            );

            if ($processedPath !== null) {
                $this->lastResolutionDebug['web_attempts'][] = [
                    'host' => $host,
                    'status' => 'resolved',
                    'image_url' => $imageUrl,
                ];

                return $processedPath;
            }

            $this->lastResolutionDebug['web_attempts'][] = [
                'host' => $host,
                'status' => 'processing_failed',
                'image_url' => $imageUrl,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastResolutionDebug(): array
    {
        return $this->lastResolutionDebug;
    }

    protected function extractBestImageUrl(array $payload): ?string
    {
        $candidates = collect([
            data_get($payload, 'product.image_front_url'),
            data_get($payload, 'product.image_front_small_url'),
            data_get($payload, 'product.image_url'),
            data_get($payload, 'product.selected_images.front.display.pt'),
            data_get($payload, 'product.selected_images.front.display.en'),
            data_get($payload, 'product.selected_images.front.display.fr'),
            data_get($payload, 'product.image_side_url'),
            data_get($payload, 'product.selected_images.ingredients.display.pt'),
            data_get($payload, 'product.selected_images.ingredients.display.en'),
            data_get($payload, 'product.selected_images.ingredients.display.fr'),
            data_get($payload, 'product.image_ingredients_url'),
            data_get($payload, 'product.image_packaging_url'),
        ]);

        $firstValidUrl = $candidates->first(function (mixed $url): bool {
            return is_string($url) && trim($url) !== '';
        });

        return is_string($firstValidUrl) ? $firstValidUrl : null;
    }

    protected function processBinaryToWebp(
        string $imageBinary,
        string $targetPath,
        ?float $width,
        ?float $height,
        string $ean,
        string $sourceReference
    ): ?string {
        $pixelMultiplier = 7;
        $quality = 90;

        try {
            $image = Image::decodeBinary($imageBinary);

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
            Log::error('Falha ao processar imagem web por EAN', [
                'ean' => $ean,
                'target_path' => $targetPath,
                'source' => $sourceReference,
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
