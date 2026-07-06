<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Http\Requests\Tenant\UpdateUserRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PasswordSetup\IssuePasswordSetupService;
use App\Support\Authorization\RbacType;
use App\Support\PasswordSetup\PasswordSetupException;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;
    use InteractsWithTrashedFilter;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = $this->requestString($request, 'search');
        $isActive = $this->requestEnum($request, 'is_active', ['0', '1']);
        $trashed = $this->resolveTrashedFilter($request);

        return $this->renderDeferredIndex('tenant/users/Index', 'users', fn (): LengthAwarePaginator => $this->usersPaginator(
            $search,
            $isActive,
            $trashed,
            $this->resolvePerPage($request, 10),
        ), [
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
                'trashed' => $trashed,
            ],
            'tenant' => $this->tenantLimitPayload(),
            'filter_options' => [
                'roles' => $this->rolesForSelect(),
            ],
            'can' => [
                'create' => $this->authorize('create', User::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('tenant/users/Form', [
            'user' => null,
            'roles' => $this->rolesForSelect(),
            'tenant' => $this->tenantLimitPayload(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();
        $roleIds = is_array($validated['role_ids'] ?? null) ? $validated['role_ids'] : [];

        if ($this->roleIdsIncludeTenantAdmin($roleIds)) {
            $this->ensureUserCreationAllowed();
        }

        $user = User::query()->create([
            ...Arr::except($validated, ['role_ids']),
            'password' => Str::password(32),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncTenantRoles($user, $roleIds);

        $tenant = Tenant::current();
        abort_if(! $tenant instanceof Tenant, 404);

        try {
            app(IssuePasswordSetupService::class)->issue($tenant, $user->id, $request->user(), $request);
        } catch (PasswordSetupException $exception) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => $exception->getMessage(),
            ]);

            return $this->toTenantRoute('tenant.users.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.users.messages.created'),
        ]);

        return $this->toTenantRoute('tenant.users.index');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles' => fn ($query) => $query
            ->whereNull('roles.tenant_id')
            ->where('roles.guard_name', 'web')
            ->where('roles.type', RbacType::TENANT)
            ->orderBy('roles.name')]);

        return Inertia::render('tenant/users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'role_ids' => $user->roles->pluck('id')->values()->all(),
            ],
            'roles' => $this->rolesForSelect(),
            'tenant' => $this->tenantLimitPayload(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();
        $roleIds = is_array($validated['role_ids'] ?? null) ? $validated['role_ids'] : [];
        $password = $validated['password'] ?? null;

        $user->loadMissing(['roles' => fn ($query) => $query
            ->whereNull('roles.tenant_id')
            ->where('roles.guard_name', 'web')
            ->where('roles.type', RbacType::TENANT)]);
        $currentRoleIds = $user->roles->pluck('id')->values()->all();

        if ($this->roleIdsIncludeTenantAdmin($roleIds) && ! $this->roleIdsIncludeTenantAdmin($currentRoleIds)) {
            $this->ensureUserCreationAllowed();
        }

        $user->update([
            ...Arr::except($validated, ['password', 'role_ids']),
            'is_active' => $request->boolean('is_active', true),
            ...($password ? ['password' => $password] : []),
        ]);

        $this->syncTenantRoles($user, $roleIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.users.messages.updated'),
        ]);

        return $this->toTenantRoute('tenant.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.users.messages.deleted'),
        ]);

        return $this->toTenantRoute('tenant.users.index');
    }

    /**
     * Reenvia o link de definição de senha para um usuário do próprio tenant
     * (autoatendimento), invalidando qualquer link pendente anterior.
     */
    public function resendPasswordSetup(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $tenant = Tenant::current();
        abort_if(! $tenant instanceof Tenant, 404);

        try {
            app(IssuePasswordSetupService::class)->issue($tenant, $user->id, $request->user(), $request, isResend: true);
        } catch (PasswordSetupException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return $this->toTenantRoute('tenant.users.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.users.messages.password_setup_sent'),
        ]);

        return $this->toTenantRoute('tenant.users.index');
    }

    private function usersPaginator(string $search, string $isActive, string $trashed, int $perPage): LengthAwarePaginator
    {
        $query = User::query();
        $this->applyTrashedToQuery($query, $trashed);

        return $query
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($isActive !== '', fn ($builder) => $builder->where('is_active', $isActive === '1'))
            ->with(['roles' => fn ($builder) => $builder
                ->whereNull('roles.tenant_id')
                ->where('roles.guard_name', 'web')
                ->where('roles.type', RbacType::TENANT)
                ->orderBy('roles.name')])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->roles->pluck('name')->values()->all(),
                'created_at' => $user->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * @return array<int, array{id: string, name: string, is_admin: bool}>
     */
    private function rolesForSelect(): array
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', RbacType::TENANT)
            ->orderBy('name')
            ->get(['id', 'name', 'system_name'])
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'is_admin' => $role->system_name === 'tenant-admin',
            ])
            ->all();
    }

    /**
     * @param  list<string>  $roleIds
     */
    private function syncTenantRoles(User $user, array $roleIds): void
    {
        $roles = Role::query()
            ->whereIn('id', $roleIds)
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', RbacType::TENANT)
            ->get();

        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId($this->tenantId());
        $user->syncRoles($roles);
        setPermissionsTeamId($currentTeamId);
    }

    /**
     * @param  list<string>  $roleIds
     */
    private function roleIdsIncludeTenantAdmin(array $roleIds): bool
    {
        if ($roleIds === []) {
            return false;
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->where('system_name', 'tenant-admin')
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    private function ensureUserCreationAllowed(): void
    {
        $tenant = Tenant::current();
        $limit = $tenant?->plan_user_limit;

        if (! $tenant || $tenant->plan === null || $limit === null) {
            throw ValidationException::withMessages([
                'limit' => __('app.landlord.tenant_access.messages.no_plan_limit'),
            ]);
        }

        if ($this->countTenantAdminUsers() >= $limit) {
            throw ValidationException::withMessages([
                'limit' => __('app.landlord.tenant_access.messages.limit_reached'),
            ]);
        }
    }

    /**
     * @return array{plan_user_limit:int|null,users_count:int,limit_message:string|null}
     */
    private function tenantLimitPayload(): array
    {
        $tenant = Tenant::current();
        $usersCount = $this->countTenantAdminUsers();
        $planUserLimit = $tenant?->plan_user_limit;
        $adminLimitReached = $planUserLimit !== null && $tenant?->plan !== null && $usersCount >= $planUserLimit;

        return [
            'plan_user_limit' => $planUserLimit,
            'users_count' => $usersCount,
            'limit_message' => $adminLimitReached ? __('app.landlord.tenant_access.messages.limit_reached') : null,
        ];
    }

    private function countTenantAdminUsers(): int
    {
        return Role::query()
            ->join('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.system_name', 'tenant-admin')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.tenant_id', $this->tenantId())
            ->distinct()
            ->count('model_has_roles.model_id');
    }
}
