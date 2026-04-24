<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\RankedProductDTO;

/**
 * Service de Regras de Merchandising
 *
 * Responsável por:
 * 1. Definir prateleira ideal para cada produto (baseado em ABC)
 * 2. Calcular número de facings (baseado em vendas)
 * 3. Aplicar regras de posicionamento (produtos A nas prateleiras mais altas)
 */
class MerchandisingRulesService
{
    /**
     * Determinar índice de prateleira ideal para um produto
     *
     * Regra: Produtos A nas prateleiras superiores, B no meio, C embaixo
     *
     * MELHORIA V2: Usa RANGE ao invés de ponto fixo
     * - Produtos A: 60-90% da altura (distribui entre prateleiras superiores)
     * - Produtos B: 30-60% da altura (distribui entre prateleiras médias)
     * - Produtos C: 0-30% da altura (distribui entre prateleiras inferiores)
     *
     * Dentro do range, usa score para decidir posição exata
     * (score maior = posição mais alta dentro do range)
     *
     * @param  int  $totalShelves  Número total de prateleiras disponíveis
     * @param  float  $scoreRatio  Posição relativa do produto (0-1, maior = mais importante)
     * @return int Índice da prateleira (0 = mais baixa, N = mais alta)
     */
    public function determineShelfIndex(
        RankedProductDTO $product,
        int $totalShelves,
        float $scoreRatio = 0.5
    ): int {
        if ($totalShelves <= 0) {
            return 0;
        }

        // Definir range de prateleiras por ABC (% da altura)
        [$minPercent, $maxPercent] = match ($product->abcClass) {
            'A' => [0.60, 0.90], // Prateleiras superiores (60-90%)
            'B' => [0.30, 0.60], // Prateleiras médias (30-60%)
            'C' => [0.05, 0.30], // Prateleiras inferiores (5-30%)
            default => [0.0, 0.20], // Sem classificação = mais baixo (0-20%)
        };

        // Calcular índices mín/máx das prateleiras nesse range
        $minIndex = (int) floor($totalShelves * $minPercent);
        $maxIndex = (int) floor($totalShelves * $maxPercent);

        // Garantir que está dentro dos limites
        $minIndex = max(0, min($minIndex, $totalShelves - 1));
        $maxIndex = max(0, min($maxIndex, $totalShelves - 1));

        // Distribuir dentro do range usando scoreRatio
        // scoreRatio alto = índice mais alto dentro do range
        $rangeSize = $maxIndex - $minIndex;
        if ($rangeSize > 0) {
            $shelfIndex = $minIndex + (int) round($scoreRatio * $rangeSize);
        } else {
            $shelfIndex = $minIndex;
        }

        return max(0, min($shelfIndex, $totalShelves - 1));
    }

    /**
     * Calcular número de facings para um produto
     *
     * Baseado em:
     * - Classificação ABC (produtos A = mais facings base)
     * - Target Stock da análise (estoque ideal influencia)
     * - Vendas proporcionais (distribuição não-linear)
     * - Limites configurados (min/max)
     *
     * Algoritmo:
     * 1. Define facing base por ABC (A=3, B=2, C=1)
     * 2. Ajusta por target_stock se disponível
     * 3. Aplica fator de vendas com curva suavizada (raiz quadrada)
     * 4. Respeita limites configurados
     *
     * @return int Número de facings (1-20)
     */
    public function calculateFacings(
        RankedProductDTO $product,
        AutoGenerateConfigDTO $config,
        float $maxSales
    ): int {
        // 1. Facing base por classificação ABC
        $baseFacings = match ($product->abcClass) {
            'A' => 3, // Produtos A têm mais destaque
            'B' => 2, // Produtos B têm destaque médio
            'C' => 1, // Produtos C têm destaque mínimo
            default => 1, // Sem classificação = mínimo
        };

        // 2. Ajuste por target_stock (se disponível)
        $targetFacings = $baseFacings;
        if ($product->targetStock !== null && $product->targetStock > 0) {
            // Assumir que cada facing representa ~5 unidades (configurável)
            $unitsPerFacing = 5;
            $targetFacings = max(1, (int) ceil($product->targetStock / $unitsPerFacing));

            // Usar a média entre base ABC e target_stock (não sobrescrever completamente)
            $targetFacings = (int) ceil(($baseFacings + $targetFacings) / 2);
        }

        // 3. Ajuste por vendas (curva suavizada)
        if ($maxSales > 0 && $product->salesTotal > 0) {
            // Usar raiz quadrada para suavizar a curva
            // Produtos com 100% das vendas não ficam com 10x mais facings
            $salesRatio = $product->salesTotal / $maxSales;
            $salesFactor = sqrt($salesRatio); // 0-1 suavizado

            $facingsRange = $config->maxFacings - $config->minFacings;
            $salesFacings = $config->minFacings + (int) ($salesFactor * $facingsRange);

            // Combinar: 70% target/ABC + 30% vendas
            $calculatedFacings = (int) ceil(($targetFacings * 0.7) + ($salesFacings * 0.3));
        } else {
            $calculatedFacings = $targetFacings;
        }

        // 4. Garantir limites configurados
        return max($config->minFacings, min($calculatedFacings, $config->maxFacings));
    }

    /**
     * Agrupar produtos por subcategoria (se configurado)
     *
     * @param  RankedProductDTO[]  $products
     * @return array ['subcategory_id' => [RankedProductDTO[]]]
     */
    public function groupBySubcategory(array $products, AutoGenerateConfigDTO $config): array
    {
        if (! $config->groupBySubcategory) {
            return ['all' => $products];
        }

        $grouped = [];
        foreach ($products as $product) {
            $subcategoryId = $product->subcategoryId ?? 'no_subcategory';
            $grouped[$subcategoryId][] = $product;
        }

        return $grouped;
    }

    /**
     * Aplicar regras de espaçamento e distribuição
     *
     * Regras:
     * - Produtos A devem ter mais espaço (mais facings)
     * - Produtos da mesma subcategoria ficam juntos
     * - Respeitar limites de largura da prateleira
     */
    public function applySpacingRules(RankedProductDTO $product): array
    {
        return [
            'min_width' => ($product->product->width ?? 10) * $product->facings,
            'priority' => match ($product->abcClass) {
                'A' => 3,
                'B' => 2,
                'C' => 1,
                default => 0,
            },
        ];
    }
}
