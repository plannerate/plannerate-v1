<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\ShelfLayoutDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service de Otimização de Layout
 *
 * Responsável por:
 * 1. Criar estrutura de prateleiras do planograma
 * 2. Distribuir produtos nas prateleiras de forma otimizada
 * 3. Lidar com produtos que não couberam
 * 4. Maximizar uso do espaço disponível
 */
class LayoutOptimizationService
{
    public function __construct(
        protected MerchandisingRulesService $merchandisingRules
    ) {}

    /**
     * Distribuir produtos nas prateleiras do planograma
     *
     * @param  Collection<RankedProductDTO>  $rankedProducts
     * @return array{shelves: ShelfLayoutDTO[], unallocated: RankedProductDTO[]}
     */
    public function distributeProducts(
        Gondola $gondola,
        Collection $rankedProducts,
        AutoGenerateConfigDTO $config
    ): array {
        // 1. Criar estrutura de prateleiras
        $shelves = $this->createShelfStructure($gondola);

        if (empty($shelves)) {
            return ['shelves' => [], 'unallocated' => $rankedProducts->toArray()];
        }

        // 2. Calcular facings para cada produto
        $maxSales = $rankedProducts->max('salesTotal') ?: 1;
        foreach ($rankedProducts as $product) {
            $facings = $this->merchandisingRules->calculateFacings($product, $config, $maxSales);
            $product->setFacings($facings);
        }

        // 3. Agrupar produtos (se configurado)
        $groupedProducts = $this->merchandisingRules->groupBySubcategory(
            $rankedProducts->toArray(),
            $config
        );

        // 4. Distribuir produtos nas prateleiras
        $unallocated = [];
        $totalShelves = count($shelves);

        // Calcular score máximo para normalização
        $maxScore = $rankedProducts->max('score') ?: 1.0;

        foreach ($groupedProducts as $group) {
            foreach ($group as $product) {
                // Calcular scoreRatio para distribuição dentro do range ABC
                $scoreRatio = $maxScore > 0 ? ($product->score / $maxScore) : 0.5;

                // Determinar prateleira ideal (agora usa range ABC + scoreRatio)
                $idealShelfIndex = $this->merchandisingRules->determineShelfIndex(
                    $product,
                    $totalShelves,
                    $scoreRatio
                );

                // Tentar alocar na prateleira ideal
                if ($this->tryAllocateProduct($shelves, $product, $idealShelfIndex)) {
                    continue;
                }

                // Se não coube, tentar em prateleiras próximas (limite ±3)
                if ($this->tryAllocateNearby($shelves, $product, $idealShelfIndex, 3)) {
                    continue;
                }

                // Se não coube em nenhuma, adicionar aos não alocados
                $unallocated[] = $product;
            }
        }

        return [
            'shelves' => $shelves,
            'unallocated' => $unallocated,
        ];
    }

    /**
     * Criar estrutura de prateleiras baseado na gôndola
     *
     * @return ShelfLayoutDTO[]
     */
    protected function createShelfStructure(Gondola $gondola): array
    {
        $shelves = [];
        $shelfIndex = 0;

        // Iterar por TODAS as sections da gôndola
        foreach ($gondola->sections as $section) {
            $availableWidth = $section->width ?? 100;

            // Criar um ShelfLayoutDTO para cada shelf da section
            foreach ($section->shelves as $shelf) {
                $shelves[$shelfIndex] = new ShelfLayoutDTO(
                    id: $shelf->id, // Adicionar ID da shelf
                    shelfIndex: $shelfIndex,
                    height: $shelf->height ?? 30,
                    availableWidth: $availableWidth,
                );
                $shelfIndex++;
            }
        }

        return $shelves;
    }

    /**
     * Tentar alocar produto em uma prateleira específica
     *
     * Valida:
     * - Largura disponível
     * - Altura compatível (produto deve caber verticalmente)
     */
    protected function tryAllocateProduct(array $shelves, RankedProductDTO $product, int $shelfIndex): bool
    {
        if (! isset($shelves[$shelfIndex])) {
            return false;
        }

        $shelf = $shelves[$shelfIndex];

        // Validar se produto cabe verticalmente na prateleira
        $productHeight = $product->product->height ?? 0;
        if ($productHeight > 0 && $productHeight > $shelf->height) {
            Log::debug('⚠️  Produto não cabe verticalmente', [
                'product' => $product->product->name,
                'product_height' => $productHeight,
                'shelf_height' => $shelf->height,
                'shelf_index' => $shelfIndex,
            ]);

            return false;
        }

        return $shelf->addProduct($product);
    }

    /**
     * Tentar alocar produto em prateleiras próximas
     *
     * MELHORIA V2: Limite de distância para não colocar produto A em prateleira C
     *
     * @param  int  $idealIndex  Índice ideal calculado
     * @param  int  $maxDistance  Distância máxima permitida (padrão: 3 prateleiras)
     */
    protected function tryAllocateNearby(
        array $shelves,
        RankedProductDTO $product,
        int $idealIndex,
        int $maxDistance = 3
    ): bool {
        $totalShelves = count($shelves);

        // Tentar em ordem crescente de distância (até o limite)
        for ($distance = 1; $distance <= $maxDistance; $distance++) {
            // Tentar acima (prioridade: prateleiras mais altas são melhores)
            $upperIndex = $idealIndex + $distance;
            if ($upperIndex < $totalShelves && $this->tryAllocateProduct($shelves, $product, $upperIndex)) {
                return true;
            }

            // Tentar abaixo
            $lowerIndex = $idealIndex - $distance;
            if ($lowerIndex >= 0 && $this->tryAllocateProduct($shelves, $product, $lowerIndex)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Otimizar distribuição de produtos (pós-alocação)
     *
     * Pode implementar algoritmos de:
     * - Balanceamento de carga entre prateleiras
     * - Reagrupamento por subcategoria
     * - Minimização de espaços vazios
     */
    public function optimizeDistribution(array $shelves): array
    {
        // TODO: Implementar otimizações avançadas em versões futuras
        return $shelves;
    }
}
