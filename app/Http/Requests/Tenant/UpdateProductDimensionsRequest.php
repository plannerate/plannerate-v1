<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductDimensionsRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        $product = $this->resolveRouteProduct();

        return $this->user()?->can('update', $product) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'dimension_status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }

    private function resolveRouteProduct(): Product
    {
        $product = $this->route('product');

        if ($product instanceof Product) {
            return $product;
        }

        return Product::query()->whereKey((string) $product)->firstOrFail();
    }
}
