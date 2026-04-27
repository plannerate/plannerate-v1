<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Support\Authorization\RbacType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

class TenantUserAccessController extends Controller
{
    /**
     * @var list<string>
     */
    private const AVAILABLE_STATUS_FILTERS = ['all', 'active', 'inactive', 'deleted'];

    /**
     * Show tenant user access management.
     */
    public function edit(Request $request, Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $statusFilter = in_array($status, self::AVAILABLE_STATUS_FILTERS, true) ? $status : 'all';

        /** @var array{
         *     users: LengthAwarePaginator<array<string, mixed>>,
         *     activeCount: int
         * } $tenantUserData
         */
        $tenantUserData = $this->runInTenantContext($tenant, function () use ($search, $statusFilter): array {
            $query = TenantUser::query()
                ->withTrashed()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($where) use ($search): void {
                        $where
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
                });

            if ($statusFilter === 'active') {
                $query->whereNull('deleted_at')->where('is_active', true);
            } elseif ($statusFilter === 'inactive') {
                $query->whereNull('deleted_at')->where('is_active', false);
            } elseif ($statusFilter === 'deleted') {
                $query->onlyTrashed();
            }

            $users = $query
                ->latest()
                ->paginate(10)
                ->withQueryString()
                ->through(fn (TenantUser $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => (bool) $user->is_active,
                    'deleted_at' => $user->deleted_at?->toDateTimeString(),
                    'created_at' => $user->created_at?->toDateTimeString(),
                ]);

            return [
                'users' => $users,
                'activeCount' => TenantUser::query()->count(),
            ];
        });

        $roles = Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', RbacType::TENANT)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->all();

        $roleNamesByUser = $this->tenantRoleNamesByUser($tenant);
        $users = $tenantUserData['users']->through(fn (array $user): array => [
            ...$user,
            'role_names' => $roleNamesByUser[$user['id']] ?? [],
        ]);

        $planUserLimit = $tenant->plan_user_limit;
        $hasPlanLimit = $planUserLimit !== null;
        $hasReachedLimit = $hasPlanLimit && $tenantUserData['activeCount'] >= $planUserLimit;
        $canCreateUsers = $hasPlanLimit && ! $hasReachedLimit;

        $limitMessage = null;

        if ($tenant->plan === null || ! $hasPlanLimit) {
            $limitMessage = __('app.landlord.tenant_access.messages.no_plan_limit');
        } elseif ($hasReachedLimit) {
            $limitMessage = __('app.landlord.tenant_access.messages.limit_reached');
        }

