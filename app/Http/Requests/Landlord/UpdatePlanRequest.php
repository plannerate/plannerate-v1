<?php

namespace App\Http\Requests\Landlord;

use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Plan|null $plan */
        $plan = $this->route('plan');

        return $plan && ($this->user()?->can('update', $plan) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Plan $plan */
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('landlord.plans', 'slug')->ignore($plan)],
            'description' => ['nullable', 'string'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'user_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['sometimes', 'nullable', 'string'],
            'items.*.key' => ['required', 'string', 'max:100'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.value' => ['nullable', 'string', 'max:500'],
            'items.*.type' => ['required', 'in:integer,boolean,string'],
            'items.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
