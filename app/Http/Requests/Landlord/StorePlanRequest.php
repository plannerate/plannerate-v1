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
            'items' => ['sometimes', 'array'],
            'items.*.key' => ['required', 'string', 'max:100'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.value' => ['nullable', 'string', 'max:500'],
            'items.*.type' => ['required', 'in:integer,boolean,string'],
            'items.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
