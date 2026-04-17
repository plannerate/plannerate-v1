<?php

namespace App\Services\Flow;

use Callcocam\LaravelRaptor\Models\Role;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FlowStepTemplateAutoAssignmentResolver
{
    /** @var Collection<int, array{id: string, tenant_id: ?string, name: string, slug: string}>|null */
    protected ?Collection $rolesCache = null;

    /** @var array<string, list<string>> */
    protected array $roleUsersCache = [];

    /** @var array<string, bool> */
    protected array $userHasTenantColumnCache = [];

    /**
     * Aplica preenchimento automático no template:
     * - default_role_id (somente quando vazio)
     * - metadata.suggested_users (merge sem duplicar)
     */
    public function applyToTemplate(FlowStepTemplate $template): bool
    {
        $currentRoleId = $this->normalizeId($template->default_role_id);
        $resolvedRoleId = $currentRoleId
            ?: $this->resolveRoleIdByStep($template->slug, $template->name, $this->normalizeId($template->tenant_id));

        $changes = [];

        if (! $currentRoleId && $resolvedRoleId) {
            $changes['default_role_id'] = $resolvedRoleId;
        }

        $roleForSuggestions = $resolvedRoleId ?: $currentRoleId;
        if ($roleForSuggestions) {
            $existingSuggested = $this->normalizeSuggestedUsers(data_get($template->metadata, 'suggested_users', []));
            $autoSuggested = $this->getSuggestedUsersByRole(
                $roleForSuggestions,
                $this->normalizeId($template->tenant_id)
            );

            $mergedSuggested = $this->mergeSuggestedUsers($existingSuggested, $autoSuggested);

            if ($mergedSuggested !== $existingSuggested) {
                $metadata = is_array($template->metadata) ? $template->metadata : [];

                if ($mergedSuggested === []) {
                    unset($metadata['suggested_users']);
                } else {
                    $metadata['suggested_users'] = $mergedSuggested;
                }

                $changes['metadata'] = $metadata;
            }
        }

        if ($changes === []) {
            return false;
        }

        $template->forceFill($changes)->save();

        return true;
    }

    /**
     * @param  iterable<int, FlowStepTemplate>  $templates
     */
    public function applyToTemplates(iterable $templates): int
    {
        $updatedCount = 0;

        foreach ($templates as $template) {
            if ($this->applyToTemplate($template)) {
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    public function resolveRoleIdByStep(?string $stepSlug, ?string $stepName, ?string $tenantId = null): ?string
    {
        $roles = $this->rolesForTenant($tenantId);
        if ($roles->isEmpty()) {
            return null;
        }

        $targetSlug = $this->normalizeRoleSlug((string) ($stepSlug ?: $stepName ?: ''));
        $targetName = Str::lower(trim((string) ($stepName ?? '')));

        if ($targetSlug !== '') {
            $matchedBySlug = $roles->first(function (array $role) use ($targetSlug) {
                return $this->normalizeRoleSlug((string) ($role['slug'] ?? '')) === $targetSlug;
            });
            if ($matchedBySlug) {
                return data_get($matchedBySlug, 'id');
            }

            $matchedByNameSlug = $roles->first(function (array $role) use ($targetSlug) {
                return $this->normalizeRoleSlug((string) ($role['name'] ?? '')) === $targetSlug;
            });
            if ($matchedByNameSlug) {
                return data_get($matchedByNameSlug, 'id');
            }
        }

        if ($targetName !== '') {
            $matchedByName = $roles->first(function (array $role) use ($targetName) {
                return Str::lower(trim((string) ($role['name'] ?? ''))) === $targetName;
            });
            if ($matchedByName) {
                return data_get($matchedByName, 'id');
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function getSuggestedUsersByRole(string $roleId, ?string $tenantId = null): array
    {
        $cacheKey = ($tenantId ?: 'global').'|'.$roleId;
        if (array_key_exists($cacheKey, $this->roleUsersCache)) {
            return $this->roleUsersCache[$cacheKey];
        }

        $userModelClass = $this->resolveUserModelClass();
        if (! $userModelClass) {
            return $this->roleUsersCache[$cacheKey] = [];
        }

        /** @var Model $userModel */
        $userModel = new $userModelClass;

        $query = $userModelClass::query()
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId))
            ->orderBy($userModel->qualifyColumn('name'));

        if ($tenantId && $this->userModelHasTenantColumn($userModelClass)) {
            $query->where($userModel->qualifyColumn('tenant_id'), $tenantId);
        }

        $users = $query->pluck($userModel->qualifyColumn('id'))
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $this->roleUsersCache[$cacheKey] = $users;

        return $users;
    }

    /**
     * @return Collection<int, array{id: string, tenant_id: ?string, name: string, slug: string}>
     */
    protected function rolesForTenant(?string $tenantId): Collection
    {
        return $this->allRoles()
            ->filter(function (array $role) use ($tenantId) {
                $roleTenantId = $this->normalizeId($role['tenant_id'] ?? null);

                if ($tenantId === null) {
                    return $roleTenantId === null;
                }

                return $roleTenantId === null || $roleTenantId === $tenantId;
            })
            ->sortBy(function (array $role) use ($tenantId) {
                $roleTenantId = $this->normalizeId($role['tenant_id'] ?? null);
                $tenantPriority = match (true) {
                    $tenantId !== null && $roleTenantId === $tenantId => 0,
                    $roleTenantId === null => 1,
                    default => 2,
                };

                return sprintf(
                    '%d|%s|%s|%s',
                    $tenantPriority,
                    $this->normalizeRoleSlug((string) ($role['slug'] ?? '')),
                    Str::lower(trim((string) ($role['name'] ?? ''))),
                    (string) ($role['id'] ?? '')
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, array{id: string, tenant_id: ?string, name: string, slug: string}>
     */
    protected function allRoles(): Collection
    {
        if ($this->rolesCache !== null) {
            return $this->rolesCache;
        }

        $this->rolesCache = Role::query()
            ->select(['id', 'tenant_id', 'name', 'slug'])
            ->get()
            ->map(function ($role) {
                return [
                    'id' => (string) $role->id,
                    'tenant_id' => $this->normalizeId($role->tenant_id),
                    'name' => (string) ($role->name ?? ''),
                    'slug' => (string) ($role->slug ?? ''),
                ];
            })
            ->values();

        return $this->rolesCache;
    }

    /**
     * @param  mixed  $usersPayload
     * @return list<string>
     */
    protected function normalizeSuggestedUsers(mixed $usersPayload): array
    {
        if (is_null($usersPayload) || $usersPayload === '') {
            return [];
        }

        if (! is_array($usersPayload)) {
            $usersPayload = [$usersPayload];
        }

        return collect($usersPayload)
            ->map(function ($value) {
                if (is_array($value)) {
                    return $value['id'] ?? $value['value'] ?? null;
                }

                return $value;
            })
            ->filter(fn ($value) => ! is_null($value) && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $existing
     * @param  list<string>  $resolved
     * @return list<string>
     */
    protected function mergeSuggestedUsers(array $existing, array $resolved): array
    {
        return collect([...$existing, ...$resolved])
            ->filter(fn ($value) => ! is_null($value) && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeRoleSlug(string $value): string
    {
        $slug = Str::slug(trim($value));

        if ($slug === '') {
            return '';
        }

        return (string) preg_replace('/-[a-z0-9]{6,12}$/', '', $slug);
    }

    protected function normalizeId(mixed $value): ?string
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected function resolveUserModelClass(): ?string
    {
        $userModelClass = config('auth.providers.users.model');

        if (! is_string($userModelClass) || ! class_exists($userModelClass)) {
            return null;
        }

        return $userModelClass;
    }

    protected function userModelHasTenantColumn(string $userModelClass): bool
    {
        if (array_key_exists($userModelClass, $this->userHasTenantColumnCache)) {
            return $this->userHasTenantColumnCache[$userModelClass];
        }

        /** @var Model $model */
        $model = new $userModelClass;
        $connection = $model->getConnectionName();
        $table = $model->getTable();

        return $this->userHasTenantColumnCache[$userModelClass] = Schema::connection($connection)
            ->hasColumn($table, 'tenant_id');
    }
}
