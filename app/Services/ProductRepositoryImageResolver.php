<?php

namespace App\Services;

use App\Models\EanReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Image;

class ProductRepositoryImageResolver
{
    public function __construct(
        protected ProductImageStandardizer $imageStandardizer
    ) {}

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
     * @param  string|null  $description  Nome/descrição do produto. Sem ela a geração por IA é
     *                                    pulada: o EAN sozinho não diz ao modelo o que desenhar.
     * @return array{path: string, public_url: string}|null
     */
    public function resolveByEan(string $ean, ?float $width = null, ?float $height = null, bool $force = false, ?string $description = null): ?array
    {
        $normalizedEan = EanReference::normalizeEan($ean);
        $this->lastResolutionDebug = [
            'ean' => $normalizedEan,
            'repository_attempts' => [],
            'web_attempts' => [],
            'ai_attempt' => null,
            'result' => null,
        ];

        if ($normalizedEan === '') {
            $this->lastResolutionDebug['result'] = 'invalid_ean';

            return null;
        }

        if (! $force) {
            // Prioridade 1: cache global no landlord (zero I/O remoto)
            $cachedPath = $this->resolveFromEanReference($normalizedEan);
            if ($cachedPath !== null) {
                $this->lastResolutionDebug['result'] = 'resolved_from_ean_reference';

                return [
                    'path' => $cachedPath,
                    'public_url' => Storage::disk('public')->url($cachedPath),
                ];
            }
        }

        $targetPath = sprintf('repositorioimages/frente/%s.webp', $normalizedEan);

        if (! $force) {
            // Prioridade 2: arquivo já existe no disco público local
            if (Storage::disk('public')->exists($targetPath)) {
                $this->lastResolutionDebug['result'] = 'resolved_from_public_disk';
                $this->saveToEanReference($normalizedEan, $targetPath);

                return [
                    'path' => $targetPath,
                    'public_url' => Storage::disk('public')->url($targetPath),
                ];
            }
        }

        // Prioridade 3+4: DigitalOcean Spaces (webp → copia; png → converte)
        $processedPath = $this->resolveFromRepository(
            ean: $normalizedEan,
            targetPath: $targetPath,
            width: $width,
            height: $height,
        );

        if ($processedPath !== null) {
            $this->lastResolutionDebug['result'] = 'resolved_from_repository';
            $this->saveToEanReference($normalizedEan, $processedPath);

            return [
                'path' => $processedPath,
                'public_url' => Storage::disk('public')->url($processedPath),
            ];
        }

        // Prioridade 5: web (OpenFoodFacts e similares)
        $webFallbackPath = $this->resolveFromWeb(
            ean: $normalizedEan,
            targetPath: $targetPath,
            width: $width,
            height: $height,
        );

        if ($webFallbackPath !== null) {
            $this->lastResolutionDebug['result'] = 'resolved_from_web';
            $this->saveToEanReference($normalizedEan, $webFallbackPath);

            return [
                'path' => $webFallbackPath,
                'public_url' => Storage::disk('public')->url($webFallbackPath),
            ];
        }

        // Prioridade 6: último recurso — IA gera a arte a partir da descrição do produto.
        $aiPath = $this->resolveFromAi(
            ean: $normalizedEan,
            description: $description,
        );

        if ($aiPath !== null) {
            $this->lastResolutionDebug['result'] = 'resolved_from_ai';
            $this->saveToEanReference($normalizedEan, $aiPath);

            return [
                'path' => $aiPath,
                'public_url' => Storage::disk('public')->url($aiPath),
            ];
        }

        $this->lastResolutionDebug['result'] = 'not_found';
        $this->logMissingImage($normalizedEan);

        return null;
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
            description: is_string($product->name) ? $product->name : null,
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
        try {
            Storage::disk('do')->exists('__probe__');
        } catch (\Throwable $e) {
            Log::warning('DOStorage indisponível; pulando resolução por repositório', [
                'ean' => $ean,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

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
        try {
            $imageFile = Storage::disk('do')->get($sourcePath);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($imageFile) || $imageFile === '') {
            return null;
        }

        try {
            // Dimensionamento (teto/qualidade) centralizado no padrão único.
            $encodedImage = $this->imageStandardizer->encode($imageFile, $width, $height);

            Storage::disk('public')->put($targetPath, $encodedImage);

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
     * Último recurso: nenhuma foto real do produto existe: pede uma ao Gemini.
     *
     * A arte gerada NÃO é a embalagem real — vai para `repositorioimages/ia/`, e não para
     * `repositorioimages/frente/`, justamente para dar para auditar e limpar depois com um
     * `where image_front_url like 'repositorioimages/ia/%'`.
     *
     * Devolve null (sem gerar nada) quando o fallback está desligado ou quando não há
     * descrição: com o EAN sozinho o modelo desenharia um produto qualquer.
     */
    protected function resolveFromAi(string $ean, ?string $description): ?string
    {
        if (! config('services.product_images.ai_fallback')) {
            $this->lastResolutionDebug['ai_attempt'] = ['status' => 'disabled'];

            return null;
        }

        $description = trim((string) $description);

        if ($description === '') {
            $this->lastResolutionDebug['ai_attempt'] = ['status' => 'skipped_no_description'];

            return null;
        }

        $targetPath = sprintf('repositorioimages/ia/%s.webp', $ean);

        try {
            $response = Image::of(sprintf(
                'Product packaging photo for a supermarket catalog: %s. Front view, centered, '.
                'plain white background, studio lighting, photorealistic, no text overlay, no watermark.',
                $description,
            ))->square()->timeout(120)->generate(Lab::Gemini);

            $binary = $response->firstImage()->content();

            if ($binary === '') {
                $this->lastResolutionDebug['ai_attempt'] = ['status' => 'empty_response'];

                return null;
            }

            Storage::disk('public')->put($targetPath, $this->imageStandardizer->encode($binary));
        } catch (\Throwable $e) {
            Log::warning('ProductRepositoryImageResolver: falha ao gerar imagem com IA', [
                'ean' => $ean,
                'description' => $description,
                'error' => $e->getMessage(),
            ]);

            $this->lastResolutionDebug['ai_attempt'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];

            return null;
        }

        $this->lastResolutionDebug['ai_attempt'] = [
            'status' => 'generated',
            'path' => $targetPath,
        ];

        return $targetPath;
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
        try {
            // Dimensionamento (teto/qualidade) centralizado no padrão único.
            $encodedImage = $this->imageStandardizer->encode($imageBinary, $width, $height);

            Storage::disk('public')->put($targetPath, $encodedImage);

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

    private function resolveFromEanReference(string $normalizedEan): ?string
    {
        $path = DB::connection('landlord')
            ->table('ean_references')
            ->whereNull('deleted_at')
            ->where('ean', $normalizedEan)
            ->value('image_front_url');

        return is_string($path) && $path !== '' ? $path : null;
    }

    protected function saveToEanReference(string $normalizedEan, string $path): void
    {
        EanReference::updateOrCreate(
            ['ean' => $normalizedEan],
            ['image_front_url' => $path]
        );
    }

    protected function logMissingImage(string $ean): void
    {
        $reportPath = 'reports/missing-images.txt';
        $timestamp = now()->toDateTimeString();
        $entry = "{$timestamp} - EAN: {$ean}\n";

        Storage::disk('local')->append($reportPath, $entry);
    }
}
