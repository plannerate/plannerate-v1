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
use App\Services\Tenancy\AdministrativeUserLimitService;
use App\Support\Authorization\RbacType;
use App\Support\PasswordSetup\PasswordSetupException;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;
    use InteractsWithTrashedFilter;

    public function __construct(
        private readonly AdministrativeUserLimitService $administrativeUserLimit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = $this->requestString($request, 'search');
        $isActive = $this->requestEnum($request, 'is_active', ['0', '1']);
        $trashed = $this->resolveTrashedFilter($request);
        $tenant = $this->currentTenantOrFail();

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
            'tenant' => $this->administrativeUserLimit->tenantAdminSummary($tenant),
            'filter_options' => [
                'roles' => $this->administrativeUserLimit->rolesForSelect($tenant),
            ],
            'can' => [
                'create' => $this->authorize('create', User::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        $tenant = $this->currentTenantOrFail();

        return Inertia::render('tenant/users/Form', [
            'user' => null,
            'roles' => $this->administrativeUserLimit->rolesForSelect($tenant),
            'tenant' => $this->administrativeUserLimit->tenantAdminSummary($tenant),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();
        $roleIds = is_array($validated['role_ids'] ?? null) ? $validated['role_ids'] : [];
        $tenant = $this->currentTenantOrFail();

        $this->administrativeUserLimit->ensureCanAssign($tenant, $roleIds);

        $user = User::query()->create([
            ...Arr::except($validated, ['role_ids']),
            'password' => Str::password(32),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncTenantRoles($user, $roleIds);

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

        $tenant = $this->currentTenantOrFail();

        return Inertia::render('tenant/users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'role_ids' => $user->roles->pluck('id')->values()->all(),
            ],
            'roles' => $this->administrativeUserLimit->rolesForSelect($tenant),
            'tenant' => $this->administrativeUserLimit->tenantAdminSummary($tenant),
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

        $this->administrativeUserLimit->ensureCanAssign($this->currentTenantOrFail(), $roleIds, $currentRoleIds);

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

        if ($user->trashed()) {
            $user->forceDelete();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('app.tenant.users.messages.force_deleted'),
            ]);

            return $this->toTenantRoute('tenant.users.index');
        }

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.users.messages.deleted'),
        ]);

        return $this->toTenantRoute('tenant.users.index');
    }

    public function restore(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.users.messages.restored'),
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
                'trashed' => $user->trashed(),
            ]);
    }

    private function currentTenantOrFail(): Tenant
    {
        $tenant = Tenant::current();
        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
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
}
