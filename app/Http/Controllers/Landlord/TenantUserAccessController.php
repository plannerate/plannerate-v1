<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\UpdateTenantUserAccessRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\RbacType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantUserAccessController extends Controller
{
    /**
     * Show tenant user access management.
     */
    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

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

        $users = User::query()
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        $roleNamesByUser = DB::connection('landlord')
            ->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.tenant_id', $tenant->id)
            ->orderBy('roles.name')
            ->get([
                'model_has_roles.model_id as user_id',
                'roles.name as role_name',
            ])
            ->groupBy('user_id')
            ->map(fn ($rows): array => collect($rows)->pluck('role_name')->values()->all());

        return Inertia::render('landlord/tenants/Access', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'users' => $users
                ->map(fn (array $user): array => [
                    ...$user,
                    'role_names' => $roleNamesByUser[$user['id']] ?? [],
                ])
                ->values()
                ->all(),
            'roles' => $roles,
        ]);
    }

    /**
     * Update tenant scoped access for a user.
     */
    public function update(UpdateTenantUserAccessRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validated();

        $user = User::query()->findOrFail($validated['user_id']);
        $roleNames = collect($validated['roles'] ?? [])->filter()->values()->all();

        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenant->id);
        $user->syncRoles($roleNames);
        setPermissionsTeamId($currentTeamId);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_access.messages.updated'),
        ]);

        return back();
    }
}
