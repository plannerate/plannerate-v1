<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Enums\ShelfLevel;
use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacedLayer;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Placement\ShelfLevelStrategy;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\ShelfLayoutDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\MerchandisingRulesService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Motor de posicionamento que replica o algoritmo guloso do LayoutOptimizationService.
 *
 * Converte OrderedBlocks em PlacedSegments preservando exatamente o comportamento atual:
 * - ABC determina o range de prateleiras (A=superior, B=médio, C=inferior)
 * - Score relativo determina a posição exata dentro do range
 * - Produto não alocado tenta prateleiras adjacentes (±3)
 * - Produto não cabe verticalmente = descartado da prateleira
 */
final class GreedyShelfPlacer implements PlacementEngineInterface
{
    public function __construct(
        private readonly MerchandisingRulesService $merchandisingRules,
    ) {}

    /**
     * @param  Collection<int, OrderedBlock>  $orderedBlocks
     * @param  Collection<int, Section>  $sections
     * @return Collection<int, PlacedSegment>
     */
    public function place(Collection $orderedBlocks, Collection $sections, PlacementSettings $settings): Collection
    {
        $configDto = $settings->toConfigDto();
        $strategy = $settings->tenantId ? new ShelfLevelStrategy($settings->tenantId) : null;

        $sectionLayouts = $this->buildSectionShelfStructure($sections);

        if ($sectionLayouts === []) {
            return collect();
        }

        $rankedBlocks = $orderedBlocks
            ->sortBy('sequenceOrder')
            ->values();

        /** @var Collection<int, Collection<int, RankedProductDTO>> $productsByBlock */
        $productsByBlock = $rankedBlocks
            ->map(fn (OrderedBlock $orderedBlock): Collection => $orderedBlock->block->children->map(
                fn (ScoredProduct $product) => $this->toRankedProductDto($product)
            )->values())
            ->values();

        $rankedProducts = $productsByBlock->flatten(1)->values();

        if ($rankedProducts->isEmpty()) {
            return collect();
        }

        $maxSales = $rankedProducts->max('salesTotal') ?: 1;
        foreach ($rankedProducts as $product) {
            $product->setFacings($this->merchandisingRules->calculateFacings($product, $configDto, $maxSales));
        }

        $currentSectionIndex = 0;
        $currentShelfIndex = 0;

        foreach ($productsByBlock as $blockIndex => $blockProducts) {
            $orderedBlock = $rankedBlocks[$blockIndex];

            if ($strategy) {
                $preferredLevel = $strategy->decidePreferredLevel($orderedBlock->block);
                $this->reorderShelvesByLevel($sectionLayouts, $preferredLevel);
            }

            if ($this->placeWholeBlock($sectionLayouts, $blockProducts->all(), $currentSectionIndex, $currentShelfIndex)) {
                continue;
            }

            Log::warning('GreedyShelfPlacer: bloco maior que a section ou sem encaixe inteiro, quebrando por produto', [
                'grouping_key' => $orderedBlock->block->groupingKey,
                'products_count' => $blockProducts->count(),
            ]);

            $this->placeBrokenBlock($sectionLayouts, $blockProducts->all(), $currentSectionIndex, $currentShelfIndex, $orderedBlock);
        }

        return $this->convertToPlacedSegments($this->flattenShelves($sectionLayouts), $sections);
    }

    /** @param  Collection<int, Section>  $sections */
    private function buildSectionShelfStructure(Collection $sections): array
    {
        $sectionLayouts = [];
        $index = 0;

        foreach ($sections as $section) {
            $availableWidth = (float) ($section->width ?? 100);
            $sectionShelves = $section->shelves;
            $numShelves = $sectionShelves->count();
            $shelves = [];

            foreach ($sectionShelves as $shelfIndex => $shelf) {
                $shelves[] = new ShelfLayoutDTO(
                    id: $shelf->id,
                    shelfIndex: $index,
                    height: (float) ($shelf->shelf_height ?? 30),
                    availableWidth: $availableWidth,
                    depth: (float) ($shelf->shelf_depth ?? 40),
                    shelfPosition: (int) ($shelf->shelf_position ?? $shelfIndex),
                );
                $index++;
            }

            $sectionLayouts[] = [
                'section_id' => $section->id,
                'shelves' => $shelves,
                'num_shelves' => $numShelves,
            ];
        }

        return $sectionLayouts;
    }

