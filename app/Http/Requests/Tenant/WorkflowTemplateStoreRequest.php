<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowTemplateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'suggested_order' => ['integer', 'min:0'],
            'estimated_duration_days' => ['nullable', 'integer', 'min:1'],
            'default_role_id' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_required_by_default' => ['boolean'],
            'template_next_step_id' => ['nullable', 'string', Rule::exists('workflow_templates', 'id')],
            'template_previous_step_id' => ['nullable', 'string', Rule::exists('workflow_templates', 'id')],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['string'],
        ];
    }
}
