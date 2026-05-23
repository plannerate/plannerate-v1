<?php

namespace App\Jobs;

use App\Ai\Agents\ProductDimensionResearcher;
use App\Enums\DimensionStatus;
use App\Events\ProductDimensionResearched;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

class ResearchProductDimensionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 120;

    public function __construct(
        public readonly string $productId,
        public readonly string $tenantId,
    ) {
        $this->onQueue('ai-research');
    }

    public function handle(): void
    {
        // TTL de 150s garante que a lock sobrevive à chamada do Gemini mesmo se o job travar
        $lock = Cache::lock('gemini-rate-limit', 150);
        if (! $lock->get()) {
            $this->release(60);

            return;
        }

        try {
            $product = Product::query()->whereKey($this->productId)->first();

            if (! $product instanceof Product) {
                return;
            }

            $product->update(['dimension_status' => DimensionStatus::Researching]);

            $prompt = $this->buildPrompt($product);
            $rawResponse = (new ProductDimensionResearcher($product))->prompt($prompt);
            $response = $this->parseJsonResponse($rawResponse->text);

            if (! ($response['found'] ?? false)) {
                $product->update([
                    'dimension_status' => DimensionStatus::NotFound,
                    'dimension_researched_at' => now(),
                ]);
                event(new ProductDimensionResearched($product, $this->tenantId));

                return;
            }

            $this->applyIfPassesSanityChecks($product, $response);
            event(new ProductDimensionResearched($product, $this->tenantId));
        } finally {
            $lock->release();
        }
    }

    public function tags(): array
    {
        return ['ai-research', 'dimensions', "tenant:{$this->tenantId}"];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ResearchProductDimensionsJob falhou', [
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
        ]);

        Product::query()->whereKey($this->productId)->update([
            'dimension_status' => DimensionStatus::Pending,
        ]);
    }

    /**
     * Extrai o objeto JSON da resposta em texto do modelo.
     * Remove blocos markdown (```json ... ```) se presentes e faz decode seguro.
     *
     * @return array<string, mixed>
     */
    private function parseJsonResponse(string $text): array
    {
        $text = trim($text);

        // Remove blocos markdown: ```json ... ``` ou ``` ... ```
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/i', '', $text) ?? $text;
        $text = trim($text);

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            Log::warning('ResearchProductDimensionsJob: JSON inválido na resposta do modelo', [
                'product_id' => $this->productId,
                'raw' => mb_substr($text, 0, 300),
            ]);

            return ['found' => false, 'source' => 'not_found', 'confidence' => 'low'];
        }

        return $decoded;
    }

    private function buildPrompt(Product $product): string
    {
        $templatePath = base_path('resources/ai/user-prompt-template.txt');
        $template = file_exists($templatePath) ? file_get_contents($templatePath) : '';

        $vars = [
            '{ean}' => (string) ($product->ean ?? 'não informado'),
            '{description}' => (string) ($product->name ?? ''),
            '{brand}' => (string) ($product->brand ?? 'não informada'),
            '{category_id}' => (string) ($product->category_id ?? ''),
            '{category_path}' => (string) ($product->category?->name ?? 'não informada'),
            '{net_content}' => (string) ($product->packaging_content ?? ''),
            '{measurement_unit}' => (string) ($product->measurement_unit ?? ''),
            '{packaging_type}' => (string) ($product->packaging_type ?? 'não informado'),
            '{tenant_name}' => (string) (Tenant::current()?->name ?? ''),
        ];

        return $template !== '' ? strtr($template, $vars) : $this->defaultPrompt($product);
    }

    private function defaultPrompt(Product $product): string
    {
        return sprintf(
            'Pesquise as dimensões físicas da embalagem primária deste produto: EAN: %s | Produto: %s | Marca: %s | Embalagem: %s %s',
            $product->ean ?? 'não informado',
            $product->name ?? '',
            $product->brand ?? '',
            $product->packaging_content ?? '',
            $product->measurement_unit ?? '',
        );
    }

    /** @param array<string, mixed> $response */
    private function applyIfPassesSanityChecks(Product $product, array $response): void
    {
        $warnings = (array) ($response['warnings'] ?? []);

        $width = isset($response['width']) ? (float) $response['width'] : null;
        $height = isset($response['height']) ? (float) $response['height'] : null;
        $depth = isset($response['depth']) ? (float) $response['depth'] : null;
        $weight = isset($response['weight']) ? (float) $response['weight'] : null;
        $confidence = (string) ($response['confidence'] ?? 'low');

        // Sanity checks
        foreach ([$width, $height, $depth] as $dim) {
            if ($dim !== null && ($dim < 1 || $dim > 100)) {
                $warnings[] = "Dimensão fora do intervalo esperado para produto de varejo: {$dim}cm";
                $confidence = 'low';
            }
        }

        if ($weight !== null && $product->net_content !== null) {
            $netContentGrams = (float) $product->net_content;
            if ($netContentGrams > 0 && $weight < $netContentGrams) {
                $warnings[] = "Peso ({$weight}g) menor que conteúdo líquido declarado ({$netContentGrams}g)";
                $confidence = 'low';
            }
        }

        // source=local_similarity não pode ter confidence=high
        if (($response['source'] ?? '') === 'local_similarity' && $confidence === 'high') {
            $confidence = 'medium';
        }

        $updates = [
            'dimension_status' => DimensionStatus::AwaitingApproval,
            'dimension_source' => $response['source'] ?? null,
            'dimension_source_url' => $response['source_url'] ?? null,
            'dimension_confidence' => $confidence,
            'dimension_reasoning' => $response['reasoning'] ?? null,
            'dimension_warnings' => $warnings ?: null,
            'dimension_researched_at' => now(),
            'similar_to_product_id' => $response['similar_product_id'] ?? null,
        ];

        if ($width !== null) {
            $updates['width'] = $width;
        }
        if ($height !== null) {
            $updates['height'] = $height;
        }
        if ($depth !== null) {
            $updates['depth'] = $depth;
        }
        if ($weight !== null) {
            $updates['weight'] = $weight;
        }
        if ($width && $height && $depth) {
            $updates['has_dimensions'] = true;
        }

        $product->update($updates);
    }
}
