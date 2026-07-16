<?php

namespace App\Support\Workflow;

use App\Enums\GondolaEditDecision;
use App\Enums\WorkflowExecutionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Support\Authorization\PermissionName;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;

/**
 * Decisão única de acesso à edição de uma gôndola.
 *
 * Regra de negócio (quando o módulo Kanban está ativo no tenant): a gôndola só
 * pode ser editada se tiver uma execução de workflow ATIVA, iniciada pelo
 * próprio usuário (execution_started_by), cuja etapa atual permita edição
 * (access_mode). Fora do Kanban, mantém o comportamento legado (edição livre).
 *
 * Reutilizada pela página do editor (App\Http\Controllers\Tenant\Editor\
 * EditorPlanogramController) e pelo middleware das APIs de escrita
 * (App\Http\Middleware\EnsureCanEditGondola), evitando divergência entre as
 * checagens de leitura e de gravação.
 */
class GondolaEditGate
{
    public function __construct(private TenantModuleService $modules) {}

    public function decide(User $user, string $gondolaId): GondolaEditDecision
    {
        if (! $this->kanbanActive()) {
            return GondolaEditDecision::Allowed;
        }

        if (! $user->can(PermissionName::TENANT_GONDOLAS_UPDATE)) {
            return GondolaEditDecision::Forbidden;
        }

        $execution = $this->activeExecutionFor($gondolaId);

        if ($execution === null) {
            return GondolaEditDecision::NotStarted;
        }

        if ((string) $execution->execution_started_by !== (string) $user->id) {
            return GondolaEditDecision::NotOwner;
        }

        if (! $execution->allowsEditing()) {
            return GondolaEditDecision::ReadOnlyStep;
        }

        return GondolaEditDecision::Allowed;
    }

    public function kanbanActive(): bool
    {
        $tenant = $this->resolveTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $this->modules->tenantHasActiveModule($tenant, ModuleSlug::KANBAN);
    }

    private function activeExecutionFor(string $gondolaId): ?WorkflowGondolaExecution
    {
        return WorkflowGondolaExecution::query()
            ->where('gondola_id', $gondolaId)
            ->where('status', WorkflowExecutionStatus::Active)
            ->with(['step:id,workflow_template_id,access_mode', 'step.template:id,access_mode'])
            ->orderByDesc('started_at')
            ->first();
    }

    private function resolveTenant(): ?Tenant
    {
        if (app()->bound('tenant')) {
            $tenant = app('tenant');

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        $current = Tenant::current();

        if ($current instanceof Tenant) {
            return $current;
        }

        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (! app()->bound($containerKey)) {
            return null;
        }

        $resolved = app($containerKey);

        return $resolved instanceof Tenant ? $resolved : null;
    }
}
