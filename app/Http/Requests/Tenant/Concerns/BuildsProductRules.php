<?php

namespace App\Http\Requests\Tenant\Concerns;

use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * Fonte única das regras de validação de produto.
 *
 * Store e Update compartilhavam ~70 linhas idênticas de regras; a única
 * diferença real é o ->ignore($product) nos uniques de slug/ean do update.
 * A classe que usa este trait precisa de InteractsWithTenantContext
 * (tenantId/tenantTable).
 */
trait BuildsProductRules
{
    /**
     * Regras completas de produto; passe o produto da rota para que os
     * uniques de slug/ean o ignorem (caso update).
     *
     * @return array<string, mixed>
     */
    protected function buildProductRules(?Product $ignoreProduct = null): array
    {
        $tenantId = $this->tenantId();
        $categoriesTable = $this->tenantTable('categories');
        $productsTable = $this->tenantTable('products');
        $storesTable = $this->tenantTable('stores');

        $slugUnique = Rule::unique($productsTable, 'slug')->where('tenant_id', $tenantId);
        $eanUnique = Rule::unique($productsTable, 'ean')->where('tenant_id', $tenantId);

        if ($ignoreProduct !== null) {
            $slugUnique->ignore($ignoreProduct);
            $eanUnique->ignore($ignoreProduct);
        }

        return [
            'category_id' => ['nullable', 'ulid', Rule::exists($categoriesTable, 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $slugUnique],
            'ean' => ['nullable', 'string', 'max:255', $eanUnique],
            'codigo_erp' => ['nullable', 'string', 'max:255'],
            'stackable' => ['sometimes', 'boolean'],
            'perishable' => ['sometimes', 'boolean'],
            'flammable' => ['sometimes', 'boolean'],
            'hangable' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'sales_status' => ['nullable', 'string', 'max:255'],
            'sales_purchases' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'synced', 'error'])],
            'sync_source' => ['nullable', 'string', 'max:255'],
            'sync_at' => ['nullable', 'date'],
            'last_purchase_date' => ['nullable', 'date'],
            'no_sales' => ['sometimes', 'boolean'],
            'no_purchases' => ['sometimes', 'boolean'],
            'url' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'fragrance' => ['nullable', 'string', 'max:255'],
            'flavor' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'subbrand' => ['nullable', 'string', 'max:255'],
            'packaging_type' => ['nullable', 'string', 'max:255'],
            'packaging_size' => ['nullable', 'string', 'max:255'],
            'measurement_unit' => ['nullable', 'string', 'max:255'],
            'packaging_content' => ['nullable', 'string', 'max:255'],
            'unit_measure' => ['nullable', 'string', 'max:255'],
            'auxiliary_description' => ['nullable', 'string', 'max:255'],
            'additional_information' => ['nullable', 'string', 'max:255'],
            'sortiment_attribute' => ['nullable', 'string', 'max:255'],
            'sortiment_attribute_levels' => ['nullable', 'string', 'max:255'],
            'dimensions_ean' => ['nullable', 'string', 'max:13'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
            'dimension_publish_status' => ['required', Rule::in(['draft', 'published'])],
            'dimensions_description' => ['nullable', 'string', 'max:255'],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['ulid', Rule::exists($storesTable, 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
