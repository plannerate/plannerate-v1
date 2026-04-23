<?php

namespace App\Jobs;

use App\Contracts\ProductImageAiEditor;
use App\Models\ProductImageAiOperation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\TenantAware;

class ProcessProductImageWithAiJob implements ShouldQueue, TenantAware
{
    use Queueable;

    public int $timeout = 150;

    public int $tries = 1;

    public function __construct(
        public string $operationId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ProductImageAiEditor $editor): void
    {
        $operation = ProductImageAiOperation::query()->find($this->operationId);

        if (! $operation) {
            Log::warning('ProcessProductImageWithAiJob: operacao nao encontrada.', [
                'operation_id' => $this->operationId,
            ]);

            return;
        }

        try {
            $targetPath = sprintf(
                'products/processed/%s/%s.webp',
                $operation->tenant_id,
                $operation->id,
            );
            $outputPath = $editor->process($operation->source_path, $targetPath);

            $operation->update([
                'status' => 'completed',
                'output_path' => $outputPath,
                'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            $operation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            Log::error('ProcessProductImageWithAiJob: erro ao processar.', [
                'operation_id' => $this->operationId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
