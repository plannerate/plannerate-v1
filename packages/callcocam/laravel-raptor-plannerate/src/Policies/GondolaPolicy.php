<?php

namespace Callcocam\LaravelRaptorPlannerate\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;

/**
 * Policy da classe de modelo do PACOTE (`Callcocam\...\Models\Gondola`).
 *
 * Difere de `App\Policies\GondolaPolicy` apenas no `view()`: quando o módulo
 * Kanban está ativo, exige que o usuário seja o RESPONSÁVEL ATUAL de uma execução
 * iniciada (`current_responsible_id`). Isso é intencional e serve às telas de
 * LEITURA que instanciam o modelo do pacote — relatório de geração, página de
 * proposta de reotimização e banner de pendência — para acompanhar handoffs de
 * revisão (o revisor atual vê o relatório).
 *
 * NÃO é o portão de EDIÇÃO do editor: a edição é controlada por
 * `App\Support\Workflow\GondolaEditGate` (regra "quem iniciou", via
 * `execution_started_by`). A diferença de campo entre as duas (responsável atual
 * vs. quem iniciou) é proposital, não uma inconsistência.
 */
class GondolaPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_VIEW_ANY);
    }

    public function view(User $user, Gondola $gondola): bool
    {
        if (! $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_VIEW)) {
            return false;
        }

        $tenant = $this->resolveTenant();

        if (! $tenant instanceof Tenant) {
            return true;
        }

        $hasKanban = app(TenantModuleService::class)
            ->tenantHasActiveModule($tenant, ModuleSlug::KANBAN);

        if (! $hasKanban) {
            return true;
        }

        return WorkflowGondolaExecution::query()
            ->where('gondola_id', $gondola->id)
            ->where('current_responsible_id', $user->id)
            ->whereNotNull('execution_started_by')
            ->whereNotNull('started_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_CREATE);
    }

    public function update(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_UPDATE);
    }

    public function delete(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_DELETE);
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
