<?php

namespace App\Http\Requests\Landlord;

use App\Models\User;
use App\Support\Authorization\RbacType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('landlord.users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => [
                'string',
                'distinct',
                Rule::exists('landlord.roles', 'id')
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('type', RbacType::LANDLORD)
                        ->whereNull('tenant_id')),
            ],
        ];
    }
}
