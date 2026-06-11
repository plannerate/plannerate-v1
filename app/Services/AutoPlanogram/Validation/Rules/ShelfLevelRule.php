<?php

namespace App\Services\AutoPlanogram\Validation\Rules;

use App\Models\Product;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Validation\ValidationResult;
use App\Services\AutoPlanogram\Validation\ValidationRuleInterface;
use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Callcocam\LaravelRaptorPlannerate\Models\ShelfLevelPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates that products with high-priority shelf preferences are placed at the correct level.
 *
 * For example: products with EYE preference should not be on LOW shelves.
 */
final class ShelfLevelRule implements ValidationRuleInterface
{
    public function name(): string
    {
        return 'shelf_level';
    }

    /**
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): array
    {
        // No modo template o especialista define shelf_order intencionalmente — pular validação
        if ($input->settings->usesTemplate()) {
            return [];
        }

        $results = [];

        // Load shelf preferences for tenant
        $preferences = $this->loadPreferences($input->tenantId);

        if (empty($preferences)) {
            return $results;
        }

        // Group segments by shelf to get shelf levels
        $segmentsByShelf = $placedSegments->groupBy('shelfId');

        foreach ($segmentsByShelf as $shelfId => $segments) {
            // Get total shelves in section for level calculation
            $firstSegment = $segments->first();
            $numShelves = $this->getNumShelvesInSection($firstSegment->sectionId);

            // Get shelf position
            $shelfPosition = $this->getShelfPosition($shelfId);
            $actualLevel = ShelfLevel::fromShelfPosition($shelfPosition, $numShelves);

            // Check each product in this shelf
            foreach ($segments as $segment) {
                foreach ($segment->layers as $layer) {
                    $productId = $layer->productId;

                    // Get product's category
                    $product = Product::find($productId);
                    if (! $product || ! $product->category_id) {
                        continue;
                    }

                    // Check if there's a preference for this category
                    $preferredLevel = $preferences[$product->category_id] ?? null;
                    if (! $preferredLevel) {
                        continue;
                    }

                    // Check if preference is violated
                    if ($this->isViolation($preferredLevel, $actualLevel)) {
                        $results[] = ValidationResult::warning(
                            $this->name(),
                            "Produto {$productId} tem preferência de nível '{$preferredLevel->label()}' mas está em '{$actualLevel->label()}'.",
                            [$productId],
                            $shelfId,
                        );
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Load category preferences for a tenant.
     *
     * @return array<string, ShelfLevel>
     */
    private function loadPreferences(string $tenantId): array
    {
        $rows = ShelfLevelPreference::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('category_id')
            ->get();

        $preferences = [];
        foreach ($rows as $row) {
            $preferences[$row->category_id] = $row->preferred_level;
        }

        return $preferences;
    }

    /**
     * Check if placing a product at actualLevel violates its preferred level.
     *
     * A violation occurs when:
     * - Preferred EYE but placed in LOW/HIGH
     * - Preferred HAND but placed in HIGH
     * - etc.
     */
    private function isViolation(ShelfLevel $preferred, ShelfLevel $actual): bool
    {
        // Only check high-priority preferences
        if ($preferred === ShelfLevel::Hand) {
            return $actual === ShelfLevel::High;
        }

        if ($preferred === ShelfLevel::Eye) {
            return $actual === ShelfLevel::Low || $actual === ShelfLevel::High;
        }

        return false;
    }

    /**
     * Get the total number of shelves in a section.
     */
    private function getNumShelvesInSection(string $sectionId): int
    {
        $count = DB::connection('tenant')->table('shelves')
            ->where('section_id', $sectionId)
            ->count();

        return max(1, $count);
    }

    /**
     * Índice ordenado da prateleira na seção (0 = topo).
     *
     * shelf_position no banco é coordenada em cm a partir do topo (0, 60, 120…) —
     * não pode ser passada direto a ShelfLevel::fromShelfPosition, que espera
     * índice 0..N-1. Ordenar por shelf_position e usar a posição na lista funciona
     * para ambas as semânticas (coordenada ou índice legado).
     */
    private function getShelfPosition(string $shelfId): int
    {
        $shelf = DB::connection('tenant')->table('shelves')
            ->where('id', $shelfId)
            ->select('section_id', 'shelf_position')
            ->first();

        if ($shelf === null) {
            return 0;
        }

        $orderedIds = DB::connection('tenant')->table('shelves')
            ->where('section_id', $shelf->section_id)
            ->whereNull('deleted_at')
            ->orderBy('shelf_position')
            ->pluck('id')
            ->all();

        $index = array_search($shelfId, $orderedIds, true);

        return $index === false ? 0 : (int) $index;
    }
}
