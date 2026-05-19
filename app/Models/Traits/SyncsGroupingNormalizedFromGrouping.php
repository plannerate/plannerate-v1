<?php

namespace App\Models\Traits;

use App\Models\PlanogramTemplateSlot;
use Illuminate\Support\Str;

trait SyncsGroupingNormalizedFromGrouping
{
    public static function bootSyncsGroupingNormalizedFromGrouping(): void
    {
        static::saving(static function (PlanogramTemplateSlot $slot): void {
            $slot->syncGroupingNormalizedFromGrouping();
        });
    }

    public function syncGroupingNormalizedFromGrouping(): void
    {
        $grouping = trim((string) $this->grouping);

        if ($grouping === '') {
            $this->grouping_normalized = null;

            return;
        }

        $this->grouping_normalized = Str::slug($grouping);
    }
}
