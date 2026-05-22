<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductDescriptionEmbeddingObserver
{
    public function created(Product $product): void
    {
        $this->generateEmbedding($product);
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged('description') || $product->wasChanged('name')) {
            $this->generateEmbedding($product);
        }
    }

    private function generateEmbedding(Product $product): void
    {
        $text = $this->buildEmbeddingText($product);

        if ($text === '') {
            return;
        }

        try {
            $embedding = Str::of($text)->toEmbeddings();

            // Atualiza sem disparar observers novamente
            Product::withoutEvents(function () use ($product, $embedding): void {
                $product->updateQuietly(['description_embedding' => $embedding]);
            });
        } catch (\Throwable $e) {
            Log::warning('Falha ao gerar embedding para produto', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildEmbeddingText(Product $product): string
    {
        return trim(implode(' ', array_filter([
            $product->name,
            $product->brand,
            $product->description,
            $product->packaging_type,
            $product->measurement_unit,
            $product->packaging_content,
        ])));
    }
}
