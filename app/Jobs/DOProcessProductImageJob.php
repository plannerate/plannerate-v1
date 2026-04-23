<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductRepositoryImageResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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
    public function handle(ProductRepositoryImageResolver $repositoryImageResolver): void
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

            $product->url = $repositoryImageResolver->resolveForProduct($product);
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
