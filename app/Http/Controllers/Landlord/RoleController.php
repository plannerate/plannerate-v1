<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreRoleRequest;
use App\Http\Requests\Landlord\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * @var list<string>
     */
    private const PROTECTED_ROLES = ['landlord-admin', 'tenant-admin'];

    /**
     * Display a listing of roles.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->withCount('permissions')
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'is_protected' => in_array($role->name, self::PROTECTED_ROLES, true),
                'created_at' => $role->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('landlord/roles/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('landlord/roles/Form', [
            'role' => null,
            'permissions' => $this->availablePermissions(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validated();

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.roles.messages.created'),
        ]);

        return to_route('landlord.roles.index');
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(Role $role): Response
    {
        $role = $this->guardGlobalRole($role);

        $this->authorize('update', $role);

        $role->load('permissions:id,name');

        return Inertia::render('landlord/roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'is_protected' => in_array($role->name, self::PROTECTED_ROLES, true),
            ],
            'permissions' => $this->availablePermissions(),
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role = $this->guardGlobalRole($role);

        $this->authorize('update', $role);

        $validated = $request->validated();

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.roles.messages.protected'),
            ]);

            return back();
        }

        $role->update([
            'name' => $validated['name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.roles.messages.updated'),
        ]);

        return to_route('landlord.roles.index');
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $role = $this->guardGlobalRole($role);

        $this->authorize('delete', $role);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.roles.messages.protected'),
            ]);

            return back();
        }

        $hasAssignments = Role::query()
            ->join('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.id', $role->id)
            ->exists();

        if ($hasAssignments) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.roles.messages.in_use'),
            ]);

            return back();
        }

        $role->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.roles.messages.deleted'),
        ]);

        return to_route('landlord.roles.index');
    }

    /**
     * @return array<int, array{name: string}>
     */
    private function availablePermissions(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (Permission $permission): array => [
                'name' => $permission->name,
            ])
            ->all();
    }

    private function guardGlobalRole(Role $role): Role
    {
        abort_if($role->tenant_id !== null || $role->guard_name !== 'web', 404);

        return $role;
    }
}
