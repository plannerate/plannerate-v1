<?php

namespace App\Http\Requests\Landlord;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('landlord.roles', 'name')
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->whereNull('tenant_id')),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::exists('landlord.permissions', 'name')
                    ->where(static fn ($query) => $query->where('guard_name', 'web')),
            ],
        ];
    }
}