    /**
     * Reordena as shelves de cada section para priorizar o nível preferido,
     * mantendo o comportamento de fallback para shelves disponíveis.
     *
     * @param  array<int, array{section_id: string, shelves: array<int, ShelfLayoutDTO>, num_shelves: int}>  $sectionLayouts
     */
    private function reorderShelvesByLevel(array &$sectionLayouts, ShelfLevel $preferred): void
    {
        foreach ($sectionLayouts as &$layout) {
            $numShelves = $layout['num_shelves'];
            usort($layout['shelves'], function (ShelfLayoutDTO $a, ShelfLayoutDTO $b) use ($preferred, $numShelves): int {
                $levelA = ShelfLevel::fromShelfPosition($a->shelfPosition, $numShelves);
                $levelB = ShelfLevel::fromShelfPosition($b->shelfPosition, $numShelves);
                $matchA = $levelA === $preferred ? 0 : 1;
                $matchB = $levelB === $preferred ? 0 : 1;

                return $matchA <=> $matchB;
            });
        }
        unset($layout);
    }

    private function toRankedProductDto(ScoredProduct $sp): RankedProductDTO
    {
        return new RankedProductDTO(
            product: $sp->product,
            abcClass: $sp->metadata['abc_class'] ?? null,
            score: $sp->score,
            salesTotal: (float) ($sp->metadata['sales_total'] ?? 0),
            margin: (float) ($sp->metadata['margin'] ?? 0),
            subcategoryId: $sp->product->category_id ?? null,
            targetStock: isset($sp->metadata['target_stock']) ? (float) $sp->metadata['target_stock'] : null,
            safetyStock: isset($sp->metadata['safety_stock']) ? (float) $sp->metadata['safety_stock'] : null,
        );
    }

    private function tryAllocate(array $shelves, RankedProductDTO $product, int $index): bool
    {
        if (! isset($shelves[$index])) {
            return false;
        }

        $shelf = $shelves[$index];
        $productHeight = $product->product->height ?? 0;

        if ($productHeight > 0 && $productHeight > $shelf->height) {
            Log::debug('GreedyShelfPlacer: produto não cabe verticalmente', [
                'product' => $product->product->name,
                'product_height' => $productHeight,
                'shelf_height' => $shelf->height,
            ]);

            return false;
        }

        return $shelf->addProduct($product);
    }

