<?php

namespace App\Http\Requests\Tenant;

use App\Models\Provider;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderUpdateRequest extends FormRequest
{
    use InteractsWithTenantContext;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Provider|null $provider */
        $provider = $this->route('provider');

        return $provider && ($this->user()?->can('update', $provider) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Provider $provider */
        $provider = $this->route('provider');
        $tenantId = $this->tenantId();

        return [
            'code' => ['nullable', 'string', 'max:255', Rule::unique('providers', 'code')->where('tenant_id', $tenantId)->ignore($provider)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