        return Inertia::render('landlord/tenants/Access', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan_user_limit' => $planUserLimit,
                'users_count' => $tenantUserData['activeCount'],
                'can_create_users' => $canCreateUsers,
                'limit_message' => $limitMessage,
            ],
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
            'status_options' => [
                ['value' => 'all', 'label' => __('app.landlord.tenant_access.statuses.all')],
                ['value' => 'active', 'label' => __('app.landlord.common.active')],
                ['value' => 'inactive', 'label' => __('app.landlord.common.inactive')],
                ['value' => 'deleted', 'label' => __('app.landlord.tenant_access.statuses.deleted')],
            ],
        ]);
    }

    /**
     * Store tenant scoped user.
     */
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->ensureUserCreationAllowed($tenant);
        $validated = $this->validateStorePayload($request, $tenant);

        $this->runInTenantContext($tenant, function () use ($validated, $tenant): void {
            $tenantUser = TenantUser::query()->create([
                ...Arr::except($validated, ['role_names']),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            $this->syncTenantRoles($tenant, $tenantUser, $validated['role_names'] ?? []);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.created'),
        ]);

        return to_route('landlord.tenants.access.edit', $tenant);
    }

    /**
     * Update tenant scoped user.
     */
    public function update(Request $request, Tenant $tenant, string $userId): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $this->validateUpdatePayload($request, $tenant, $userId);

        $this->runInTenantContext($tenant, function () use ($validated, $tenant, $userId): void {
            $tenantUser = TenantUser::query()->findOrFail($userId);
            $password = $validated['password'] ?? null;

            $tenantUser->update([
                ...Arr::except($validated, ['password', 'role_names']),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                ...($password ? ['password' => $password] : []),
            ]);

            $this->syncTenantRoles($tenant, $tenantUser, $validated['role_names'] ?? []);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.updated'),
        ]);

        return back();
    }

    /**
     * Toggle tenant user active status.
     */
    public function toggleActive(Request $request, Tenant $tenant, string $userId): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $this->runInTenantContext($tenant, function () use ($validated, $userId): void {
            $tenantUser = TenantUser::query()->findOrFail($userId);
            $tenantUser->update([
                'is_active' => (bool) $validated['is_active'],
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.status_updated'),
        ]);

        return back();
    }

    /**
     * Soft delete tenant user.
     */
    public function destroy(Tenant $tenant, string $userId): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->runInTenantContext($tenant, function () use ($userId): void {
            $tenantUser = TenantUser::query()->findOrFail($userId);
            $tenantUser->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.deleted'),
        ]);

        return back();
    }

    /**
     * Restore soft deleted tenant user.
     */
    public function restore(Tenant $tenant, string $userId): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->runInTenantContext($tenant, function () use ($userId): void {
            $tenantUser = TenantUser::query()->withTrashed()->findOrFail($userId);

            if ($tenantUser->trashed()) {
                $tenantUser->restore();
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.restored'),
        ]);

        return back();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function tenantRoleNamesByUser(Tenant $tenant): array
    {
        return Role::query()
            ->selectRaw('model_has_roles.model_id as user_id, roles.name as role_name')
            ->join('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.type', RbacType::TENANT)
            ->whereNull('roles.tenant_id')
            ->where('model_has_roles.model_type', TenantUser::class)
            ->where('model_has_roles.tenant_id', $tenant->id)
            ->orderBy('roles.name')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows): array => $rows->pluck('role_name')->values()->all())
            ->all();
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function syncTenantRoles(Tenant $tenant, TenantUser $tenantUser, array $roleNames): void
    {
        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenant->id);
        $tenantUser->syncRoles($roleNames);
        setPermissionsTeamId($currentTeamId);
    }

    /**
     * @throws ValidationException
     */
    private function ensureUserCreationAllowed(Tenant $tenant): void
    {
        $limit = $tenant->plan_user_limit;

        if ($tenant->plan === null || $limit === null) {
            throw ValidationException::withMessages([
                'limit' => __('app.landlord.tenant_access.messages.no_plan_limit'),
            ]);
        }

        $usersCount = $this->runInTenantContext($tenant, fn (): int => TenantUser::query()->count());

        if ($usersCount >= $limit) {
            throw ValidationException::withMessages([
                'limit' => __('app.landlord.tenant_access.messages.limit_reached'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStorePayload(Request $request, Tenant $tenant): array
    {
        return $this->runInTenantContext($tenant, function () use ($request): array {
            return Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'is_active' => ['sometimes', 'boolean'],
                'role_names' => ['nullable', 'array'],
                'role_names.*' => [
                    'string',
                    'distinct',
                    Rule::exists('landlord.roles', 'name')
                        ->where(static fn ($query) => $query
                            ->where('guard_name', 'web')
                            ->where('type', RbacType::TENANT)
                            ->whereNull('tenant_id')),
                ],
            ])->validate();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUpdatePayload(Request $request, Tenant $tenant, string $userId): array
    {
        return $this->runInTenantContext($tenant, function () use ($request, $userId): array {
            return Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId, (new TenantUser)->getKeyName())],
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
                'is_active' => ['sometimes', 'boolean'],
                'role_names' => ['nullable', 'array'],
                'role_names.*' => [
                    'string',
                    'distinct',
                    Rule::exists('landlord.roles', 'name')
                        ->where(static fn ($query) => $query
                            ->where('guard_name', 'web')
                            ->where('type', RbacType::TENANT)
                            ->whereNull('tenant_id')),
                ],
            ])->validate();
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runInTenantContext(Tenant $tenant, callable $callback): mixed
    {
        $tenantConnectionName = $this->resolveTenantConnectionName();
        $originalTenantDatabase = config("database.connections.{$tenantConnectionName}.database");
        $originalTenant = CurrentTenantModel::current();
        $tenant->makeCurrent();

        try {
            return $callback();
        } finally {
            if ($originalTenant !== null) {
                $originalTenant->makeCurrent();
            } else {
                CurrentTenantModel::forgetCurrent();
                config([
                    "database.connections.{$tenantConnectionName}.database" => $originalTenantDatabase,
                ]);
                DB::purge($tenantConnectionName);
            }
        }
    }

    private function resolveTenantConnectionName(): string
    {
        return (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
    }
}
