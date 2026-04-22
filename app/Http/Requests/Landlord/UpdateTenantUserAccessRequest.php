<?php

namespace App\Http\Requests\Landlord;

use App\Models\Tenant;
use App\Support\Authorization\RbacType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantUserAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');

        return $tenant && ($this->user()?->can('update', $tenant) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', Rule::exists('landlord.users', 'id')],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::exists('landlord.roles', 'name')
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('type', RbacType::TENANT)
                        ->whereNull('tenant_id')),
            ],
        ];
    }
}
