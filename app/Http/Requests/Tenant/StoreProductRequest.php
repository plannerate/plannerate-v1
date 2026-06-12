<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Tenant\Concerns\BuildsProductRules;
use App\Models\Product;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use BuildsProductRules, InteractsWithTenantContext;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->buildProductRules();
    }
}
