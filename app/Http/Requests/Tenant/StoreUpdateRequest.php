<?php

namespace App\Http\Requests\Tenant;

use App\Models\Store;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUpdateRequest extends FormRequest
{
    use InteractsWithTenantContext;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Store|null $store */
        $store = $this->route('store');

        return $store && ($this->user()?->can('update', $store) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Store $store */
        $store = $this->route('store');
        $tenantId = $this->tenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'slug')->where('tenant_id', $tenantId)->ignore($store)],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'code')->where('tenant_id', $tenantId)->ignore($store)],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
