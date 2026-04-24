<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Concerns;

use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\User;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Spatie\Permission\Models\Role;

trait ValidatesWorkflowPermissions
{
    protected function hasUsersForRole(string $roleSlug): bool
    {
        return User::whereHas('roles', function ($query) use ($roleSlug) {
            $query->where('name', $roleSlug);
        })->exists();
    }

    /**
     * Verifica se há usuários disponíveis para todas as etapas (FlowConfigStep) do planograma.
     *
     * @return array<string> Array de erros, vazio se OK
     */
    protected function validatePlanogramWorkflowRoles(Planogram $planogram): array
    {
        $errors = [];

        $steps = FlowConfigStep::query()
            ->whereIn('configurable_type', WorkflowMorphMap::planogramWorkflowTypes())
            ->where('configurable_id', $planogram->id)
            ->with('stepTemplate')
            ->orderBy('order')
            ->get();

        if ($steps->isEmpty()) {
            return [];
        }

        foreach ($steps as $step) {
            $stepName = $step->stepTemplate?->name ?? $step->name ?? 'Etapa';
            $roleId = $step->default_role_id;
            if (! $roleId) {
                $errors[] = "A etapa '{$stepName}' não tem role responsável definida.";

                continue;
            }
            $role = Role::find($roleId);
            if (! $role) {
                continue;
            }
            if (! $this->hasUsersForRole($role->name)) {
                $errors[] = "A etapa '{$stepName}' tem a role '{$role->name}', mas não existe usuário com essa role.";
            }
        }

        return $errors;
    }
}
