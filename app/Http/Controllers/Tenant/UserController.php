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
use App\Support\Authorization\RbacType;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
                'trashed' => $trashed,
            ],
            'tenant' => $this->tenantLimitPayload(),
            'filter_options' => [
                'roles' => $this->rolesForSelect(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);
        $this->ensureUserCreationAllowed();

        return Inertia::render('tenant/users/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'user' => null,
            'roles' => $this->rolesForSelect(),
            'tenant' => $this->tenantLimitPayload(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $this->ensureUserCreationAllowed();

        $validated = $request->validated();
        $roleIds = is_array($validated['role_ids'] ?? null) ? $validated['role_ids'] : [];

        $user = User::query()->create([
            ...Arr::except($validated, ['role_ids']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncTenantRoles($user, $roleIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.users.messages.created'),
        ]);

        return to_route('tenant.users.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, User $user): Response
    {
        unset($subdomain);
        $this->authorize('update', $user);

        $user->load(['roles' => fn ($query) => $query
            ->whereNull('roles.tenant_id')
            ->where('roles.guard_name', 'web')
            ->where('roles.type', RbacType::TENANT)
            ->orderBy('roles.name')]);

        return Inertia::render('tenant/users/Form', [
            'subdomain' => $this->tenantSubdomain(),
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

    public function update(UpdateUserRequest $request, string $subdomain, User $user): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $user);

        $validated = $request->validated();
        $roleIds = is_array($validated['role_ids'] ?? null) ? $validated['role_ids'] : [];
        $password = $validated['password'] ?? null;

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

        return to_route('tenant.users.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, User $user): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.users.messages.deleted'),
        ]);

        return to_route('tenant.users.index', $this->tenantRouteParameters());
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
     * @return array<int, array{id: string, name: string}>
     */
    private function rolesForSelect(): array
    {
        return Role::query()
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

        if (User::query()->count() >= $limit) {
            throw ValidationException::withMessages([
                'limit' => __('app.landlord.tenant_access.messages.limit_reached'),
            ]);
        }
    }

    /**
     * @return array{plan_user_limit:int|null,users_count:int,can_create_users:bool,limit_message:string|null}
     */
    private function tenantLimitPayload(): array
    {
        $tenant = Tenant::current();
        $usersCount = User::query()->count();
        $planUserLimit = $tenant?->plan_user_limit;
        $hasPlanLimit = $planUserLimit !== null;
        $hasReachedLimit = $hasPlanLimit && $usersCount >= $planUserLimit;
        $canCreateUsers = $hasPlanLimit && ! $hasReachedLimit;
        $limitMessage = null;

        if (! $tenant || $tenant->plan === null || ! $hasPlanLimit) {
            $limitMessage = __('app.landlord.tenant_access.messages.no_plan_limit');
        } elseif ($hasReachedLimit) {
            $limitMessage = __('app.landlord.tenant_access.messages.limit_reached');
        }

        return [
            'plan_user_limit' => $planUserLimit,
            'users_count' => $usersCount,
            'can_create_users' => $canCreateUsers,
            'limit_message' => $limitMessage,
        ];
    }
}
