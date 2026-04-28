<?php

namespace App\Http\Requests\Tenant;

use App\Models\WorkflowGondolaExecution;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowExecutionAssignRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        /** @var WorkflowGondolaExecution|null $execution */
        $execution = $this->route('execution');

        return $execution !== null && ($this->user()?->can('manage', $execution) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usersTable = $this->tenantTable('users');

        return [
            'user_id' => ['required', 'ulid', Rule::exists($usersTable, 'id')],
        ];
    }
}
