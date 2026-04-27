<?php

namespace App\Http\Requests\Tenant;

use App\Models\Store;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoreRequest extends FormRequest
{
    use InteractsWithTenantContext;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Store::class) ?? false;
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
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'slug')->where('tenant_id', $tenantId)],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'code')->where('tenant_id', $tenantId)],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'description' => ['nullable', 'string', 'max:255'],
            'map' => ['nullable', 'array'],
            'map.image' => ['nullable', 'string'],
            'map.regions' => ['nullable'],
            'address' => ['nullable', 'array'],
            'address.id' => ['nullable', 'ulid'],
            'address.type' => ['nullable', 'string', 'max:255'],
            'address.name' => ['nullable', 'string', 'max:255'],
            'address.zip_code' => ['nullable', 'string', 'max:15'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:255'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.reference' => ['nullable', 'string', 'max:255'],
            'address.additional_information' => ['nullable', 'string', 'max:255'],
            'address.district' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.country' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'size:2'],
            'address.status' => ['nullable', Rule::in(['draft', 'published'])],
            'address.is_default' => ['sometimes', 'boolean'],
        ];
    }
}
