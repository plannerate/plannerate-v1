<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $product && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        $tenantId = $this->tenantId();

        return [
            'category_id' => ['nullable', 'ulid', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->where('tenant_id', $tenantId)->ignore($product)],
            'ean' => ['nullable', 'string', 'max:255', Rule::unique('products', 'ean')->where('tenant_id', $tenantId)->ignore($product)],
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
            'dimensions_ean' => ['nullable', 'string', 'max:13'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
            'dimensions_status' => ['required', Rule::in(['draft', 'published'])],
            'dimensions_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function tenantId(): ?string
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (! app()->bound($containerKey)) {
            return null;
        }

        return app($containerKey)?->getKey();
    }
}
