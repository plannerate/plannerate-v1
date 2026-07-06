<?php

namespace App\Http\Requests\Tenant;

use App\Models\User;
use App\Support\Authorization\RbacType;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usersTable = $this->tenantTable('users');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($usersTable, 'email')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => [
                'string',
                'distinct',
                Rule::exists('landlord.roles', 'id')
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('type', RbacType::TENANT)
                        ->whereNull('tenant_id')),
            ],
        ];
    }
}
