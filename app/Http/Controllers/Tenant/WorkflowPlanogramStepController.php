<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\WorkflowPlanogramSettingsUpdateRequest;
use App\Models\Planogram;
use App\Models\User;
use App\Models\WorkflowPlanogramStep;
use App\Services\WorkflowPlanogramStepService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class WorkflowPlanogramStepController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(private readonly WorkflowPlanogramStepService $stepService) {}

    public function index(string $subdomain, Planogram $planogram): JsonResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogram);

        $steps = $this->stepService->syncForPlanogram($planogram);

        return response()->json([
            'steps' => $this->transformSettingsSteps($steps),
            'users' => $this->usersForSelect(),
        ]);
    }

    public function update(WorkflowPlanogramSettingsUpdateRequest $request, string $subdomain, Planogram $planogram): JsonResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogram);

        $steps = $this->stepService->updateSettings($planogram, $request->validated('steps', []));

        return response()->json([
            'steps' => $this->transformSettingsSteps($steps),
            'users' => $this->usersForSelect(),
        ]);
    }

    public function loadDefaults(string $subdomain, Planogram $planogram): JsonResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogram);

        $steps = $this->stepService->loadDefaultSettingsForPlanogram($planogram);

        return response()->json([
            'steps' => $this->transformSettingsSteps($steps),
            'users' => $this->usersForSelect(),
        ]);
    }

    /**
     * @param  Collection<int, WorkflowPlanogramStep>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function transformSettingsSteps(Collection $steps): array
    {
        return $steps
            ->map(fn (WorkflowPlanogramStep $step): array => [
                'id' => $step->id,
                'workflow_template_id' => $step->workflow_template_id,
                'name' => $step->name,
                'description' => $step->description,
                'estimated_duration_days' => $step->estimated_duration_days,
                'role_id' => $step->role_id,
                'is_required' => (bool) $step->is_required,
                'is_skipped' => (bool) $step->is_skipped,
                'status' => $step->status,
                'suggested_order' => $step->suggested_order,
                'selected_user_ids' => $step->availableUsers->pluck('id')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function usersForSelect(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }
}
