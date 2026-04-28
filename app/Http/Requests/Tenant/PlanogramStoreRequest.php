<?php

namespace App\Http\Requests\Tenant;

use App\Models\Planogram;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanogramStoreRequest extends FormRequest
{
    use InteractsWithTenantContext;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Planogram::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $storesTable = $this->tenantTable('stores');
        $clustersTable = $this->tenantTable('clusters');
        $planogramsTable = $this->tenantTable('planograms');
        $categoriesTable = $this->tenantTable('categories');

        return [
            'template_id' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'ulid', Rule::exists($storesTable, 'id')->where('tenant_id', $tenantId)],
            'cluster_id' => ['nullable', 'ulid', Rule::exists($clustersTable, 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique($planogramsTable, 'slug')->where('tenant_id', $tenantId)],
            'type' => ['required', Rule::in(['realograma', 'planograma'])],
            'category_id' => ['nullable', 'ulid', Rule::exists($categoriesTable, 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }
}
