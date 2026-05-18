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
        $tenantId = $this->tenantId();

        return [
            'grouper_code' => ['required', 'string', 'max:255', Rule::unique($similarGroupsTable, 'grouper_code')->where('tenant_id', $tenantId)->ignore($similarGroup)],
            'name' => ['required', 'string', 'max:255'],
            'product_codes' => ['nullable', 'array'],
            'product_codes.*' => ['string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'description' => ['nullable', 'string'],
        ];
    }
}
