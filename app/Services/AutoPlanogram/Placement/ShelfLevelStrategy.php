<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Enums\ShelfLevel;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Strategy for determining and applying shelf level preferences.
 *
 * Manages product placement by shelf level (eye, hand, low, high) based on:
 * 1. Explicit tenant/category preferences from shelf_level_preferences table
 * 2. Heuristic scores based on product characteristics (margin, sales velocity, strategic importance)
 */
final class ShelfLevelStrategy
{
    /** @var array<string, ShelfLevel> */
    private array $preferences;

    private ShelfLevel $tenantDefault;

    public function __construct(private string $tenantId)
    {
        $this->loadPreferences();
    }

    /**
     * Load preferences from database for this tenant.
     */
    private function loadPreferences(): void
    {
        $rows = DB::table('shelf_level_preferences')
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->get();

        $this->preferences = [];
        $this->tenantDefault = ShelfLevel::Hand;

        foreach ($rows as $row) {
            $level = ShelfLevel::from($row->preferred_level);
            if ($row->category_id === null) {
                $this->tenantDefault = $level;
            } else {
                $this->preferences[$row->category_id] = $level;
            }
        }
    }

    /**
     * Decide the preferred shelf level for a product block.
     *
     * Priority:
     * 1. Explicit preference for adjacency category (level 4)
     * 2. Heuristic based on block characteristics
     */
    public function decidePreferredLevel(ProductBlock $block): ShelfLevel
    {
        // 1. Check explicit preference for adjacency category
        if ($block->adjacencyCategoryId && isset($this->preferences[$block->adjacencyCategoryId])) {
            return $this->preferences[$block->adjacencyCategoryId];
        }

        // 2. Check explicit default tenant preference
        if (empty($block->adjacencyCategoryId)) {
            return $this->tenantDefault;
        }

        // 3. Apply heuristic
        return $this->heuristicLevel($block);
    }

    /**
     * Apply heuristic to determine shelf level based on product characteristics.
     *
     * Scoring logic:
     * - Strategic products → HIGH (institutional visibility)
     * - High margin → EYE (premium positioning)
     * - High sales velocity → HAND (accessibility)
     * - Otherwise → LOW (volume/economy)
     */
    private function heuristicLevel(ProductBlock $block): ShelfLevel
    {
        if ($block->children->isEmpty()) {
            return $this->tenantDefault;
        }

        $avgMargin = $block->children->avg(function ($sp) {
            return (float) ($sp->metadata['margem_norm'] ?? 0);
        });

        $avgGiro = $block->children->avg(function ($sp) {
            return (float) ($sp->metadata['giro_norm'] ?? 0);
        });

        $avgStrategic = $block->children->avg(function ($sp) {
            return (float) ($sp->metadata['strategic'] ?? 0);
        });

        // Strategic products go to HIGH (institutional visibility)
        if ($avgStrategic >= 0.5) {
            return ShelfLevel::High;
        }

        // High margin → EYE (premium/profitable)
        if ($avgMargin >= 0.7) {
            return ShelfLevel::Eye;
        }

        // High sales velocity → HAND (accessibility)
        if ($avgGiro >= 0.7) {
            return ShelfLevel::Hand;
        }

        // Default: LOW (volume/economy)
        return ShelfLevel::Low;
    }

    /**
     * Pick the best shelf from available options considering preferred level.
     *
     * Selection strategy:
     * 1. Try to match exact shelf level
     * 2. Fall back to closest priority score
     * 3. Return null if no shelves available
     */
    public function pickShelf(
        ShelfLevel $preferred,
        Collection $availableShelves,
        int $numShelvesTotal
    ): ?Shelf {
        if ($availableShelves->isEmpty()) {
            return null;
        }

        // Annotate each shelf with its ShelfLevel
        $annotated = $availableShelves->map(function (Shelf $shelf) use ($numShelvesTotal) {
            return [
                'shelf' => $shelf,
                'level' => ShelfLevel::fromShelfPosition(
                    (int) $shelf->shelf_position,
                    $numShelvesTotal
                ),
            ];
        });

        // 1st attempt: exact match
        $exact = $annotated->firstWhere('level', $preferred);
        if ($exact) {
            return $exact['shelf'];
        }

        // Fallback: sort by priority score proximity
        $sorted = $annotated->sortBy(function ($annotation) use ($preferred) {
            return abs(
                $annotation['level']->priorityScore() - $preferred->priorityScore()
            );
        });

        $first = $sorted->first();

        return $first ? $first['shelf'] : null;
    }

    /**
     * Get preference for a specific category.
     */
    public function getPreferenceForCategory(?string $categoryId): ShelfLevel
    {
        if (! $categoryId) {
            return $this->tenantDefault;
        }

        return $this->preferences[$categoryId] ?? $this->tenantDefault;
    }

    /**
     * Reload preferences from database (for testing or dynamic updates).
     */
    public function reloadPreferences(): void
    {
        $this->loadPreferences();
    }
}
