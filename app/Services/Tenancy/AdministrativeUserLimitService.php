<?php

namespace App\Services\Tenancy;

use App\Http\Controllers\Landlord\TenantUserAccessController;
use App\Http\Controllers\Tenant\UserController;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\RbacType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verificação central e reutilizável do limite de usuários por perfil administrativo.
 *
 * "Perfil administrativo" = role de tenant marcada com `is_administrative = true`.
 * O valor do limite de cada perfil vem do plano do tenant ({@see Tenant::roleUserLimit()}):
 * - "tenant-admin" mantém o limite legado (plans.user_limit / plan_item "user_limit");
 * - demais perfis usam o plan_item "user_limit:{system_name}".
 *
 * Esta classe é a única fonte da regra — consumida pelo cadastro de usuários no
 * tenant ({@see UserController}) e pela tela de acesso
 * no landlord ({@see TenantUserAccessController}).
 */
class AdministrativeUserLimitService
{
    /**
     * Perfis de sistema que estão SEMPRE disponíveis para qualquer tenant,
     * independentemente do vínculo no pivot role_tenant. Rede de segurança
     * para nunca deixar um tenant sem como criar administradores.
     *
     * @var list<string>
     */
    private const ALWAYS_AVAILABLE_SYSTEM_NAMES = ['tenant-admin'];

    /**
     * Cache por request dos perfis administrativos.
     *
     * @var Collection<int, Role>|null
     */
    private ?Collection $administrativeRoles = null;

    /**
     * Perfis (roles de tenant) marcados como administrativos.
     *
     * @return Collection<int, Role>
     */
    public function administrativeRoles(): Collection
    {
        if ($this->administrativeRoles instanceof Collection) {
            return $this->administrativeRoles;
        }

        return $this->administrativeRoles = $this->tenantRolesQuery()
            ->where(function ($query): void {
                // "tenant-admin" é sempre administrativo (independe da flag), para
                // preservar o limite legado mesmo se alguém desmarcar a coluna.
                $query->where('is_administrative', true)
                    ->orWhere('system_name', 'tenant-admin');
            })
            ->get();
    }

    /**
     * Indica se o perfil é administrativo (sujeito a limite).
     */
    public function isAdministrative(Role $role): bool
    {
        return (bool) $role->is_administrative || $role->system_name === 'tenant-admin';
    }

    /**
     * Limite de usuários do perfil dentro do plano do tenant.
     *
     * @return int|null null = ilimitado/não configurado.
     */
    public function limitFor(Tenant $tenant, Role $role): ?int
    {
        return $tenant->roleUserLimit($role->system_name);
    }

    /**
     * Quantidade de usuários DISTINTOS do tenant que possuem o perfil.
     */
    public function countUsersWithRole(Tenant $tenant, Role $role): int
    {
        return DB::connection('landlord')
            ->table('model_has_roles')
            ->where('role_id', $role->getKey())
            ->where('model_type', User::class)
            ->where('tenant_id', $tenant->getKey())
            ->distinct()
            ->count('model_id');
    }

    /**
     * Garante que a atribuição dos perfis não ultrapassa os limites do plano.
     *
     * Considera apenas perfis administrativos RECÉM-adicionados (presentes em
     * $assignedRoleIds e ausentes em $currentRoleIds): um usuário que já possui o
     * perfil não reconsome vaga ao ser editado.
     *
     * @param  list<string>  $assignedRoleIds
     * @param  list<string>  $currentRoleIds
     *
     * @throws ValidationException
     */
    public function ensureCanAssign(Tenant $tenant, array $assignedRoleIds, array $currentRoleIds = []): void
    {
        $newlyAssignedIds = array_values(array_diff($assignedRoleIds, $currentRoleIds));

        if ($newlyAssignedIds === []) {
            return;
        }

        foreach ($this->administrativeRoles() as $role) {
            if (! in_array($role->getKey(), $newlyAssignedIds, true)) {
                continue;
            }

            $isTenantAdmin = $role->system_name === 'tenant-admin';
            $limit = $this->limitFor($tenant, $role);

            // tenant-admin preserva o erro "sem limite de plano" do fluxo legado:
            // sem plano ou sem limite configurado bloqueia a criação.
            if ($isTenantAdmin && ($tenant->plan === null || $limit === null)) {
                throw ValidationException::withMessages([
                    'limit' => __('app.landlord.tenant_access.messages.no_plan_limit'),
                ]);
            }

            // Demais perfis sem limite configurado = ilimitado (não bloqueia).
            if ($limit === null) {
                continue;
            }

            if ($this->countUsersWithRole($tenant, $role) >= $limit) {
                throw ValidationException::withMessages([
                    'limit' => $isTenantAdmin
                        ? __('app.landlord.tenant_access.messages.limit_reached')
                        : __('app.landlord.tenant_access.messages.role_limit_reached', ['role' => $role->name]),
                ]);
            }
        }
    }

