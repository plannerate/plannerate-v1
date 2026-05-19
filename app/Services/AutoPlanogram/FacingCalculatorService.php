<?php

namespace App\Services\AutoPlanogram;

use App\Services\AutoPlanogram\DTO\AutoGenerateConfigDTO;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\RankedProductDTO;
use App\Services\AutoPlanogram\DTO\ScoredProduct;  
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
    public function __construct(
        private readonly ProductWidthResolver $widthResolver,
    ) {}

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
     * Escalonamento proporcional de facings por espaço disponível.
     * Desativado: testes mostraram que piora o resultado quando o mix
     * é maior que a gôndola (cenário mais comum). Preservado para
     * uso futuro em cenários de gôndola com folga.
     *
     * @see AutoPlanogramService::generate() — removido do fluxo principal em 2026-05
     *
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return Collection<int, ScoredProduct>
     */
    public function scaleFacings(Collection $scoredProducts, float $availableWidth, int $minFacings = 1): Collection
    {
        [$demandedWidth, $demandedWidthBruta, $invalidCount] = $this->totalDemandedWidth($scoredProducts);

        if ($demandedWidth <= 0.0 || $availableWidth <= 0.0) {
            return $scoredProducts;
        }

        $scaleFactor = $demandedWidth > $availableWidth
            ? $availableWidth / $demandedWidth
            : 1.0;

        Log::info('FacingCalculatorService: escalonamento', [
            'espaco_disponivel_cm' => round($availableWidth, 1),
            'largura_demandada_bruta' => round($demandedWidthBruta, 1),
            'largura_demandada_limpa' => round($demandedWidth, 1),
            'fator_escala' => round($scaleFactor, 4),
            'precisa_escalar' => $scaleFactor < 1.0,
            'produtos_com_width_invalido' => $invalidCount,
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
     * Retorna [largura_limpa, largura_bruta, count_invalidos].
     *
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return array{float, float, int}
     */
    private function totalDemandedWidth(Collection $scoredProducts): array
    {
        $clean = 0.0;
        $bruta = 0.0;
        $invalidCount = 0;

        foreach ($scoredProducts as $scoredProduct) {
            $facing = (int) ($scoredProduct->metadata['facing_ideal'] ?? 1);
            $rawWidth = (float) ($scoredProduct->product->width ?? 0.0);
            $cleanWidth = $this->widthResolver->resolve($scoredProduct->product);

            $bruta += $facing * $rawWidth;
            $clean += $facing * $cleanWidth;

            if ($rawWidth !== $cleanWidth) {
                $invalidCount++;
            }
        }

        return [$clean, $bruta, $invalidCount];
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
