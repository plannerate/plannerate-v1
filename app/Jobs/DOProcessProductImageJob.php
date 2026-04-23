<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Spatie\Multitenancy\Jobs\TenantAware;

class DOProcessProductImageJob implements ShouldQueue, TenantAware
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $productId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $product = Product::query()->find($this->productId);

            if (! $product) {
                Log::error('DOProcessProductImageJob: Produto não encontrado', [
                    'product_id' => $this->productId,
                ]);

                return;
            }

            if (! $product->ean) {
                return;
            }

            $webpPath = sprintf('repositorioimagens/frente/%s.webp', $product->ean);
            if (Storage::disk('public')->exists($webpPath)) {
                $product->url = $webpPath;
                $product->save();

                return;
            }

            $product->url = $this->processImageFromStorage("repositorioimagens/frente/{$product->ean}.png", $product);
            $product->save();
        } catch (\Exception $e) {
            Log::error('DOProcessProductImageJob: Erro ao processar', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function processImageFromStorage($storagePath, $product)
    {
        // Fator para converter as dimensões do produto (ex: cm) em pixels para exibição.
        // Aumentado para um resultado visual maior. Ajuste se necessário.
        $pixelMultiplier = 7;
        // Qualidade da imagem WebP (0 a 100). 90 é alta qualidade.
        $quality = 90;

        try {
            // Dimensões agora estão diretamente no produto (tabela dimensions foi removida)
            $width = $product->width;
            $height = $product->height;

            $imageFile = Storage::disk('do')->get($storagePath);

            $url = Storage::disk('do')->url($storagePath);
            if (! $imageFile) {
                // Log::warning("Imagem não encontrada para EAN {$product->ean} no caminho {$url}");

                // Registra EAN no relatório de imagens não encontradas
                $this->logMissingImage($product->ean);

                return null;
            }
            // Log::info("Processando imagem para EAN {$product->ean} a partir de {$url}");
            $image = Image::decodeBinary($imageFile);

            if (! is_numeric($width) || $width <= 0) {
                $width = $image->width() / $pixelMultiplier; // Largura padrão em cm
            }
            if (! is_numeric($height) || $height <= 0) {
                $height = $image->height() / $pixelMultiplier; // Altura padrão em cm
            }
            // Calcula o tamanho que a imagem deve ter.
            $targetWidth = (int) ($width * $pixelMultiplier);
            $targetHeight = (int) ($height * $pixelMultiplier);

            // Usa resize(): Força a imagem a ter o tamanho alvo, garantindo a proporção visual.
            $image->resize($targetWidth, $targetHeight);

            // Codifica para WebP com alta qualidade
            $encodedImage = $image->encode(new WebpEncoder($quality));

            $newFileName = sprintf('%s.webp', $product->ean);
            $newPath = sprintf('repositorioimagens/frente/%s', $newFileName);

            Storage::disk('public')->put($newPath, (string) $encodedImage);

            $url = Storage::disk('public')->url($newPath);
            // Log::info("Imagem processada para EAN {$product->ean}, salva em {$url}");

            return $newPath;
        } catch (\Exception $e) {
            Log::error("Falha ao processar imagem para EAN {$product->ean}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Registra EAN de imagem não encontrada em arquivo de relatório
     */
    protected function logMissingImage(string $ean): void
    {
        $reportPath = 'reports/missing-images.txt';
        $timestamp = now()->toDateTimeString();
        $entry = "{$timestamp} - EAN: {$ean}\n";

        Storage::disk('local')->append($reportPath, $entry);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DOProcessProductImageJob falhou', [
            'product_id' => $this->productId,
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
