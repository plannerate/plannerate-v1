<?php

namespace App\Http\Requests\Tenant;

use App\Models\Cluster;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClusterUpdateRequest extends FormRequest
{
    use InteractsWithTenantContext;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Cluster|null $cluster */
        $cluster = $this->route('cluster');

        return $cluster && ($this->user()?->can('update', $cluster) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Cluster $cluster */
        $cluster = $this->route('cluster');
        $tenantId = $this->tenantId();
        $storesTable = $this->tenantTable('stores');
        $clustersTable = $this->tenantTable('clusters');

        return [
            'store_id' => ['required', 'ulid', Rule::exists($storesTable, 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'specification_1' => ['nullable', 'string', 'max:255'],
            'specification_2' => ['nullable', 'string', 'max:255'],
            'specification_3' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique($clustersTable, 'slug')->where('tenant_id', $tenantId)->ignore($cluster)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
