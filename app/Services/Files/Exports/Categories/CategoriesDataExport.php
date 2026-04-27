<?php

namespace App\Services\Files\Exports\Categories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CategoriesDataExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $tenantId
    ) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return CategoriesTemplateExport::columns();
    }

    public function title(): string
    {
        return 'Categorias';
    }

    /**
     * @return Collection<int, array<int, mixed>>
     */
    public function collection(): Collection
    {
        return Product::query()
            ->where('tenant_id', $this->tenantId)
            ->whereNotNull('category_id')
            ->orderBy('ean')
            ->with([
                'category.parent.parent.parent.parent.parent.parent',
            ])
            ->get()
            ->map(function (Product $product): array {
                $levels = $this->categoryLevels($product->category);

                return [
                    $levels[0] ?? '',
                    $levels[1] ?? '',
                    $levels[2] ?? '',
                    $levels[3] ?? '',
                    $levels[4] ?? '',
                    $levels[5] ?? '',
                    $levels[6] ?? '',
                    $levels[7] ?? '',
                    $product->ean ?? '',
                ];
            });
    }

    /**
     * @return array<int, string>
     */
    private function categoryLevels(?Category $category): array
    {
        if (! $category instanceof Category) {
            return [];
        }

        return $category->getFullHierarchy()
            ->pluck('name')
            ->values()
            ->all();
    }
}
