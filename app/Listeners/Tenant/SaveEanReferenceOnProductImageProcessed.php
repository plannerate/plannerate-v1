<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\ProductImageProcessed;
use App\Models\Category;
use App\Models\EanReference;
use App\Models\Product;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaveEanReferenceOnProductImageProcessed
{
    public function handle(ProductImageProcessed $event): void
    {
        if ($event->ean === '') {
            return;
        }

        $product = $this->loadProduct($event->productId, $event->database);

        EanReference::updateOrCreate(
            ['ean' => EanReference::normalizeEan($event->ean)],
            $this->buildAttributes($event, $product),
        );
    }

    private function loadProduct(string $productId, ?string $database): ?Product
    {
        try {
            $connectionName = (string) config('multitenancy.tenant_database_connection_name', 'tenant');

            if ($database !== null && $database !== '') {
                $tenantConfig = (array) config("database.connections.{$connectionName}", []);
                $tenantConfig['database'] = $database;
                Config::set("database.connections.{$connectionName}", $tenantConfig);
                DB::purge($connectionName);
            }

            return Product::on($connectionName)
                ->with('category:id,name,slug')
                ->find($productId);
        } catch (\Throwable $e) {
            Log::warning('SaveEanReferenceOnProductImageProcessed: falha ao carregar produto', [
                'product_id' => $productId,
                'database' => $database,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(ProductImageProcessed $event, ?Product $product): array
    {
        $attributes = [];

        if ($event->imagePath !== null) {
            $attributes['image_front_url'] = $event->imagePath;
        }

        if ($product === null) {
            return $attributes;
        }

        // Dimensions
        $hasDimension = false;
        foreach (['width', 'height', 'depth'] as $dim) {
            $value = $product->{$dim};
            if (is_numeric($value) && (float) $value > 0) {
                $attributes[$dim] = $value;
                $hasDimension = true;
            }
        }

        $weight = $product->weight;
        if (is_numeric($weight) && (float) $weight > 0) {
            $attributes['weight'] = $weight;
        }

        $attributes['has_dimensions'] = $hasDimension;

        if ($this->filled($product->unit)) {
            $attributes['unit'] = $product->unit;
        }

        if ($this->filled($product->dimension_publish_status)) {
            $attributes['dimension_publish_status'] = $product->dimension_publish_status;
        }

        // Brand / packaging
        if ($this->filled($product->brand)) {
            $attributes['brand'] = $product->brand;
        }

        if ($this->filled($product->subbrand)) {
            $attributes['subbrand'] = $product->subbrand;
        }

        if ($this->filled($product->packaging_type)) {
            $attributes['packaging_type'] = $product->packaging_type;
        }

        if ($this->filled($product->packaging_size)) {
            $attributes['packaging_size'] = $product->packaging_size;
        }

        if ($this->filled($product->measurement_unit)) {
            $attributes['measurement_unit'] = $product->measurement_unit;
        }

        // Description: prefere description, fallback para reference
        $description = $this->firstFilled($product->description, $product->reference);
        if ($description !== null) {
            $attributes['reference_description'] = $description;
        }

        // Category (nome e slug — category_id omitido: é do tenant, não do landlord)
        $category = $product->category;
        if ($category instanceof Category) {
            if ($this->filled($category->name)) {
                $attributes['category_name'] = $category->name;
            }
            if ($this->filled($category->slug)) {
                $attributes['category_slug'] = $category->slug;
            }
        }

        return $attributes;
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    private function firstFilled(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if ($this->filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
