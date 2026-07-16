<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Auditoria SOMENTE LEITURA do risco de lockout ao ligar `RBAC_ENABLED`.
 *
 * Enquanto `RBAC_ENABLED=false`, o trait `ChecksRbacPermission::allowByContext`
 * libera tudo e nenhum usuário precisa de papel. Se o flag for ligado, o acesso
 * em contexto de tenant passa a exigir `$user->can(...)` avaliado no `team_id`
 * do tenant — e usuários sem papel/permissão atribuídos naquele tenant seriam
 * bloqueados (403). Hoje NADA no código atribui papéis a usuários de tenant
 * automaticamente (só o super-admin via seeder), então habilitar sem provisionar
 * trancaria todo mundo.
 *
 * Este comando lista, por tenant, os usuários nessa situação, para provisionar
 * os papéis ANTES de habilitar o RBAC. Não escreve nada.
 */
class AuditRbacTenantAccessCommand extends Command
{
    protected $signature = 'rbac:audit-tenant-access
                            {--tenant= : ID ou slug de um tenant específico}';

    protected $description = 'Lista (read-only) usuários que ficariam sem acesso se RBAC_ENABLED fosse ligado (sem papel no tenant)';

    public function handle(): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        if (! (bool) config('permission.rbac_enabled', false)) {
            $this->warn('RBAC_ENABLED está DESLIGADO: hoje todos passam. Este relatório mostra o que aconteceria se fosse ligado.');
            $this->newLine();
        }

        $originalTeamId = getPermissionsTeamId();
        $totalUsers = 0;
        $totalAtRisk = 0;

        try {
            foreach ($tenants as $tenant) {
                try {
                    [$users, $atRisk] = $tenant->execute(fn (): array => $this->auditCurrentTenant($tenant));
                } catch (Throwable $e) {
                    $this->error("✗ [{$tenant->name}] falha ao auditar: {$e->getMessage()}");

                    continue;
                }

                $totalUsers += $users;
                $totalAtRisk += count($atRisk);
                $this->reportTenant($tenant, $users, $atRisk);
            }
        } finally {
            setPermissionsTeamId($originalTeamId);
        }

        $this->newLine();
        $this->info(sprintf(
            'Resumo: %d tenant(s), %d usuário(s) ativo(s), %d em risco de lockout se RBAC for ligado.',
            $tenants->count(),
            $totalUsers,
            $totalAtRisk,
        ));

        if ($totalAtRisk > 0) {
            $this->newLine();
            $this->line('Para provisionar antes de ligar o RBAC: atribua o papel adequado a cada usuário');
            $this->line('no team context do tenant (setPermissionsTeamId($tenant->id) + $user->syncRoles(...)),');
            $this->line('como faz Tenant\\UserController::syncTenantRoles. Super-admins operam em contexto');
            $this->line('landlord (bypass) e podem aparecer aqui sem representar risco real.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    protected function getTenants(): Collection
    {
        $query = Tenant::query()->where('status', 'active')->orderBy('name');

        $ref = $this->option('tenant');
        if (is_string($ref) && $ref !== '') {
            $query->where(fn ($builder) => $builder->whereKey($ref)->orWhere('slug', $ref));
        }

        return $query->get();
    }

    /**
     * Audita o tenant já ativo (dentro de `$tenant->execute()`).
     *
     * @return array{0: int, 1: list<array{name: ?string, email: ?string, id: string}>}
     */
    protected function auditCurrentTenant(Tenant $tenant): array
    {
        setPermissionsTeamId($tenant->getKey());

        /** @var Collection<int, User> $users */
        $users = User::query()->where('is_active', true)->orderBy('name')->get();
        $atRisk = [];

        foreach ($users as $user) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');

            if ($this->wouldBeLockedOut($user)) {
                $atRisk[] = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'id' => (string) $user->getKey(),
                ];
            }
        }

        return [$users->count(), $atRisk];
    }

    /**
     * Ficaria bloqueado em contexto de tenant se, no team atual, não tem NENHUM
     * papel nem permissão direta — pois `$user->can()` avaliaria tudo como falso.
     */
    protected function wouldBeLockedOut(User $user): bool
    {
        return $user->roles()->count() === 0
            && $user->permissions()->count() === 0;
    }

    /**
     * @param  list<array{name: ?string, email: ?string, id: string}>  $atRisk
     */
    protected function reportTenant(Tenant $tenant, int $users, array $atRisk): void
    {
        if ($atRisk === []) {
            $this->info("✓ [{$tenant->name}] {$users} usuário(s), nenhum em risco.");

            return;
        }

        $this->warn("⚠ [{$tenant->name}] ".count($atRisk)." de {$users} usuário(s) SEM papel (seriam bloqueados):");
        foreach ($atRisk as $user) {
            $this->line("   • {$user['name']} <{$user['email']}> [{$user['id']}]");
        }
    }
}
