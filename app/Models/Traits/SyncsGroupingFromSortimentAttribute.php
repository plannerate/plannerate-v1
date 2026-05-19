<?php

namespace App\Models\Traits;

use App\Models\Product;
use Illuminate\Support\Str;

trait SyncsGroupingFromSortimentAttribute
{
    public static function bootSyncsGroupingFromSortimentAttribute(): void
    {
        static::saving(static function (Product $product): void {
            $product->syncGroupingFromSortimentAttribute();
        });
    }

    public function syncGroupingFromSortimentAttribute(): void
    {
        $sortimentAttribute = trim((string) $this->sortiment_attribute);

        if ($sortimentAttribute === '') {
            $this->grouping = null;
            $this->grouping_normalized = null;

            return;
        }

        $this->grouping = $sortimentAttribute;
        $this->grouping_normalized = Str::slug($sortimentAttribute);
    }
}
