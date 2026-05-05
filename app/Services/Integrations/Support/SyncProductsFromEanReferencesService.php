<?php

namespace App\Services\Integrations\Support;

use App\Models\EanReference;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncProductsFromEanReferencesService
{
    /**
     * @var array<string, bool>
     */
    private array $tenantCategoryIds = [];

    /**
     * @var array<string, string>
     */
    private array $tenantCategoryIdBySlug = [];

    /**
     * @var array<string, string>
     */
    private array $tenantCategoryIdByName = [];

    /**
     * @return array{matched: int, updated: int, remaining: int}
     */
    public function sync(string $tenantConnectionName, string $tenantId, bool $preview = false): array
    {
        if ($tenantId === '') {
            return [
                'matched' => 0,
                'updated' => 0,
                'remaining' => 0,
            ];
        }

        $connection = DB::connection($tenantConnectionName);
        $products = $connection
            ->table('products')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('ean')
            ->where('ean', '!=', '')
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            return [
                'matched' => 0,
                'updated' => 0,
                'remaining' => 0,
            ];
        }

        $referencesByEan = $this->loadReferencesByEan($products);

        $matched = 0;
        $updated = 0;
        $remaining = 0;

        $this->loadTenantCategoryMaps($tenantConnectionName, $tenantId);

        foreach ($products as $product) {
            $ean = EanReference::normalizeEan((string) ($product->ean ?? ''));
            if ($ean === '') {
                continue;
            }

            $reference = $referencesByEan->get($ean);
            if (! $reference instanceof EanReference) {
                continue;
            }

            $matched++;

            $updates = $this->updatesForProduct($product, $reference);

            if ($updates === []) {
                continue;
            }

            $remaining++;

            if ($preview) {
                continue;
            }

            $updates['updated_at'] = Carbon::now();

            $connection
                ->table('products')
                ->where('id', (string) $product->id)
                ->update($updates);

            $updated++;
            $remaining--;
        }

        return [
            'matched' => $matched,
            'updated' => $updated,
            'remaining' => $remaining,
        ];
    }

    /**
     * @param  Collection<int, object>  $products
     * @return Collection<string, EanReference>
     */
    private function loadReferencesByEan(Collection $products): Collection
    {
        $eans = $products
            ->pluck('ean')
            ->filter(fn (mixed $ean): bool => is_string($ean) && trim($ean) !== '')
            ->map(fn (string $ean): string => EanReference::normalizeEan($ean))
            ->filter(fn (string $ean): bool => $ean !== '')
            ->unique()
            ->values();

        if ($eans->isEmpty()) {
            return collect();
        }

        return EanReference::query()
            ->whereIn('ean', $eans->all())
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('ean');
    }

    private function loadTenantCategoryMaps(string $tenantConnectionName, string $tenantId): void
    {
        $this->tenantCategoryIds = [];
        $this->tenantCategoryIdBySlug = [];
        $this->tenantCategoryIdByName = [];

        $categories = DB::connection($tenantConnectionName)
            ->table('categories')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get(['id', 'slug', 'name']);

        foreach ($categories as $category) {
            $id = is_string($category->id ?? null) ? $category->id : null;
            if ($id === null || $id === '') {
                continue;
            }

            $this->tenantCategoryIds[$id] = true;

            $slug = is_string($category->slug ?? null) ? trim($category->slug) : '';
            $name = is_string($category->name ?? null) ? mb_strtolower(trim($category->name)) : '';

            if ($slug !== '' && ! isset($this->tenantCategoryIdBySlug[$slug])) {
                $this->tenantCategoryIdBySlug[$slug] = $id;
            }

            if ($name !== '' && ! isset($this->tenantCategoryIdByName[$name])) {
                $this->tenantCategoryIdByName[$name] = $id;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function updatesForProduct(object $product, EanReference $reference): array
    {
        $updates = [];

        $categoryId = $this->resolveTenantCategoryId($reference);
        if (($product->category_id === null || $product->category_id === '') && $categoryId !== null) {
            $updates['category_id'] = $categoryId;
        }

        foreach ([
            'description' => $reference->reference_description,
            'brand' => $reference->brand,
            'subbrand' => $reference->subbrand,
            'packaging_type' => $reference->packaging_type,
            'packaging_size' => $reference->packaging_size,
            'measurement_unit' => $reference->measurement_unit,
            'unit' => $reference->unit,
            'dimension_status' => $reference->dimension_status,
        ] as $column => $value) {
            if (($product->{$column} === null || $product->{$column} === '') && is_string($value) && $value !== '') {
                $updates[$column] = $value;
            }
        }

        foreach (['width', 'height', 'depth', 'weight'] as $column) {
            $productValue = $product->{$column};
            $referenceValue = $reference->{$column};

            if ($productValue === null && $referenceValue !== null) {
                $updates[$column] = $referenceValue;
            }
        }

        $missingAnyDimension = $product->width === null || $product->height === null || $product->depth === null;
        if ($missingAnyDimension) {
            $updates['has_dimensions'] = (bool) $reference->has_dimensions;
        }

        return $updates;
    }

    private function resolveTenantCategoryId(EanReference $reference): ?string
    {
        $referenceCategoryId = is_string($reference->category_id) ? trim($reference->category_id) : '';
        if ($referenceCategoryId !== '' && isset($this->tenantCategoryIds[$referenceCategoryId])) {
            return $referenceCategoryId;
        }

        $categorySlug = is_string($reference->category_slug) ? trim($reference->category_slug) : '';
        if ($categorySlug !== '' && isset($this->tenantCategoryIdBySlug[$categorySlug])) {
            return $this->tenantCategoryIdBySlug[$categorySlug];
        }

        $categoryName = is_string($reference->category_name) ? mb_strtolower(trim($reference->category_name)) : '';
        if ($categoryName !== '' && isset($this->tenantCategoryIdByName[$categoryName])) {
            return $this->tenantCategoryIdByName[$categoryName];
        }

        return null;
    }
}
