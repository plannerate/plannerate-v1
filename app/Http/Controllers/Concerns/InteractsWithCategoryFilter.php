<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Category;

trait InteractsWithCategoryFilter
{
    /**
     * @return list<string>
     */
    private function categoryAndDescendantIds(string $categoryId): array
    {
        $categoryId = trim($categoryId);

        if ($categoryId === '') {
            return [];
        }

        $allIds = [$categoryId];
        $pendingParentIds = [$categoryId];

        while ($pendingParentIds !== []) {
            $childrenIds = Category::query()
                ->whereIn('category_id', $pendingParentIds)
                ->pluck('id')
                ->map(static fn(mixed $id): string => (string) $id)
                ->filter(static fn(string $id): bool => $id !== '')
                ->unique()
                ->values()
                ->all();

            $nextPendingParentIds = array_values(array_diff($childrenIds, $allIds));

            if ($nextPendingParentIds === []) {
                break;
            }

            $allIds = array_values(array_unique([...$allIds, ...$nextPendingParentIds]));
            $pendingParentIds = $nextPendingParentIds;
        }

        return $allIds;
    }

    /**
     * @return list<string>
     */
    private function resolveCategoryFilter(string $categoryId): array
    {
        return $this->categoryAndDescendantIds($categoryId);
    }
}
