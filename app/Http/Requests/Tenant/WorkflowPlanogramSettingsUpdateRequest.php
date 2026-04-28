<?php

namespace App\Http\Requests\Tenant;

use App\Models\Planogram;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowPlanogramSettingsUpdateRequest extends FormRequest
{
    use InteractsWithTenantContext;

    public function authorize(): bool
    {
        /** @var Planogram|null $planogram */
        $planogram = $this->route('planogram');

        return $planogram !== null && ($this->user()?->can('update', $planogram) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Planogram $planogram */
        $planogram = $this->route('planogram');
        $workflowPlanogramStepsTable = $this->tenantTable('workflow_planogram_steps');
        $usersTable = $this->tenantTable('users');

        return [
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_id' => [
                'required',
                'ulid',
                Rule::exists($workflowPlanogramStepsTable, 'id')
                    ->where('planogram_id', $planogram->id)
                    ->where('tenant_id', $this->tenantId()),
            ],
            'steps.*.is_required' => ['required', 'boolean'],
            'steps.*.is_skipped' => ['required', 'boolean'],
            'steps.*.estimated_duration_days' => ['nullable', 'integer', 'min:0'],
            'steps.*.user_ids' => ['nullable', 'array'],
            'steps.*.user_ids.*' => ['ulid', Rule::exists($usersTable, 'id')],
        ];
    }
}
