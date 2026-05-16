<?php

namespace App\Services\AutoPlanogram;

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\RankedProductDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Calcula o número de facings de um produto para o planograma automático.
 *
 * Estratégia:
 * 1. Facing base pela classificação ABC do produto (A=3, B=2, C=1)
 * 2. Ajuste por target_stock (média entre ABC e estoque alvo)
 * 3. Ajuste por vendas relativas com curva suavizada (raiz quadrada)
 * 4. Clamp nos limites configurados [minFacings, maxFacings]
 */
final class FacingCalculatorService
{
    /**
     * Calcula quantos facings um produto deve ter.
     *
     * @param  RankedProductDTO  $product  Produto ranqueado com abcClass, targetStock e salesTotal
     * @param  AutoGenerateConfigDTO  $config  Configurações do planograma (minFacings, maxFacings)
     * @param  float  $maxSales  Maior volume de vendas entre todos os produtos do bloco (normalização)
     */
    public function calculateIdeal(RankedProductDTO $product, AutoGenerateConfigDTO $config, float $maxSales): int
    {
        // 1. Facing base pela classificação ABC
        $baseFacings = match ($product->abcClass) {
            'A' => 3,
            'B' => 2,
            'C' => 1,
            default => 1,
        };

        // 2. Ajuste por target_stock (se disponível)
        $targetFacings = $baseFacings;
        if ($product->targetStock !== null && $product->targetStock > 0) {
            $unitsPerFacing = 5;
            $stockFacings = max(1, (int) ceil($product->targetStock / $unitsPerFacing));
            $targetFacings = (int) ceil(($baseFacings + $stockFacings) / 2);
        }

        // 3. Ajuste por vendas — curva suavizada com raiz quadrada
        if ($maxSales > 0 && $product->salesTotal > 0) {
            $salesFactor = sqrt($product->salesTotal / $maxSales); // 0..1 suavizado
            $facingsRange = $config->maxFacings - $config->minFacings;
            $salesFacings = $config->minFacings + (int) ($salesFactor * $facingsRange);

            // 70% peso no target/ABC + 30% peso nas vendas
            $calculatedFacings = (int) ceil(($targetFacings * 0.7) + ($salesFacings * 0.3));
        } else {
            $calculatedFacings = $targetFacings;
        }

        // 4. Respeitar limites configurados
        return max($config->minFacings, min($calculatedFacings, $config->maxFacings));
    }

    public function calculate(RankedProductDTO $product, AutoGenerateConfigDTO $config, float $maxSales): int
    {
        return $this->calculateIdeal($product, $config, $maxSales);
    }

    /**
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return Collection<int, ScoredProduct>
     */
    public function calculateIdealFacings(Collection $scoredProducts, PlacementSettings $settings): Collection
    {
        if ($scoredProducts->isEmpty()) {
            return collect();
        }

        $config = $settings->toConfigDto();
        $maxSales = (float) ($scoredProducts->max(fn (ScoredProduct $sp): float => (float) ($sp->metadata['sales_total'] ?? $sp->metadata['raw_quantity'] ?? 0.0)) ?? 0.0);

        return $scoredProducts->map(function (ScoredProduct $scoredProduct) use ($config, $maxSales): ScoredProduct {
            $rankedProduct = $this->toRankedProductDto($scoredProduct);
            $facingIdeal = $this->calculateIdeal($rankedProduct, $config, $maxSales);

            return new ScoredProduct(
                productId: $scoredProduct->productId,
                ean: $scoredProduct->ean,
                score: $scoredProduct->score,
                product: $scoredProduct->product,
                metadata: array_merge($scoredProduct->metadata, [
                    'facing_ideal' => $facingIdeal,
                    'facing_final' => $facingIdeal,
                    'estimated_facing' => $facingIdeal,
                ]),
            );
        })->values();
    }

    /**
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return Collection<int, ScoredProduct>
     */
    public function scaleFacings(Collection $scoredProducts, float $availableWidth, int $minFacings = 1): Collection
    {
        $demandedWidth = $this->totalDemandedWidth($scoredProducts);

        if ($demandedWidth <= 0.0 || $availableWidth <= 0.0) {
            return $scoredProducts;
        }

        $scaleFactor = $demandedWidth > $availableWidth
            ? $availableWidth / $demandedWidth
            : 1.0;

        Log::info('FacingCalculatorService: escalonamento', [
            'espaco_disponivel_cm' => round($availableWidth, 1),
            'largura_demandada_cm' => round($demandedWidth, 1),
            'fator_escala' => round($scaleFactor, 4),
            'precisa_escalar' => $scaleFactor < 1.0,
        ]);

        if ($scaleFactor >= 1.0) {
            return $scoredProducts->map(function (ScoredProduct $scoredProduct): ScoredProduct {
                $facingIdeal = (int) ($scoredProduct->metadata['facing_ideal'] ?? 1);

                return new ScoredProduct(
                    productId: $scoredProduct->productId,
                    ean: $scoredProduct->ean,
                    score: $scoredProduct->score,
                    product: $scoredProduct->product,
                    metadata: array_merge($scoredProduct->metadata, [
                        'facing_ideal' => $facingIdeal,
                        'facing_final' => $facingIdeal,
                        'estimated_facing' => $facingIdeal,
                        'scale_factor' => 1.0,
                    ]),
                );
            })->values();
        }

        return $scoredProducts->map(function (ScoredProduct $scoredProduct) use ($scaleFactor, $minFacings): ScoredProduct {
            $facingIdeal = (int) ($scoredProduct->metadata['facing_ideal'] ?? 1);
            $facingFinal = max($minFacings, (int) floor($facingIdeal * $scaleFactor));

            return new ScoredProduct(
                productId: $scoredProduct->productId,
                ean: $scoredProduct->ean,
                score: $scoredProduct->score,
                product: $scoredProduct->product,
                metadata: array_merge($scoredProduct->metadata, [
                    'facing_ideal' => $facingIdeal,
                    'facing_final' => $facingFinal,
                    'estimated_facing' => $facingFinal,
                    'scale_factor' => $scaleFactor,
                ]),
            );
        })->values();
    }

    /**
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     */
    private function totalDemandedWidth(Collection $scoredProducts): float
    {
        return (float) $scoredProducts->sum(function (ScoredProduct $scoredProduct): float {
            $facing = (int) ($scoredProduct->metadata['facing_ideal'] ?? 1);
            $width = (float) ($scoredProduct->product->width ?? 10.0);

            return $facing * $width;
        });
    }

    private function toRankedProductDto(ScoredProduct $scoredProduct): RankedProductDTO
    {
        return new RankedProductDTO(
            product: $scoredProduct->product,
            abcClass: $scoredProduct->metadata['abc_class'] ?? null,
            score: $scoredProduct->score,
            salesTotal: (float) ($scoredProduct->metadata['sales_total'] ?? $scoredProduct->metadata['raw_quantity'] ?? 0),
            margin: (float) ($scoredProduct->metadata['margin'] ?? $scoredProduct->metadata['raw_margem'] ?? 0),
            subcategoryId: $scoredProduct->product->category_id ?? null,
            targetStock: isset($scoredProduct->metadata['target_stock']) ? (float) $scoredProduct->metadata['target_stock'] : null,
            safetyStock: isset($scoredProduct->metadata['safety_stock']) ? (float) $scoredProduct->metadata['safety_stock'] : null,
        );
    }
}
