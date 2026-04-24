<?php

namespace Callcocam\LaravelRaptorPlannerate\Jobs;

use Callcocam\LaravelRaptorPlannerate\Concerns\BelongsToConnection;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Client;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class DOProcessProductImageJob implements ShouldQueue
{
    use BelongsToConnection, Queueable;

    /**
     * Create a new job instance.
     * Usa IDs/database em vez do model Product para evitar ModelNotFoundException
     * na desserialização (a conexão tenant não está configurada quando o worker restaura o job).
     */
    public function __construct(
        public string $productId,
        public ?string $clientId = null,
        public ?string $database = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->database) {
                $this->configureTenantConnection($this->database);
            } elseif ($this->clientId) {
                $client = Client::on(config('database.default'))->find($this->clientId);
                if ($client) {
                    $this->setupClientConnection($client);
                }
            }

            $connection = $this->getClientConnection() ?? 'tenant';
            $product = Product::on($connection)->find($this->productId);

            if (! $product) {
                Log::error('DOProcessProductImageJob: Produto não encontrado', [
                    'product_id' => $this->productId,
                    'connection' => $connection,
                ]);

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

    /**
     * Configura conexão tenant diretamente pelo database
     */
    protected function configureTenantConnection(string $database): void
    {
        $connectionName = 'tenant';
        $tenantConfig = config("database.connections.{$connectionName}");
        $tenantConfig['database'] = $database;
        Config::set("database.connections.{$connectionName}", $tenantConfig);
        DB::purge($connectionName);
        $this->clientConnection = $connectionName;
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
            'client_id' => $this->clientId,
            'database' => $this->database,
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
