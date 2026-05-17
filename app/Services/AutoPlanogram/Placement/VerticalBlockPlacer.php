<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Enums\ShelfLevel;
use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacedLayer;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\DTO\VerticalBlockResult;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Posiciona produtos de alta prioridade verticalmente em múltiplas prateleiras.
 *
 * Produtos marcados com is_vertical_block=true ocupam a mesma posição X em todas
 * as prateleiras elegíveis da section, criando uma coluna visual contínua.
 * O espaço reservado é devolvido ao GreedyShelfPlacer para que não haja sobreposição.
 */
final class VerticalBlockPlacer
{
    /**
     * @param  Collection<int, OrderedBlock>  $orderedBlocks
     * @param  Collection<int, Section>  $sections
     */
    public function place(
        Collection $orderedBlocks,
        Collection $sections,
        PlacementSettings $settings,
        int $minShelves = 2,
    ): VerticalBlockResult {
        /** @var array<string, float> */
        $reservedWidthPerShelf = [];
        /** @var array<string, float> section_id => next available X in cm */
        $nextXPerSection = [];
        $verticalSegments = collect();
        $remainingBlocks = collect();
        $candidatesTried = 0;
        $blocksCreated = 0;
        $returnedToGreedy = 0;

        foreach ($orderedBlocks as $orderedBlock) {
            $block = $orderedBlock->block;

            $verticalChildren = $block->children
                ->filter(fn (ScoredProduct $sp) => $sp->metadata['is_vertical_block'] ?? false)
                ->values();

            $normalChildren = $block->children
                ->reject(fn (ScoredProduct $sp) => $sp->metadata['is_vertical_block'] ?? false)
                ->values();

            foreach ($verticalChildren as $sp) {
                $candidatesTried++;
                $result = $this->placeVertically(
                    $sp, $sections, $minShelves, $reservedWidthPerShelf, $nextXPerSection
                );

                if ($result['placed']) {
                    $verticalSegments = $verticalSegments->merge($result['segments']);
                    foreach ($result['reserved'] as $shelfId => $width) {
                        $reservedWidthPerShelf[$shelfId] = ($reservedWidthPerShelf[$shelfId] ?? 0.0) + $width;
                    }
                    $blocksCreated++;
                } else {
                    $normalChildren = $normalChildren->push($sp);
                    $returnedToGreedy++;
                }
            }

            if ($normalChildren->isNotEmpty()) {
                $remainingBlocks->push($orderedBlock->withChildren($normalChildren));
            }
        }

        Log::info('VerticalBlockPlacer: resultado', [
            'candidatos_tentados' => $candidatesTried,
            'blocos_verticais_criados' => $blocksCreated,
            'produtos_devolvidos_greedy' => $returnedToGreedy,
            'shelves_com_reserva' => count($reservedWidthPerShelf),
        ]);

        return new VerticalBlockResult(
            verticalSegments: $verticalSegments,
            remainingBlocks: $remainingBlocks,
            reservedWidthPerShelf: $reservedWidthPerShelf,
        );
    }

    /**
     * @param  array<string, float>  $reservedWidthPerShelf  Current reservations (read-only for checks)
     * @param  array<string, float>  $nextXPerSection  Mutable: tracks next free X per section
     * @return array{placed: bool, segments: Collection<int, PlacedSegment>, reserved: array<string, float>}
     */
    private function placeVertically(
        ScoredProduct $sp,
        Collection $sections,
        int $minShelves,
        array $reservedWidthPerShelf,
        array &$nextXPerSection,
    ): array {
        $facing = (int) ($sp->metadata['facing_final']
            ?? $sp->metadata['facing_ideal']
            ?? $sp->metadata['facing']
            ?? 1);

        $rawWidth = (float) ($sp->product->width ?? 0.0);
        $productWidth = ($rawWidth > 0 && $rawWidth <= 60) ? $rawWidth : 10.0;
        $occupiedWidth = $productWidth * $facing;
        $productHeight = (float) ($sp->product->height ?? 0.0);

        foreach ($sections as $section) {
            $shelves = $section->shelves->sortBy('shelf_position')->values();
            $numShelves = $shelves->count();
            $sectionUsableWidth = max(0.0, (float) ($section->width ?? 100.0) - (float) ($section->cremalheira_width ?? 0.0));

            // Eligible = EYE ou HAND com clearance suficiente e espaço horizontal
            // Bloco vertical pertence ao meio da gôndola — não ao topo (HIGH) nem ao chão (LOW)
            $eligibleShelves = collect(); // array{shelf: Shelf, level: ShelfLevel}[]

            foreach ($shelves as $i => $shelf) {
                $level = ShelfLevel::fromShelfPosition($i, $numShelves);

                if (! in_array($level, [ShelfLevel::Eye, ShelfLevel::Hand])) {
                    continue;
                }

                $clearance = $this->clearance($shelf, $shelves, $i);

                if ($productHeight > 0 && $productHeight > $clearance) {
                    continue;
                }

                $alreadyReserved = $reservedWidthPerShelf[$shelf->id] ?? 0.0;
                $available = $sectionUsableWidth - $alreadyReserved;

                if ($available < $occupiedWidth) {
                    continue;
                }

                $eligibleShelves->push(['shelf' => $shelf, 'level' => $level]);
            }

            if ($eligibleShelves->count() < $minShelves) {
                continue;
            }

            $positionX = (int) round($nextXPerSection[$section->id] ?? 0.0);
            $nextXPerSection[$section->id] = ($nextXPerSection[$section->id] ?? 0.0) + $occupiedWidth;

            $segments = collect();
            $reserved = [];

            foreach ($eligibleShelves as ['shelf' => $shelf, 'level' => $level]) {
                $segments->push(new PlacedSegment(
                    sectionId: $section->id,
                    shelfId: $shelf->id,
                    ordering: 0,
                    position: $positionX,
                    width: (int) round($occupiedWidth),
                    distributedWidth: (int) round($occupiedWidth),
                    layers: collect([new PlacedLayer(
                        productId: $sp->product->id,
                        ean: (string) ($sp->product->ean ?? $sp->product->codigo_erp ?? ''),
                        quantity: $facing,
                        height: 1,
                    )]),
                    isVerticalBlock: true,
                    shelfLevel: $level,
                ));
                $reserved[$shelf->id] = $occupiedWidth;
            }

            return ['placed' => true, 'segments' => $segments, 'reserved' => $reserved];
        }

        return ['placed' => false, 'segments' => collect(), 'reserved' => []];
    }

    /**
     * @param  Collection<int, Shelf>  $allShelves  Sorted ascending by shelf_position
     */
    private function clearance(Shelf $shelf, Collection $allShelves, int $index): float
    {
        if ($index === 0) {
            return (float) ($shelf->shelf_position ?? 0);
        }

        $above = $allShelves->values()[$index - 1];

        return max(0.0,
            (float) ($shelf->shelf_position ?? 0)
            - ((float) ($above->shelf_position ?? 0) + (float) ($above->shelf_height ?? 0))
        );
    }
}