    /**
     * Lista de perfis (tenant) para os selects/checkboxes de usuário, já com o
     * estado de limite por perfil para o front desabilitar quando atingido.
     *
     * @return list<array{id:string,name:string,is_admin:bool,limit:int|null,count:int,limit_reached:bool}>
     */
    public function rolesForSelect(Tenant $tenant): array
    {
        return $this->availableRolesQuery($tenant)
            ->get()
            ->map(function (Role $role) use ($tenant): array {
                $isAdmin = $this->isAdministrative($role);
                $limit = $isAdmin ? $this->limitFor($tenant, $role) : null;
                $count = $isAdmin ? $this->countUsersWithRole($tenant, $role) : 0;

                return [
                    'id' => (string) $role->getKey(),
                    'name' => $role->name,
                    'is_admin' => $isAdmin,
                    'limit' => $limit,
                    'count' => $count,
                    'limit_reached' => $limit !== null && $count >= $limit,
                ];
            })
            ->all();
    }

    /**
     * IDs dos perfis (tenant) disponíveis para o tenant informado.
     *
     * @return list<string>
     */
    public function availableRoleIds(Tenant $tenant): array
    {
        return $this->availableRolesQuery($tenant)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * Nomes dos perfis (tenant) disponíveis para o tenant informado.
     *
     * @return list<string>
     */
    public function availableRoleNames(Tenant $tenant): array
    {
        return $this->availableRolesQuery($tenant)
            ->pluck('name')
            ->all();
    }

    /**
     * Query dos perfis globais de tenant DISPONÍVEIS para o tenant: os
     * vinculados pelo pivot role_tenant, mais os perfis de sistema sempre
     * disponíveis ({@see self::ALWAYS_AVAILABLE_SYSTEM_NAMES}).
     */
    private function availableRolesQuery(Tenant $tenant): Builder
    {
        return $this->tenantRolesQuery()
            ->where(function (Builder $query) use ($tenant): void {
                $query
                    ->whereIn('id', function ($subQuery) use ($tenant): void {
                        $subQuery
                            ->select('role_id')
                            ->from('role_tenant')
                            ->where('tenant_id', $tenant->getKey());
                    })
                    ->orWhereIn('system_name', self::ALWAYS_AVAILABLE_SYSTEM_NAMES);
            });
    }

    /**
     * Resumo do perfil administrativo legado (tenant-admin) para os cards/props
     * que exibem "limite do plano" e a barra de uso.
     *
     * @return array{plan_user_limit:int|null,users_count:int,limit_message:string|null}
     */
    public function tenantAdminSummary(Tenant $tenant): array
    {
        $role = $this->tenantAdminRole();
        $limit = $tenant->plan_user_limit;
        $count = $role instanceof Role ? $this->countUsersWithRole($tenant, $role) : 0;
        $reached = $limit !== null && $tenant->plan !== null && $count >= $limit;

        return [
            'plan_user_limit' => $limit,
            'users_count' => $count,
            'limit_message' => $reached ? __('app.landlord.tenant_access.messages.limit_reached') : null,
        ];
    }

    /**
     * Query base dos perfis globais de tenant (guard web, tenant_id null).
     */
    private function tenantRolesQuery(): Builder
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', RbacType::TENANT)
            ->orderBy('name');
    }

    private function tenantAdminRole(): ?Role
    {
        return $this->tenantRolesQuery()
            ->where('system_name', 'tenant-admin')
            ->first();
    }
}
