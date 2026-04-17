<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class TenantLimitService
{
    /**
     * Verifica se o tenant atingiu o limite de um recurso.
     * Lança ValidationException se o limite foi atingido.
     *
     * @throws ValidationException
     */
    public function enforce(string $limitKey, int $currentCount, string $label): void
    {
        $tenant = app('current.tenant');
        $limit = (int) data_get($tenant?->settings, "limits.{$limitKey}", 0);

        if ($limit > 0 && $currentCount >= $limit) {
            throw ValidationException::withMessages([
                'name' => "Limite de {$limit} {$label} atingido para este tenant.",
            ]);
        }
    }

    /**
     * Verifica limite de usuários com base nos roles atribuídos no request.
     *
     * Se algum dos roles informados for "admin" (special = true), aplica max_admins.
     * Caso contrário, aplica max_users.
     *
     * @param  array<int|string>  $roleIds  IDs dos roles sendo atribuídos ao novo usuário.
     * @param  string  $userModel  Classe do model de usuário.
     *
     * @throws ValidationException
     */
    public function enforceByRoles(array $roleIds, string $userModel): void
    {
        if (! app()->bound('current.tenant')) {
            return;
        }

        $tenant = app('current.tenant');
        $roleModel = config('raptor.shinobi.models.role', \Callcocam\LaravelRaptor\Models\Role::class);

        $isAdmin = ! empty($roleIds) && $roleModel::whereIn('id', $roleIds)
            ->where('special', true)
            ->exists();

        if ($isAdmin) {
            $count = $userModel::where('tenant_id', $tenant->id)
                ->withoutTrashed()
                ->whereHas('roles', fn ($q) => $q->where('special', true))
                ->count();

            $this->enforce('max_admins', $count, 'administradores');
        } else {
            $count = $userModel::where('tenant_id', $tenant->id)
                ->withoutTrashed()
                ->where(function ($q) {
                    $q->whereDoesntHave('roles')
                        ->orWhereHas('roles', fn ($r) => $r->where('special', false)->orWhereNull('special'));
                })
                ->count();

            $this->enforce('max_users', $count, 'usuários');
        }
    }

    /**
     * Retorna o limite configurado para o recurso, ou 0 se sem limite.
     */
    public function getLimit(string $limitKey): int
    {
        $tenant = app('current.tenant');

        return (int) data_get($tenant?->settings, "limits.{$limitKey}", 0);
    }

    /**
     * Verifica se o tenant atingiu o limite sem lançar exceção.
     */
    public function hasReachedLimit(string $limitKey, int $currentCount): bool
    {
        $limit = $this->getLimit($limitKey);

        return $limit > 0 && $currentCount >= $limit;
    }
}
