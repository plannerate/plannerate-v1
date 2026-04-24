<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Workflow\Kanban;

use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorPlannerate\Policies\FlowExecutionPolicy;
use Illuminate\Contracts\Auth\Authenticatable;

class KanbanExecutionPermissionsResolver
{
    /**
     * @param  array<string, bool>  $planogramEditPermissions
     * @return array{
     *     abilities: array<string, bool>|null,
     *     action_visibility: array<string, bool>,
     *     permissions: array<string, bool>
     * }
     */
    public function resolve(
        ?Authenticatable $user,
        FlowExecution $execution,
        ?string $gondolaPlanogramId,
        array $planogramEditPermissions,
        bool $isLastExecutionStep,
    ): array {
        $abilities = $user ? FlowExecutionPolicy::abilities($user, $execution) : null;
        $canFinish = ($abilities['can_finish'] ?? false) && $isLastExecutionStep;
        $canPerformActions = (bool) (
            ($abilities['can_start'] ?? false)
            || ($abilities['can_move'] ?? false)
            || ($abilities['can_pause'] ?? false)
            || ($abilities['can_resume'] ?? false)
            || ($abilities['can_assign'] ?? false)
            || ($abilities['can_abandon'] ?? false)
            || ($abilities['can_notes'] ?? false)
            || $canFinish
        );

        return [
            'abilities' => $abilities,
            'action_visibility' => [
                'start' => $abilities['can_start'] ?? false,
                'move' => $abilities['can_move'] ?? false,
                'pause' => $abilities['can_pause'] ?? false,
                'resume' => $abilities['can_resume'] ?? false,
                'assign' => $abilities['can_assign'] ?? false,
                'abandon' => $abilities['can_abandon'] ?? false,
                'finish' => $canFinish,
                'notes' => $abilities['can_notes'] ?? false,
            ],
            'permissions' => [
                'can_move' => $abilities['can_move'] ?? false,
                'can_perform_actions' => $canPerformActions,
                'can_start_execution' => $abilities['can_start'] ?? false,
                'can_edit_planogram' => $gondolaPlanogramId
                    ? ($planogramEditPermissions[$gondolaPlanogramId] ?? false)
                    : false,
            ],
        ];
    }
}
