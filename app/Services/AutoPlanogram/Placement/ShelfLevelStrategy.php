<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Services\AutoPlanogram\DTO\ProductBlock;
use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $rows = DB::connection('tenant')->table('shelf_level_preferences')
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
     * 1. Explicit preference for adjacency category
     * 2. ABC classification based on aggregateScore
     */
    public function decidePreferredLevel(ProductBlock $block): ShelfLevel
    {
        if ($block->adjacencyCategoryId && isset($this->preferences[$block->adjacencyCategoryId])) {
            return $this->preferences[$block->adjacencyCategoryId];
        }

        return $this->levelFromAbcClass($block);
    }

    /**
     * Deriva o nível preferido a partir da classificação ABC do bloco.
     *
     * Thresholds calibrados para distribuição ~20% EYE, ~30% HAND, ~50% LOW.
     * Somente produtos estratégicos vão para HIGH — score alto sozinho não basta.
     * - Estratégico (strategic >= 1.0) → HIGH
     * - A  (≥0.70) → EYE (top ~20%)
     * - B  (≥0.35) → HAND (próximos ~30%)
     * - C  (<0.35) → LOW (restante ~50%)
     */
    private function levelFromAbcClass(ProductBlock $block): ShelfLevel
    {
        $hasStrategic = $block->children->some(
            fn ($sp) => ((float) ($sp->metadata['strategic'] ?? 0)) >= 1.0
        );

        if ($hasStrategic) {
            $level = ShelfLevel::High;
        } else {
            $level = match (true) {
                $block->aggregateScore >= 0.70 => ShelfLevel::Eye,
                $block->aggregateScore >= 0.35 => ShelfLevel::Hand,
                default => ShelfLevel::Low,
            };
        }

        Log::debug('ShelfLevelStrategy: nível decidido', [
            'block_key' => $block->groupingKey,
            'avg_score' => round($block->aggregateScore, 3),
            'has_strategic' => $hasStrategic,
            'nivel_decided' => $level->value,
            'fallback_order' => array_map(fn ($l) => $l->value, $level->fallbackOrder()),
        ]);

        return $level;
    }

    /**
     * Pick the best shelf from available options using hierarchical fallback.
     *
     * Percorre a ordem de fallback do nível preferido — produto NUNCA vai para
     * um nível fora desta lista (sem fallback para nível inadequado).
     */
    public function pickShelf(
        ShelfLevel $preferred,
        Collection $availableShelves,
        int $numShelvesTotal
    ): ?Shelf {
        if ($availableShelves->isEmpty()) {
            return null;
        }

        $annotated = $availableShelves->map(function (Shelf $shelf) use ($numShelvesTotal) {
            return [
                'shelf' => $shelf,
                'level' => ShelfLevel::fromShelfPosition(
                    (int) $shelf->shelf_position,
                    $numShelvesTotal
                ),
            ];
        });

        foreach ($preferred->fallbackOrder() as $targetLevel) {
            $match = $annotated->firstWhere('level', $targetLevel);
            if ($match) {
                return $match['shelf'];
            }
        }

        return null;
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
