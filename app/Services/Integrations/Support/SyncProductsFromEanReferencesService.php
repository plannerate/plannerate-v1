<?php

namespace App\Services\Integrations\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncProductsFromEanReferencesService
{
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
        $matched = $this->countMatchedProducts($tenantConnectionName, $tenantId);

        if ($preview) {
            return [
                'matched' => $matched,
                'updated' => 0,
                'remaining' => $this->countProductsNeedingUpdates($tenantConnectionName, $tenantId),
            ];
        }

        $updated = $connection->getDriverName() === 'sqlite'
            ? $this->syncWithCursor($tenantConnectionName, $tenantId)
            : $this->syncWithBulkUpdate($tenantConnectionName, $tenantId);

        return [
            'matched' => $matched,
            'updated' => $updated,
            'remaining' => $this->countProductsNeedingUpdates($tenantConnectionName, $tenantId),
        ];
    }

    private function countMatchedProducts(string $tenantConnectionName, string $tenantId): int
    {
        return DB::connection($tenantConnectionName)
            ->table('products as p')
            ->join('ean_references as r', function ($join): void {
                $join->on('r.tenant_id', '=', 'p.tenant_id')
                    ->on('r.ean', '=', 'p.ean')
                    ->whereNull('r.deleted_at');
            })
            ->where('p.tenant_id', $tenantId)
            ->whereNotNull('p.ean')
            ->count('p.id');
    }

    private function countProductsNeedingUpdates(string $tenantConnectionName, string $tenantId): int
    {
        return DB::connection($tenantConnectionName)
            ->table('products as p')
            ->join('ean_references as r', function ($join): void {
                $join->on('r.tenant_id', '=', 'p.tenant_id')
                    ->on('r.ean', '=', 'p.ean')
                    ->whereNull('r.deleted_at');
            })
            ->where('p.tenant_id', $tenantId)
            ->where(function ($query): void {
                $this->addNeedsUpdateConditions($query);
            })
            ->count('p.id');
    }

    private function syncWithBulkUpdate(string $tenantConnectionName, string $tenantId): int
    {
        $connection = DB::connection($tenantConnectionName);

        $sql = "
            UPDATE products p
            SET p.category_id = CASE
                    WHEN (p.category_id IS NULL OR p.category_id = '') AND r.category_id IS NOT NULL
                    THEN r.category_id
                    ELSE p.category_id
                END,
                p.description = CASE
                    WHEN (p.description IS NULL OR p.description = '') AND r.reference_description IS NOT NULL AND r.reference_description <> ''
                    THEN r.reference_description
                    ELSE p.description
                END,
                p.brand = CASE
                    WHEN (p.brand IS NULL OR p.brand = '') AND r.brand IS NOT NULL AND r.brand <> ''
                    THEN r.brand
                    ELSE p.brand
                END,
                p.subbrand = CASE
                    WHEN (p.subbrand IS NULL OR p.subbrand = '') AND r.subbrand IS NOT NULL AND r.subbrand <> ''
                    THEN r.subbrand
                    ELSE p.subbrand
                END,
                p.packaging_type = CASE
                    WHEN (p.packaging_type IS NULL OR p.packaging_type = '') AND r.packaging_type IS NOT NULL AND r.packaging_type <> ''
                    THEN r.packaging_type
                    ELSE p.packaging_type
                END,
                p.packaging_size = CASE
                    WHEN (p.packaging_size IS NULL OR p.packaging_size = '') AND r.packaging_size IS NOT NULL AND r.packaging_size <> ''
                    THEN r.packaging_size
                    ELSE p.packaging_size
                END,
                p.measurement_unit = CASE
                    WHEN (p.measurement_unit IS NULL OR p.measurement_unit = '') AND r.measurement_unit IS NOT NULL AND r.measurement_unit <> ''
                    THEN r.measurement_unit
                    ELSE p.measurement_unit
                END,
                p.width = CASE
                    WHEN p.width IS NULL AND r.width IS NOT NULL
                    THEN r.width
                    ELSE p.width
                END,
                p.height = CASE
                    WHEN p.height IS NULL AND r.height IS NOT NULL
                    THEN r.height
                    ELSE p.height
                END,
                p.depth = CASE
                    WHEN p.depth IS NULL AND r.depth IS NOT NULL
                    THEN r.depth
                    ELSE p.depth
                END,
                p.weight = CASE
                    WHEN p.weight IS NULL AND r.weight IS NOT NULL
                    THEN r.weight
                    ELSE p.weight
                END,
                p.unit = CASE
                    WHEN (p.unit IS NULL OR p.unit = '') AND r.unit IS NOT NULL AND r.unit <> ''
                    THEN r.unit
                    ELSE p.unit
                END,
                p.has_dimensions = CASE
                    WHEN (p.width IS NULL OR p.height IS NULL OR p.depth IS NULL)
                    THEN r.has_dimensions
                    ELSE p.has_dimensions
                END,
                p.dimension_status = CASE
                    WHEN (p.dimension_status IS NULL OR p.dimension_status = '') AND r.dimension_status IS NOT NULL AND r.dimension_status <> ''
                    THEN r.dimension_status
                    ELSE p.dimension_status
                END,
                p.updated_at = ?
                        FROM ean_references r
                        WHERE r.tenant_id = p.tenant_id
                            AND r.ean = p.ean
                            AND r.deleted_at IS NULL
                            AND p.tenant_id = ?
              AND (
                  ((p.category_id IS NULL OR p.category_id = '') AND r.category_id IS NOT NULL)
                  OR ((p.description IS NULL OR p.description = '') AND r.reference_description IS NOT NULL AND r.reference_description <> '')
                  OR ((p.brand IS NULL OR p.brand = '') AND r.brand IS NOT NULL AND r.brand <> '')
                  OR ((p.subbrand IS NULL OR p.subbrand = '') AND r.subbrand IS NOT NULL AND r.subbrand <> '')
                  OR ((p.packaging_type IS NULL OR p.packaging_type = '') AND r.packaging_type IS NOT NULL AND r.packaging_type <> '')
                  OR ((p.packaging_size IS NULL OR p.packaging_size = '') AND r.packaging_size IS NOT NULL AND r.packaging_size <> '')
                  OR ((p.measurement_unit IS NULL OR p.measurement_unit = '') AND r.measurement_unit IS NOT NULL AND r.measurement_unit <> '')
                  OR (p.width IS NULL AND r.width IS NOT NULL)
                  OR (p.height IS NULL AND r.height IS NOT NULL)
                  OR (p.depth IS NULL AND r.depth IS NOT NULL)
                  OR (p.weight IS NULL AND r.weight IS NOT NULL)
                  OR ((p.unit IS NULL OR p.unit = '') AND r.unit IS NOT NULL AND r.unit <> '')
                  OR ((p.dimension_status IS NULL OR p.dimension_status = '') AND r.dimension_status IS NOT NULL AND r.dimension_status <> '')
              )
        ";

        return $connection->affectingStatement($sql, [Carbon::now(), $tenantId]);
    }

    private function syncWithCursor(string $tenantConnectionName, string $tenantId): int
    {
        $connection = DB::connection($tenantConnectionName);
        $updated = 0;

        $products = $connection
            ->table('products as p')
            ->join('ean_references as r', function ($join): void {
                $join->on('r.tenant_id', '=', 'p.tenant_id')
                    ->on('r.ean', '=', 'p.ean')
                    ->whereNull('r.deleted_at');
            })
            ->where('p.tenant_id', $tenantId)
            ->select([
                'p.id',
                'p.category_id',
                'p.description',
                'p.brand',
                'p.subbrand',
                'p.packaging_type',
                'p.packaging_size',
                'p.measurement_unit',
                'p.width',
                'p.height',
                'p.depth',
                'p.weight',
                'p.unit',
                'p.has_dimensions',
                'p.dimension_status',
                'r.category_id as reference_category_id',
                'r.reference_description',
                'r.brand as reference_brand',
                'r.subbrand as reference_subbrand',
                'r.packaging_type as reference_packaging_type',
                'r.packaging_size as reference_packaging_size',
                'r.measurement_unit as reference_measurement_unit',
                'r.width as reference_width',
                'r.height as reference_height',
                'r.depth as reference_depth',
                'r.weight as reference_weight',
                'r.unit as reference_unit',
                'r.has_dimensions as reference_has_dimensions',
                'r.dimension_status as reference_dimension_status',
            ])
            ->orderBy('p.id')
            ->cursor();

        foreach ($products as $product) {
            $updates = $this->updatesForProduct($product);

            if ($updates === []) {
                continue;
            }

            $updates['updated_at'] = Carbon::now();

            $connection
                ->table('products')
                ->where('id', (string) $product->id)
                ->update($updates);

            $updated++;
        }

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    private function updatesForProduct(object $product): array
    {
        $updates = [];

        $fields = [
            'category_id' => 'reference_category_id',
            'description' => 'reference_description',
            'brand' => 'reference_brand',
            'subbrand' => 'reference_subbrand',
            'packaging_type' => 'reference_packaging_type',
            'packaging_size' => 'reference_packaging_size',
            'measurement_unit' => 'reference_measurement_unit',
            'width' => 'reference_width',
            'height' => 'reference_height',
            'depth' => 'reference_depth',
            'weight' => 'reference_weight',
            'unit' => 'reference_unit',
            'dimension_status' => 'reference_dimension_status',
        ];

        foreach ($fields as $productField => $referenceField) {
            if ($this->isBlank($product->{$productField} ?? null) && ! $this->isBlank($product->{$referenceField} ?? null)) {
                $updates[$productField] = $product->{$referenceField};
            }
        }

        if (
            ($this->isBlank($product->width ?? null)
                || $this->isBlank($product->height ?? null)
                || $this->isBlank($product->depth ?? null))
            && isset($product->reference_has_dimensions)
        ) {
            $updates['has_dimensions'] = (bool) $product->reference_has_dimensions;
        }

        return $updates;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function addNeedsUpdateConditions($query): void
    {
        $query
            ->where(function ($query): void {
                $query->whereNull('p.category_id')
                    ->orWhere('p.category_id', '');
            })
            ->whereNotNull('r.category_id')
            ->orWhere(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('p.description')
                        ->orWhere('p.description', '');
                })->whereNotNull('r.reference_description')
                    ->where('r.reference_description', '<>', '');
            })
            ->orWhere(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('p.brand')
                        ->orWhere('p.brand', '');
                })->whereNotNull('r.brand')
                    ->where('r.brand', '<>', '');
            })
            ->orWhere(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('p.subbrand')
                        ->orWhere('p.subbrand', '');
                })->whereNotNull('r.subbrand')
                    ->where('r.subbrand', '<>', '');
            })
            ->orWhere(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('p.packaging_type')
                        ->orWhere('p.packaging_type', '');
                })->whereNotNull('r.packaging_type')
                    ->where('r.packaging_type', '<>', '');
            })
            ->orWhere(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('p.packaging_size')
                        ->orWhere('p.packaging_size', '');
                })->whereNotNull('r.packaging_size')
                    ->where('r.packaging_size', '<>', '');
            })
            ->orWhere(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('p.measurement_unit')
                        ->orWhere('p.measurement_unit', '');
                })->whereNotNull('r.measurement_unit')
                    ->where('r.measurement_unit', '<>', '');
            })
            ->orWhere(function ($query): void {
                $query->whereNull('p.width')
                    ->whereNotNull('r.width');
            })
            ->orWhere(function ($query): void {
                $query->whereNull('p.height')
                    ->whereNotNull('r.height');
            })
            ->orWhere(function ($query): void {
                $query->whereNull('p.depth')
                    ->whereNotNull('r.depth');
            })
            ->orWhere(function ($query): void {
                $query->whereNull('p.weight')
                    ->whereNotNull('r.weight');
            })
            ->orWhere(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('p.unit')
                        ->orWhere('p.unit', '');
                })->whereNotNull('r.unit')
                    ->where('r.unit', '<>', '');
            })
            ->orWhere(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('p.dimension_status')
                        ->orWhere('p.dimension_status', '');
                })->whereNotNull('r.dimension_status')
                    ->where('r.dimension_status', '<>', '');
            });
    }
}
