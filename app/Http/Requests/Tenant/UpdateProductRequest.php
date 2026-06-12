<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Tenant\Concerns\BuildsProductRules;
use App\Models\Product;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    use BuildsProductRules, InteractsWithTenantContext;

    private ?Product $routeProduct = null;

    public function authorize(): bool
    {
        $product = $this->resolvedRouteProduct();

        return $this->user()?->can('update', $product) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Mesmas regras do store; uniques de slug/ean ignoram o produto da rota
        return $this->buildProductRules($this->resolvedRouteProduct());
    }

    private function resolvedRouteProduct(): Product
    {
        return $this->routeProduct ??= $this->resolveRouteProduct();
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
