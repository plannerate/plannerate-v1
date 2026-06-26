<?php

namespace App\Http\Requests\Tenant;

use App\Models\WorkflowGondolaExecution;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WorkflowExecutionStoreRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        return $this->user()?->can('start', WorkflowGondolaExecution::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gondola_id' => ['required', 'string'],
            'step_id' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
