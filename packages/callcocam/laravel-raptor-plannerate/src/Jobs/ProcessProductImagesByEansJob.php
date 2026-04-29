<?php

namespace Callcocam\LaravelRaptorPlannerate\Jobs;

use Callcocam\LaravelRaptorPlannerate\Concerns\BelongsToConnection;
use Callcocam\LaravelRaptorPlannerate\Events\GondolaProductImagesUpdated;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessProductImagesByEansJob implements ShouldQueue
{
    use BelongsToConnection, Queueable;

    /**
     * Processa imagens dos produtos pelos EANs na conexão tenant.
     * Dispatcha um DOProcessProductImageJob por produto (para não sobrecarregar).
     *
     * @param  array<int, string>  $eans
     */
    public function __construct(
        public array $eans,
        public string $database,
        public string $gondolaId,
        public string $userId,
    ) {}

    public function handle(): void
    {
        if (empty($this->eans)) {
            Log::info('ProcessProductImagesByEansJob: Nenhum EAN para processar.');
            event(new GondolaProductImagesUpdated(
                userId: $this->userId,
                gondolaId: $this->gondolaId,
                processedCount: 0,
            ));

            return;
        }

        $this->setTenantDatabase($this->database);
        $connection = $this->getTenantConnection() ?? $this->tenantConnectionName();

        $products = Product::on($connection)
            ->whereIn('ean', $this->eans)
            ->get(['id', 'ean']);

        if ($products->isEmpty()) {
            event(new GondolaProductImagesUpdated(
                userId: $this->userId,
                gondolaId: $this->gondolaId,
                processedCount: 0,
            ));

            return;
        }

        $jobs = $products
            ->map(fn (Product $product): DOProcessProductImageJob => new DOProcessProductImageJob(
                (string) $product->id,
                null,
                $this->database,
            ))
            ->all();

        $userId = $this->userId;
        $gondolaId = $this->gondolaId;

        Bus::batch($jobs)
            ->name("Atualizar imagens da gôndola {$gondolaId}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($userId, $gondolaId): void {
                event(new GondolaProductImagesUpdated(
                    userId: $userId,
                    gondolaId: $gondolaId,
                    processedCount: $batch->totalJobs,
                ));
            })
            ->dispatch();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessProductImagesByEansJob falhou', [
            'database' => $this->database,
            'eans_count' => count($this->eans),
            'error' => $exception->getMessage(),
        ]);
    }
}
