<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Concerns\SwitchesTenantContext;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation\IssueImpersonationService;
use App\Services\PasswordSetup\IssuePasswordSetupService;
use App\Services\Tenancy\AdministrativeUserLimitService;
use App\Support\Authorization\RbacType;
use App\Support\Impersonation\ImpersonationException;
use App\Support\PasswordSetup\PasswordSetupException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TenantUserAccessController extends Controller
{
    use InteractsWithTrashedFilter, SwitchesTenantContext;

    /**
     * @var list<string>
     */
    private const AVAILABLE_STATUS_FILTERS = ['all', 'active', 'inactive', 'trashed'];

    public function __construct(
        private readonly AdministrativeUserLimitService $administrativeUserLimit,
    ) {}

    /**
     * Show tenant user access management.
     */
    public function edit(Request $request, Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $statusFilter = in_array($status, self::AVAILABLE_STATUS_FILTERS, true) ? $status : 'all';
        $perPage = $this->resolvePerPage($request, 10);
        $trashFilter = $this->resolveTrashedFilter($request);

        /** @var array{
         *     users: LengthAwarePaginator<array<string, mixed>>,
         *     activeCount: int
         * } $tenantUserData
         */
        $summary = $this->administrativeUserLimit->tenantAdminSummary($tenant);

        $tenantUserData = $this->runInTenantContext($tenant, function () use ($search, $statusFilter, $perPage, $trashFilter): array {
            $query = User::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($where) use ($search): void {
                        $where
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
                });

            if ($statusFilter === 'active') {
                $query->where('is_active', true);
            } elseif ($statusFilter === 'inactive') {
                $query->where('is_active', false);
            } elseif ($trashFilter) {
                $this->applyTrashedToQuery($query, $trashFilter);
            }

            $users = $query
                ->latest()
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => (bool) $user->is_active,
                    'deleted_at' => $user->deleted_at?->toDateTimeString(),
                    'created_at' => $user->created_at?->toDateTimeString(),
                ]);

            return [
                'users' => $users,
            ];
        });

        $roles = $this->administrativeUserLimit->rolesForSelect($tenant);

        $roleNamesByUser = $this->tenantRoleNamesByUser($tenant);
        $users = $tenantUserData['users']->through(fn (array $user): array => [
            ...$user,
            'role_names' => $roleNamesByUser[$user['id']] ?? [],
        ]);

        return Inertia::render('landlord/tenants/Access', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan_user_limit' => $summary['plan_user_limit'],
                'users_count' => $summary['users_count'],
                'limit_message' => $summary['limit_message'],
                'can_impersonate' => $request->user()->can('impersonate', $tenant),
            ],
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'trashed' => $trashFilter,
            ],
            'status_options' => [
                ['value' => 'all', 'label' => __('app.landlord.tenant_access.statuses.all')],
                ['value' => 'published', 'label' => __('app.landlord.common.active')],
                ['value' => 'draft', 'label' => __('app.landlord.common.inactive')],
                ['value' => 'trashed', 'label' => __('app.landlord.tenant_access.statuses.deleted')],
            ],
        ]);
    }

    /**
     * Store tenant scoped user.
     */
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $this->validateStorePayload($request, $tenant);

        $roleNames = is_array($validated['role_names'] ?? null) ? $validated['role_names'] : [];
        $this->administrativeUserLimit->ensureCanAssign($tenant, $this->roleIdsForNames($roleNames));

        $tenantUser = $this->runInTenantContext($tenant, function () use ($validated, $tenant): User {
            $tenantUser = User::query()->create([
                ...Arr::except($validated, ['role_names']),
                'password' => Str::password(32),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            $this->syncTenantRoles($tenant, $tenantUser, $validated['role_names'] ?? []);

            return $tenantUser;
        });

        try {
            app(IssuePasswordSetupService::class)->issue($tenant, $tenantUser->id, $request->user(), $request);
        } catch (PasswordSetupException $exception) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => $exception->getMessage(),
            ]);

            return $this->toLandlordRoute('landlord.tenants.access.edit', ['tenant' => $tenant]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.created'),
        ]);

        return $this->toLandlordRoute('landlord.tenants.access.edit', ['tenant' => $tenant]);
    }

    /**
     * Update tenant scoped user.
     */
    public function update(Request $request, Tenant $tenant, string $userId): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $this->validateUpdatePayload($request, $tenant, $userId);

        $roleNames = is_array($validated['role_names'] ?? null) ? $validated['role_names'] : [];
        $this->administrativeUserLimit->ensureCanAssign(
            $tenant,
            $this->roleIdsForNames($roleNames),
            $this->currentTenantRoleIds($tenant, $userId),
        );

        $this->runInTenantContext($tenant, function () use ($validated, $tenant, $userId): void {
            $tenantUser = User::query()->findOrFail($userId);
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
            $tenantUser = User::query()->findOrFail($userId);
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
     * Sync roles for a tenant user without requiring full user data.
     */
    public function syncRoles(Request $request, Tenant $tenant, string $userId): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'role_names' => ['nullable', 'array'],
            'role_names.*' => [
                'string',
                'distinct',
                Rule::in($this->administrativeUserLimit->availableRoleNames($tenant)),
                Rule::exists('landlord.roles', 'name')
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('type', RbacType::TENANT)
                        ->whereNull('tenant_id')),
            ],
        ]);

        $this->runInTenantContext($tenant, function () use ($validated, $tenant, $userId): void {
            $tenantUser = User::query()->findOrFail($userId);
            $this->syncTenantRoles($tenant, $tenantUser, $validated['role_names'] ?? []);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.updated'),
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
            $tenantUser = User::query()->findOrFail($userId);
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
            $tenantUser = User::query()->withTrashed()->findOrFail($userId);

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
     * Permanently delete a soft deleted tenant user and its role assignments.
     */
    public function forceDelete(Tenant $tenant, string $userId): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->runInTenantContext($tenant, function () use ($tenant, $userId): void {
            $tenantUser = User::query()->withTrashed()->findOrFail($userId);

            // Remove role assignments held on the landlord connection for this user/tenant.
            DB::connection('landlord')
                ->table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $tenantUser->id)
                ->where('tenant_id', $tenant->id)
                ->delete();

            $tenantUser->forceDelete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.force_deleted'),
        ]);

        return back();
    }

    /**
     * Issue an impersonation code for a tenant user and redirect the browser to the tenant's
     * host to consume it. Uses Inertia::location() so the cross-host hop happens automatically
     * even though the trigger is a normal Inertia visit.
     */
    public function impersonate(Request $request, Tenant $tenant, string $userId): RedirectResponse|SymfonyResponse
    {
        $this->authorize('impersonate', $tenant);

        try {
            $issued = app(IssueImpersonationService::class)->issue($request->user(), $tenant, $userId, $request);
        } catch (ImpersonationException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back();
        }

        return Inertia::location($issued['consumeUrl']);
    }

    /**
     * Reenvia o link de definição de senha para um usuário do tenant, invalidando
     * qualquer link pendente anterior.
     */
    public function resendPasswordSetup(Request $request, Tenant $tenant, string $userId): RedirectResponse
    {
        $this->authorize('update', $tenant);

        try {
            app(IssuePasswordSetupService::class)->issue($tenant, $userId, $request->user(), $request, isResend: true);
        } catch (PasswordSetupException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.password_setup_sent'),
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
            ->where('model_has_roles.model_type', User::class)
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
    private function syncTenantRoles(Tenant $tenant, User $tenantUser, array $roleNames): void
    {
        // Get role IDs from names
        $roleIds = DB::connection('landlord')
            ->table('roles')
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->all();

        // Prepare records to sync
        $records = collect($roleIds)->map(fn ($roleId) => [
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $tenantUser->id,
            'tenant_id' => $tenant->id,
        ])->all();

        // Remove old role assignments for this user/tenant
        DB::connection('landlord')
            ->table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $tenantUser->id)
            ->where('tenant_id', $tenant->id)
            ->delete();

        // Insert new role assignments
        if (! empty($records)) {
            DB::connection('landlord')
                ->table('model_has_roles')
                ->insert($records);
        }

        // Clear cached roles
        $tenantUser->unsetRelation('roles');
        $tenantUser->unsetRelation('permissions');
    }

    /**
     * Resolve os IDs dos perfis (roles de tenant globais) a partir dos nomes.
     *
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    private function roleIdsForNames(array $roleNames): array
    {
        if ($roleNames === []) {
            return [];
        }

        return Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', RbacType::TENANT)
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->all();
    }

    /**
     * IDs dos perfis que o usuário já possui neste tenant.
     *
     * @return list<string>
     */
    private function currentTenantRoleIds(Tenant $tenant, string $userId): array
    {
        return DB::connection('landlord')
            ->table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $userId)
            ->where('tenant_id', $tenant->id)
            ->pluck('role_id')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStorePayload(Request $request, Tenant $tenant): array
    {
        $tenantConnection = $this->resolveTenantConnectionName();
        $availableRoleNames = $this->administrativeUserLimit->availableRoleNames($tenant);

        return $this->runInTenantContext($tenant, function () use ($request, $tenantConnection, $availableRoleNames): array {
            return Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique("{$tenantConnection}.users", 'email')->whereNull('deleted_at'),
                ],
                'is_active' => ['sometimes', 'boolean'],
                'role_names' => ['nullable', 'array'],
                'role_names.*' => [
                    'string',
                    'distinct',
                    Rule::in($availableRoleNames),
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
        $tenantConnection = $this->resolveTenantConnectionName();
        $availableRoleNames = $this->administrativeUserLimit->availableRoleNames($tenant);

        return $this->runInTenantContext($tenant, function () use ($request, $userId, $tenantConnection, $availableRoleNames): array {
            return Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique("{$tenantConnection}.users", 'email')
                        ->ignore($userId, (new User)->getKeyName())
                        ->whereNull('deleted_at'),
                ],
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
                'is_active' => ['sometimes', 'boolean'],
                'role_names' => ['nullable', 'array'],
                'role_names.*' => [
                    'string',
                    'distinct',
                    Rule::in($availableRoleNames),
                    Rule::exists('landlord.roles', 'name')
                        ->where(static fn ($query) => $query
                            ->where('guard_name', 'web')
                            ->where('type', RbacType::TENANT)
                            ->whereNull('tenant_id')),
                ],
            ])->validate();
        });
    }
}
