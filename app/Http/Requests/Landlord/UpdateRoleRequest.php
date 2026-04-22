<?php

namespace App\Http\Requests\Landlord;

use App\Models\Role;
use App\Support\Authorization\RbacType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role && ($this->user()?->can('update', $role) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');
        $type = (string) $this->input('type', (string) $role?->type);

        return [
            'type' => ['required', 'string', Rule::in(RbacType::all())],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('landlord.roles', 'name')
                    ->ignore($role?->id)
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('type', $type)
                        ->whereNull('tenant_id')),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::exists('landlord.permissions', 'name')
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('type', $type)),
            ],
        ];
    }
}
