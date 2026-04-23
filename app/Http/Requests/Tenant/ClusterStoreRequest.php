<?php

namespace App\Http\Requests\Tenant;

use App\Models\Cluster;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClusterStoreRequest extends FormRequest
{
    use InteractsWithTenantContext;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Cluster::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'store_id' => ['required', 'ulid', Rule::exists('stores', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'specification_1' => ['nullable', 'string', 'max:255'],
            'specification_2' => ['nullable', 'string', 'max:255'],
            'specification_3' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('clusters', 'slug')->where('tenant_id', $tenantId)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
