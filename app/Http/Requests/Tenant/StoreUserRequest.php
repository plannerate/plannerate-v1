<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\AdministrativeUserLimitService;
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
                Rule::in($this->availableRoleIds()),
                Rule::exists('landlord.roles', 'id')
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('type', RbacType::TENANT)
                        ->whereNull('tenant_id')),
            ],
        ];
    }

    /**
     * IDs dos perfis vinculados ao tenant atual (mais os perfis de sistema
     * sempre disponíveis). Restringe a atribuição ao catálogo do tenant.
     *
     * @return list<string>
     */
    private function availableRoleIds(): array
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return app(AdministrativeUserLimitService::class)->availableRoleIds($tenant);
    }
}
