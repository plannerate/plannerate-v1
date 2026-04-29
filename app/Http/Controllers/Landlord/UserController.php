<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreUserRequest;
use App\Http\Requests\Landlord\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\RbacType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->string('search'));
        $isActive = $request->query('is_active');
        $roleId = trim((string) $request->string('role_id'));
        $hasIsActiveFilter = in_array($isActive, ['0', '1'], true);
        $hasRoleFilter = $roleId !== '';

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($hasIsActiveFilter, fn ($query) => $query->where('is_active', $isActive === '1'))
            ->when($hasRoleFilter, function ($query) use ($roleId): void {
                $query->whereHas('roles', function ($rolesQuery) use ($roleId): void {
                    $rolesQuery
                        ->where('roles.id', $roleId)
                        ->whereNull('roles.tenant_id')
                        ->where('roles.guard_name', 'web')
                        ->where('roles.type', RbacType::LANDLORD);
                });
            })
            ->with(['roles' => fn ($query) => $query
                ->whereNull('roles.tenant_id')
                ->where('roles.guard_name', 'web')
                ->where('roles.type', RbacType::LANDLORD)
                ->orderBy('roles.name')])
            ->latest()
            ->paginate($this->resolvePerPage($request, 10))
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->roles->pluck('name')->values()->all(),
                'created_at' => $user->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('landlord/users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'is_active' => $hasIsActiveFilter ? $isActive : '',
                'role_id' => $hasRoleFilter ? $roleId : '',
            ],
            'filter_options' => [
                'roles' => $this->rolesForSelect(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('landlord/users/Form', [
            'user' => null,
            'roles' => $this->rolesForSelect(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();
        $roleIds = $validated['role_ids'] ?? [];

        $user = User::query()->create([
            ...Arr::except($validated, ['role_ids']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncLandlordRoles($user, $roleIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.users.messages.created'),
        ]);

        return to_route('landlord.users.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles' => fn ($query) => $query
            ->whereNull('roles.tenant_id')
            ->where('roles.guard_name', 'web')
            ->where('roles.type', RbacType::LANDLORD)
            ->orderBy('roles.name')]);

        return Inertia::render('landlord/users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'role_ids' => $user->roles->pluck('id')->values()->all(),
            ],
            'roles' => $this->rolesForSelect(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();
        $roleIds = $validated['role_ids'] ?? [];
        $password = $validated['password'] ?? null;

        $user->update([
            ...Arr::except($validated, ['password', 'role_ids']),
            'is_active' => $request->boolean('is_active', true),
            ...($password ? ['password' => $password] : []),
        ]);

        $this->syncLandlordRoles($user, $roleIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.users.messages.updated'),
        ]);

        return to_route('landlord.users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.users.messages.deleted'),
        ]);

        return to_route('landlord.users.index');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function rolesForSelect(): array
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', RbacType::LANDLORD)
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
    private function syncLandlordRoles(User $user, array $roleIds): void
    {
        $roles = Role::query()
            ->whereIn('id', $roleIds)
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', RbacType::LANDLORD)
            ->get();

        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);
        $user->syncRoles($roles);
        setPermissionsTeamId($currentTeamId);
    }
}
