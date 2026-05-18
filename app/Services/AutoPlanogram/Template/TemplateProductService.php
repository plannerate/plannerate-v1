<?php

namespace App\Services\AutoPlanogram\Template;

use Illuminate\Database\Eloquent\Model;

final class TemplateProductService
{
    public function normalizeGrouping(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }

    /** @return array<string, mixed> */
    public function templateData(Model $template): array
    {
        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'department' => $template->department,
            'is_active' => $template->is_active,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function productsData(Model $template): array
    {
        return $template->templateProducts
            ->map(fn (Model $p): array => [
                'id' => $p->id,
                'ean' => $p->ean,
                'product_id' => $p->product_id,
                'description' => $p->description,
                'brand' => $p->brand,
                'grouping' => $p->grouping,
                'category' => $p->category,
                'subcategory' => $p->subcategory,
                'package_type' => $p->package_type,
                'package_content' => $p->package_content,
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function availableGroupings(Model $template): array
    {
        return $template->subtemplates
            ->flatMap(fn (Model $sub) => $sub->slots->pluck('grouping'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Store template products, skipping duplicates.
     * The $resolver receives ($ean, $item) and returns the context-specific attributes
     * (description, brand, department, product_id, etc.) to merge into the create payload.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  callable(string, array<string, mixed>): array<string, mixed>  $resolver
     * @param  array<string, mixed>  $extra
     */
    public function storeProducts(Model $template, array $items, callable $resolver, array $extra = []): void
    {
        foreach ($items as $item) {
            $ean = $item['ean'];
            $grouping = $item['grouping'];

            $alreadyExists = $template->templateProducts()
                ->where('ean', $ean)
                ->where('grouping_normalized', $this->normalizeGrouping($grouping))
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $template->templateProducts()->create([
                ...$extra,
                'ean' => $ean,
                'grouping' => $grouping,
                'grouping_normalized' => $this->normalizeGrouping($grouping),
                ...$resolver($ean, $item),
            ]);
        }
    }

    public function updateProduct(Model $product, string $grouping): void
    {
        $product->update([
            'grouping' => $grouping,
            'grouping_normalized' => $this->normalizeGrouping($grouping),
        ]);
    }

    public function destroyProduct(Model $product): void
    {
        $product->delete();
    }
}
