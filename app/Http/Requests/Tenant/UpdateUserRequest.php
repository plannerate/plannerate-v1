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

class UpdateUserRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user && ($this->user()?->can('update', $user) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');
        $usersTable = $this->tenantTable('users');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($usersTable, 'email')->ignore($user)->whereNull('deleted_at')],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
