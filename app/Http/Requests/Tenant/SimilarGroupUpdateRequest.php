<?php

namespace App\Http\Requests\Tenant;

use App\Models\SimilarGroup;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimilarGroupUpdateRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        /** @var SimilarGroup|null $similarGroup */
        $similarGroup = $this->route('similar_group');

        return $similarGroup && ($this->user()?->can('update', $similarGroup) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var SimilarGroup $similarGroup */
        $similarGroup = $this->route('similar_group');
        $similarGroupsTable = $this->tenantTable('similar_groups');
        $productsTable = $this->tenantTable('products');
        $tenantId = $this->tenantId();

        return [
            'grouper_code' => ['required', 'string', 'max:255', Rule::unique($similarGroupsTable, 'grouper_code')->where('tenant_id', $tenantId)->ignore($similarGroup)],
            'name' => ['required', 'string', 'max:255'],
            'product_codes' => ['nullable', 'array'],
            'product_codes.*' => ['string', 'max:255'],
            'product_ids' => ['required', 'array', 'min:2'],
            'product_ids.*' => ['ulid', Rule::exists($productsTable, 'id')->where('tenant_id', $tenantId)],
            'dimension_source_product_id' => ['nullable', 'ulid', Rule::exists($productsTable, 'id')->where('tenant_id', $tenantId)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'description' => ['nullable', 'string'],
            'apply_dimensions' => ['sometimes', 'boolean'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'dimension_status' => ['nullable', Rule::in(['draft', 'published'])],
        ];
    }
}