    /** @param  array<int, array{section_id: string, shelves: array<int, ShelfLayoutDTO>}>  $sectionLayouts */
    private function placeWholeBlock(array &$sectionLayouts, array $products, int &$currentSectionIndex, int &$currentShelfIndex): bool
    {
        for ($sectionIndex = $currentSectionIndex; $sectionIndex < count($sectionLayouts); $sectionIndex++) {
            $startShelfIndex = $sectionIndex === $currentSectionIndex ? $currentShelfIndex : 0;
            $lastShelfIndex = $this->tryPlaceProductsInSection($sectionLayouts[$sectionIndex]['shelves'], $products, $startShelfIndex);

            if ($lastShelfIndex !== null) {
                $currentSectionIndex = $sectionIndex;
                $currentShelfIndex = $lastShelfIndex;

                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, ShelfLayoutDTO>  $shelves
     * @param  array<int, RankedProductDTO>  $products
     */
    private function tryPlaceProductsInSection(array &$shelves, array $products, int $startShelfIndex): ?int
    {
        if ($products === [] || $shelves === []) {
            return null;
        }

        $workingShelves = $this->cloneShelves($shelves);
        $currentShelfIndex = max(0, $startShelfIndex);

        foreach ($products as $product) {
            $placed = false;

            for ($shelfIndex = $currentShelfIndex; $shelfIndex < count($workingShelves); $shelfIndex++) {
                if (! $this->tryAllocate($workingShelves, $product, $shelfIndex)) {
                    continue;
                }

                $currentShelfIndex = $shelfIndex;
                $placed = true;

                break;
            }

            if (! $placed) {
                return null;
            }
        }

        $this->syncShelves($shelves, $workingShelves);

        return $currentShelfIndex;
    }

    /**
     * @param  array<int, array{section_id: string, shelves: array<int, ShelfLayoutDTO>}>  $sectionLayouts
     * @param  array<int, RankedProductDTO>  $products
     */
    private function placeBrokenBlock(
        array &$sectionLayouts,
        array $products,
        int &$currentSectionIndex,
        int &$currentShelfIndex,
        OrderedBlock $orderedBlock,
    ): void {
        foreach ($products as $product) {
            $placed = false;

            while ($currentSectionIndex < count($sectionLayouts)) {
                $sectionShelves = &$sectionLayouts[$currentSectionIndex]['shelves'];

                for ($shelfIndex = $currentShelfIndex; $shelfIndex < count($sectionShelves); $shelfIndex++) {
                    if (! $this->tryAllocate($sectionShelves, $product, $shelfIndex)) {
                        continue;
                    }

                    $currentShelfIndex = $shelfIndex;
                    $placed = true;

                    break;
                }

                unset($sectionShelves);

                if ($placed) {
                    break;
                }

                $currentSectionIndex++;
                $currentShelfIndex = 0;
            }

            if ($placed) {
                continue;
            }

            Log::warning('GreedyShelfPlacer: produto do bloco nao pode ser posicionado', [
                'grouping_key' => $orderedBlock->block->groupingKey,
                'product_id' => $product->product->id,
            ]);

            return;
        }
    }

    /**
     * @param  array<int, ShelfLayoutDTO>  $shelves
     * @return array<int, ShelfLayoutDTO>
     */
    private function cloneShelves(array $shelves): array
    {
        return array_map(function (ShelfLayoutDTO $shelf): ShelfLayoutDTO {
            $clone = new ShelfLayoutDTO(
                id: $shelf->id,
                shelfIndex: $shelf->shelfIndex,
                height: $shelf->height,
                availableWidth: $shelf->availableWidth,
                depth: $shelf->depth,
            );

            $clone->products = $shelf->products;
            $clone->occupiedWidth = $shelf->occupiedWidth;

            return $clone;
        }, $shelves);
    }

    /**
     * @param  array<int, ShelfLayoutDTO>  $targetShelves
     * @param  array<int, ShelfLayoutDTO>  $sourceShelves
     */
    private function syncShelves(array &$targetShelves, array $sourceShelves): void
    {
        foreach ($sourceShelves as $index => $shelf) {
            $targetShelves[$index]->products = $shelf->products;
            $targetShelves[$index]->occupiedWidth = $shelf->occupiedWidth;
        }
    }

    /**
     * @param  array<int, array{section_id: string, shelves: array<int, ShelfLayoutDTO>}>  $sectionLayouts
     * @return array<int, ShelfLayoutDTO>
     */
    private function flattenShelves(array $sectionLayouts): array
    {
        return array_values(array_merge(
            [],
            ...array_map(fn (array $layout): array => $layout['shelves'], $sectionLayouts),
        ));
    }

    /**
     * Converte ShelfLayoutDTOs para PlacedSegments, mantendo o mapeamento correto
     * para section_id e shelf_id com base nas sections e suas shelves.
     *
     * @param  ShelfLayoutDTO[]  $shelves
     * @param  Collection<int, Section>  $sections
     * @return Collection<int, PlacedSegment>
     */
    private function convertToPlacedSegments(array $shelves, Collection $sections): Collection
    {
        // Monta mapa shelfId → sectionId para look-up rápido
        $shelfToSection = [];
        foreach ($sections as $section) {
            foreach ($section->shelves as $shelf) {
                $shelfToSection[$shelf->id] = $section->id;
            }
        }

        $placedSegments = collect();

        foreach ($shelves as $shelfLayout) {
            if (empty($shelfLayout->products)) {
                continue;
            }

            $sectionId = $shelfToSection[$shelfLayout->id] ?? '';
            $ordering = 0;
            $positionX = 0;

            foreach ($shelfLayout->products as $rankedProduct) {
                $productWidth = (int) round(($rankedProduct->product->width ?? 10) * $rankedProduct->facings);

                $layer = new PlacedLayer(
                    productId: $rankedProduct->product->id,
                    ean: (string) ($rankedProduct->product->ean ?? $rankedProduct->product->codigo_erp ?? ''),
                    quantity: $rankedProduct->facings,
                    height: 1, // empilhamento padrão nesta fase
                );

                $placedSegments->push(new PlacedSegment(
                    sectionId: $sectionId,
                    shelfId: $shelfLayout->id,
                    ordering: $ordering,
                    position: $positionX,
                    width: $productWidth,
                    distributedWidth: $productWidth,
                    layers: collect([$layer]),
                ));

                $ordering++;
                $positionX += $productWidth;
            }
        }

        return $placedSegments;
    }
}
