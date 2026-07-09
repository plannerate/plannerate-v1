<?php

namespace App\Http\Requests\Landlord;

use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Plan::class) ?? false;
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
            'slug' => ['required', 'string', 'max:255', Rule::unique('landlord.plans', 'slug')],
            'description' => ['nullable', 'string'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'user_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'role_limits' => ['sometimes', 'array'],
            'role_limits.*' => ['nullable', 'integer', 'min:1'],
            'items' => ['sometimes', 'array'],
            'items.*.key' => ['required', 'string', 'max:100'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.value' => ['nullable', 'string', 'max:500'],
            'items.*.type' => ['required', 'in:integer,boolean,string'],
            'items.*.is_active' => ['sometimes', 'boolean'],
            'items.*.limit_message' => ['nullable', 'string', 'max:500'],
            'items.*.upgrade_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * Normaliza limites por perfil vazios ("") para null (= ilimitado).
     */
    protected function prepareForValidation(): void
    {
        $roleLimits = $this->input('role_limits');

        if (is_array($roleLimits)) {
            $this->merge([
                'role_limits' => array_map(
                    static fn ($value) => ($value === '' || $value === null) ? null : $value,
                    $roleLimits,
                ),
            ]);
        }
    }
}
