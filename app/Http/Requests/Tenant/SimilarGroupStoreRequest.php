<?php

namespace App\Http\Requests\Tenant;

use App\Models\SimilarGroup;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimilarGroupStoreRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        return $this->user()?->can('create', SimilarGroup::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $similarGroupsTable = $this->tenantTable('similar_groups');
        $tenantId = $this->tenantId();

        return [
            'grouper_code' => ['required', 'string', 'max:255', Rule::unique($similarGroupsTable, 'grouper_code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'product_codes' => ['nullable', 'array'],
            'product_codes.*' => ['string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'description' => ['nullable', 'string'],
        ];
    }
}
