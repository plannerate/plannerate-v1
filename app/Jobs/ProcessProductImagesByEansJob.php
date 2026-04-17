<?php

namespace App\Jobs;

use App\Concerns\BelongsToConnection;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        public string $database
    ) {}

    public function handle(): void
    {
        if (empty($this->eans)) {
            Log::info('ProcessProductImagesByEansJob: Nenhum EAN para processar.');

            return;
        }

        $this->setTenantDatabase($this->database);
        $connection = $this->getClientConnection() ?? 'tenant';

        $products = Product::on($connection)
            ->whereIn('ean', $this->eans)
            ->get(['id', 'ean']);

        $count = 0;
        foreach ($products as $product) {
            DOProcessProductImageJob::dispatch(
                $product->id,
                null,
                $this->database
            );
            $count++;
        }

        Log::info('ProcessProductImagesByEansJob: jobs disparados', [
            'database' => $this->database,
            'eans_requested' => count($this->eans),
            'products_found' => $products->count(),
            'jobs_dispatched' => $count,
        ]);
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
